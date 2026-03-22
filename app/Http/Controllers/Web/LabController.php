<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Services\AI\MediaAnalyzerManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use ImageKit\ImageKit;
use Illuminate\Support\Str;
use Throwable;

class LabController extends Controller
{
    protected $aiManager;

    public function __construct(MediaAnalyzerManager $aiManager)
    {
        $this->aiManager = $aiManager;
    }

    /**
     * Handle real-time laboratory performance diagnostic request
     */
    public function process(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:81920', // Supports up to 80MB
        ]);

        $file = $request->file('image');
        $timings = [];

        try {
            // Stage 1: Local I/O Time
            $t0 = microtime(true);
            $fileName = 'lab_' . Str::random(10) . '_' . preg_replace('/[^A-Za-z0-9\-\_\.]/', '', $file->getClientOriginalName());
            $localPath = 'lab/' . $fileName;
            Storage::disk('public')->put($localPath, file_get_contents($file->getRealPath()));
            $localFullPath = Storage::disk('public')->path($localPath);
            $timings['local_io_ms'] = round((microtime(true) - $t0) * 1000, 2);

            // Stage 2: CDN Sync Time (ImageKit)
            $t1 = microtime(true);
            $imageKit = new ImageKit(
                config('services.imagekit.public_key') ?? '',
                config('services.imagekit.private_key') ?? '',
                config('services.imagekit.url_endpoint') ?? ''
            );
            $cloudResponse = $imageKit->uploadFiles([
                'file' => base64_encode(file_get_contents($localFullPath)),
                'fileName' => $fileName,
                'useUniqueFileName' => true,
                'folder' => '/opticvault/lab',
            ]);
            $timings['cdn_upload_ms'] = round((microtime(true) - $t1) * 1000, 2);

            if (isset($cloudResponse->error)) {
                $errorMsg = is_string($cloudResponse->error) ? $cloudResponse->error : json_encode($cloudResponse->error);
                throw new \Exception("CDN Upload Failed: " . $errorMsg);
            }

            // Stage 3: Database & Mocking
            // We need a real DB record so the AI Analyzer and Drivers can fetch the ImageKit file_id
            $t2 = microtime(true);
            $image = Image::create([
                'user_id' => Auth::id() ?? 1,
                'title' => 'Diagnostic Trace',
                'filename' => $fileName,
                'file_type' => $file->getClientOriginalExtension(),
                'size' => $file->getSize(),
                'privacy' => 'private',
            ]);
            $image->storage()->updateOrCreate(['image_id' => $image->id], [
                'path' => $localPath,
                'imagekit_file_id' => $cloudResponse->result->fileId ?? null,
                'imagekit_file_path' => $cloudResponse->result->filePath ?? null,
            ]);
            // Reload all relationships expected by AI drivers
            $image->load(['storage', 'meta', 'moderation', 'settings']);
            $timings['database_ms'] = round((microtime(true) - $t2) * 1000, 2);

            // Stage 4: AI Analysis
            $t3 = microtime(true);
            // Run analysis synchronously for the trace
            $aiResult = $this->aiManager->analyze($image);
            $timings['ai_analysis_ms'] = round((microtime(true) - $t3) * 1000, 2);

            // Stage 5: Total Pipeline Time
            $timings['total_ms'] = round((microtime(true) - $t0) * 1000, 2);

            // --- Cleanup Lab Artifacts ---
            // Remove DB record
            $image->delete();
            // Remove local file
            Storage::disk('public')->delete($localPath);
            // Remove ImageKit file to preserve CDN storage quota
            try {
                if (isset($cloudResponse->result->fileId)) {
                    $imageKit->deleteFile($cloudResponse->result->fileId);
                }
            } catch (Throwable $e) {
                // Ignore cleanup errors
            }

            // Prepare response with results and exact timings
            return response()->json([
                'success' => true,
                'timings' => $timings,
                'ai_result' => $aiResult->toArray(),
                'driver_used' => $this->aiManager->getDefaultDriver(),
                'preview_url' => $cloudResponse->result->url ?? null,
            ]);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine()
            ], 500);
        }
    }
}
