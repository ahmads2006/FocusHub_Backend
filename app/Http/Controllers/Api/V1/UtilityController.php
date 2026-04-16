<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\FormatHelper;
use App\Helpers\SecurityHelper;
use App\Helpers\MediaHelper;
use App\Helpers\ApiResponseHelper;

class UtilityController extends Controller
{
    /**
     * Test the API Response Helper.
     */
    public function testResponseHelper(Request $request) 
    {
        return ApiResponseHelper::success(
            ['test' => 'Hello World'], 
            'ApiResponseHelper is working perfectly!'
        );
    }

    /**
     * Format file size (bytes to human readable string)
     */
    public function formatBytes(Request $request)
    {
        $request->validate([
            'bytes' => 'required|integer|min:0'
        ]);

        $formatted = FormatHelper::bytes($request->bytes);
        
        return ApiResponseHelper::success([
            'original_bytes' => $request->bytes,
            'formatted_size' => $formatted,
        ], 'Bytes formatted successfully');
    }

    /**
     * Format large numbers (like 1500 -> 1.5K)
     */
    public function formatNumber(Request $request)
    {
        $request->validate([
            'number' => 'required|integer|min:0'
        ]);

        $formatted = FormatHelper::number($request->number);
        
        return ApiResponseHelper::success([
            'original_number' => $request->number,
            'formatted_number' => $formatted,
        ], 'Number formatted successfully');
    }

    /**
     * Mask an email address
     */
    public function maskEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $masked = SecurityHelper::maskEmail($request->email);
        
        return ApiResponseHelper::success([
            'original_email' => $request->email,
            'masked_email'   => $masked,
        ], 'Email masked successfully');
    }

    /**
     * Get image orientation from dimensions
     */
    public function imageOrientation(Request $request)
    {
        $request->validate([
            'width'  => 'required|integer|min:1',
            'height' => 'required|integer|min:1',
        ]);

        $orientation = MediaHelper::getOrientation($request->width, $request->height);
        
        return ApiResponseHelper::success([
            'width'       => $request->width,
            'height'      => $request->height,
            'orientation' => $orientation,
        ], 'Orientation calculated successfully');
    }
}
