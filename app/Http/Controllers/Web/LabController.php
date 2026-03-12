<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

use App\Services\Core\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LabController extends Controller
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Handle real-time laboratory processing request
     */
    public function process(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:81920', // Supports up to 80MB as requested
            'compression' => 'required|boolean',
            'watermark' => 'required|boolean',
            'orientation' => 'required|boolean',
            'multisize' => 'required|boolean',
        ]);

        // Temporarily override user settings for this lab session if needed
        // but for now we just pass the flags to the service
        
        $data = [
            'auto_orient' => $request->boolean('orientation'),
            'title' => 'Lab Test: ' . $request->file('image')->getClientOriginalName(),
            'privacy' => 'private', // Keep lab tests private
        ];

        try {
            // Perform real upload and processing
            $image = $this->imageService->processAndUpload($request->file('image'), $data, Auth::id());

            // Prepare response with results
            return response()->json([
                'success' => true,
                'original_size' => $request->file('image')->getSize(),
                'processed_size' => filesize(Storage::disk('public')->path($image->path)),
                'preview_url' => $image->url, // This is the optimized version
                'filename' => $image->filename,
                'file_type' => $image->file_type,
                'metadata' => $image->metadata,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
