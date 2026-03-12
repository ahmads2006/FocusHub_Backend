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
        if (\Illuminate\Support\Facades\Auth::id() !== $image->user_id) {
            abort(403, 'غير مصرح لك بمشاركة هذه الصورة.');
        }

        $link = $this->service->generate($image, null, null, 1);
        $url = $this->service->getFullUrl($link);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء رابط التدمير الذاتي بنجاح!',
            'url' => $url
        ]);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'shareable_id' => 'required|string',
            'shareable_type' => 'required|string|in:App\Models\Image,App\Models\Album',
            'expires_in'        => 'nullable|integer', // hours
            'permission'         => 'nullable|in:view,download',
            'max_access'         => 'nullable|integer|min:1',
            'auto_rotate'        => 'nullable|boolean',
            'require_watermark'  => 'nullable|boolean',
        ]);

        $modelClass = $request->shareable_type;
        $model = $modelClass::findOrFail($request->shareable_id);

        if (\Illuminate\Support\Facades\Auth::id() !== $model->user_id) {
            abort(403, 'غير مصرح لك بمشاركة هذا العنصر.');
        }

        $expiry = $request->expires_in ? now()->addHours((int) $request->expires_in) : null;
        $permission = $request->permission ?? 'view';
        $maxAccess = $request->max_access ? (int) $request->max_access : null;
        $autoRotate = $request->input('auto_rotate', false);

        $requireWatermark = $request->has('require_watermark')
            ? (bool) $request->input('require_watermark')
            : null;

        $link = $this->service->generate($model, $expiry, null, $maxAccess, $permission, $autoRotate, $requireWatermark);
        $url = $this->service->getFullUrl($link);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء رابط المشاركة بنجاح!',
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

    public function verifyPassword(Request $request, $token)
    {
        $link = SharedLink::where('token_hash', hash('sha256', $token))->firstOrFail();
        
        $request->validate([
            'password' => 'required|string',
        ]);

        if (Hash::check($request->password, $link->password)) {
            $request->session()->put("link_auth_{$link->id}", true);
            return redirect()->route('shared.link.show', $token);
        }

        return back()->withErrors(['password' => 'كلمة المرور غير صحيحة.']);
    }
}
