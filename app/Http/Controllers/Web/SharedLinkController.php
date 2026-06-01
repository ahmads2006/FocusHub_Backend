<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

use App\Models\Album;
use App\Models\Image;
use App\Models\SharedLink;
use App\Services\Security\SharedLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SharedLinkController extends Controller
{
    protected $service;

    public function __construct(SharedLinkService $service)
    {
        $this->service = $service;
    }

    public function generateShareOnceLink(Image $image)
    {
        $userId = \Illuminate\Support\Facades\Auth::id();
        if ($image->privacy !== 'public' && $userId !== $image->user_id) {
            abort(403, 'غير مصرح لك بمشاركة هذه الصورة.');
        }

        $image->loadMissing('settings');
        $allowDownload = (bool)($image->settings?->allow_download ?? false);
        $permission = $allowDownload ? 'download' : 'view';
        $watermarkOnDownload = $image->settings?->watermark_on_download ?? null;

        $link = $this->service->generate(
            $image,
            null,
            null,
            1,
            $permission,
            true,
            $watermarkOnDownload,
            'رابط لمرة واحدة'
        );
        $url = $this->service->getFullUrl($link);

        return response()->json([
            'success' => true,
            'message' => __('messages.self_destruct_link_created'),
            'url' => $url
        ]);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'shareable_id' => 'required|string',
            'shareable_type' => 'required|string|in:App\Models\Image,App\Models\Album',
            'expires_in'        => 'nullable|integer', // hours
            'password'          => 'nullable|string|min:4',
            'permission'         => 'nullable|in:view,download',
            'max_access'         => 'nullable|integer|min:1',
            'auto_rotate'        => 'nullable|boolean',
            'require_watermark'  => 'nullable|boolean',
        ]);

        $modelClass = $request->shareable_type;
        $model = $modelClass::findOrFail($request->shareable_id);

        $userId = \Illuminate\Support\Facades\Auth::id();
        $isPublic = ($model instanceof Image && $model->privacy === 'public') || ($model instanceof Album && $model->privacy === 'public');

        if (!$isPublic && $userId !== $model->user_id) {
            abort(403, 'غير مصرح لك بمشاركة هذا العنصر.');
        }

        $expiry = $request->expires_in ? now()->addHours((int) $request->expires_in) : null;
        $permission = $request->permission ?? 'view';
        $maxAccess = $request->max_access ? (int) $request->max_access : null;
        $password = $request->password ?: null;
        $autoRotate = $request->input('auto_rotate', false);

        $requireWatermark = $request->has('require_watermark')
            ? (bool) $request->input('require_watermark')
            : null;

        $link = $this->service->generate($model, $expiry, $password, $maxAccess, $permission, $autoRotate, $requireWatermark);
        $url = $this->service->getFullUrl($link);

        return response()->json([
            'success' => true,
            'message' => __('messages.share_link_created'),
            'url' => $url
        ]);
    }

    public function show(Request $request, $token)
    {
        // Link is already validated and retrieved by middleware
        $link = $request->attributes->get('shared_link');
        $shareable = $link->shareable;

        if ($shareable instanceof Album) {
            return view('shared_links.album', [
                'album' => $shareable,
                'link' => $link
            ]);
        }

        if ($shareable instanceof Image) {
            return view('shared_links.photo', [
                'photo' => $shareable,
                'link' => $link
            ]);
        }

        abort(404, 'Shareable content type is not supported.');
    }

    public function downloadAlbum(Request $request, $token)
    {
        $link = $request->attributes->get('shared_link');
        $shareable = $link->shareable;

        if (!$shareable instanceof Album) {
            abort(404, 'الرابط لا يشير إلى ألبوم.');
        }

        if ($link->permission !== 'download') {
            abort(403, 'غير مصرح بتنزيل هذا الألبوم.');
        }

        $items = $shareable->photos ?? $shareable->images ?? collect();
        if ($items->isEmpty()) {
            abort(404, 'الألبوم فارغ ولا يوجد ما يمكن تنزيله.');
        }

        $zipFileName = 'Album_' . \Illuminate\Support\Str::slug($shareable->title) . '_' . time() . '.zip';
        $tempDir = storage_path('app/temp');
        if (!\Illuminate\Support\Facades\File::exists($tempDir)) {
            \Illuminate\Support\Facades\File::makeDirectory($tempDir, 0755, true);
        }
        
        $zipFilePath = $tempDir . '/' . $zipFileName;
        $zip = new \ZipArchive();
        
        if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $tempFiles = [];
            
            foreach ($items as $index => $image) {
                // Determine storage disk and path
                $path = $image->storage->path ?? $image->path ?? null;
                if (!$path) continue;

                $preferredDisk = $image->storage->disk ?? 'public';
                if ($preferredDisk === 'spaces') {
                    $preferredDisk = 's3';
                }

                $activeDisk = null;
                $disksToCheck = array_unique([$preferredDisk, 'public', 'local', 's3']);
                
                foreach ($disksToCheck as $d) {
                    if (\Illuminate\Support\Facades\Storage::disk($d)->exists($path)) {
                        $activeDisk = $d;
                        break;
                    }
                }
                
                if ($activeDisk) {
                    $stream = \Illuminate\Support\Facades\Storage::disk($activeDisk)->readStream($path);
                    if ($stream) {
                        $tempFile = tempnam($tempDir, 'album_img_');
                        $out = fopen($tempFile, 'wb');
                        stream_copy_to_stream($stream, $out);
                        fclose($out);
                        fclose($stream);
                        
                        $tempFiles[] = $tempFile;
                        
                        // Define a clean name for the ZIP
                        $ext = pathinfo($image->filename ?? $path, PATHINFO_EXTENSION);
                        if (!$ext) $ext = 'jpg';
                        $baseName = \Illuminate\Support\Str::slug($image->title ?: 'image_' . ($index + 1));
                        $nameInZip = sprintf('%03d', $index + 1) . '_' . $baseName . '.' . $ext;
                        
                        $zip->addFile($tempFile, $nameInZip);
                    }
                }
            }
            $zip->close();
            
            // Clean up temporary local files downloaded from cloud/disk
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
            
            if (file_exists($zipFilePath)) {
                return response()->download($zipFilePath)->deleteFileAfterSend(true);
            }
        }

        abort(500, 'فشل في إنشاء ملف الألبوم (ZIP).');
    }

    public function verifyPassword(Request $request, $token)
    {
        $link = $request->attributes->get('shared_link');
        $authId = $request->attributes->get('shared_link_auth_id');

        if (!$link) {
            abort(404, 'Shared link is invalid or expired.');
        }
        
        $request->validate([
            'password' => 'required|string',
        ]);

        if (Hash::check($request->password, $link->password)) {
            $request->session()->put("link_auth_{$authId}", true);
            return redirect()->route('shared_link.show', $token);
        }

        return back()->withErrors(['password' => 'كلمة المرور غير صحيحة.']);
    }
}
