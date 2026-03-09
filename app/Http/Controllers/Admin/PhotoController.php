<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\BannedImageHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    /**
     * Display a listing of all photos for management.
     */
    public function index(Request $request)
    {
        $query = Image::query()->with('user');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%')
                  ->orWhereHas('user', function($q) use ($request) {
                      $q->where('name', 'like', '%' . $request->search . '%');
                  });
        }

        if ($request->filled('privacy')) {
            $query->where('privacy', $request->privacy);
        }

        $images = $query->latest()->paginate(20);

        return view('admin.photos.index', compact('images'));
    }

    /**
     * Hide or show a photo.
     */
    public function toggleVisibility(Image $image)
    {
        $image->privacy = ($image->privacy === 'hidden') ? 'public' : 'hidden';
        $image->save();

        return back()->with('success', 'تم تحديث حالة ظهور الصورة بنجاح.');
    }

    /**
     * Permanently delete a photo.
     */
    public function destroy(Image $image)
    {
        // Use the existing service or direct deletion
        if (Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }
        
        $image->delete();

        return back()->with('success', 'تم حذف الصورة نهائياً من النظام.');
    }

    /**
     * Ban a photo's hash and delete it.
     */
    public function ban(Image $image)
    {
        $path = storage_path('app/public/' . $image->path);
        
        if (file_exists($path)) {
            $hash = md5_file($path);
            
            BannedImageHash::firstOrCreate([
                'hash' => $hash
            ], [
                'reason' => 'Admin Manual Ban',
                'details' => [
                    'original_name' => $image->title,
                    'user_id' => $image->user_id,
                    'banned_at' => now()->toDateTimeString()
                ]
            ]);

            Storage::disk('public')->delete($image->path);
        }

        $image->delete();

        return back()->with('success', 'تم حظر بصمة الصورة وحذفها نهائياً.');
    }

    /**
     * List banned hashes.
     */
    public function bannedHashes()
    {
        $hashes = BannedImageHash::latest()->paginate(50);
        return view('admin.photos.banned_hashes', compact('hashes'));
    }

    /**
     * Remove a hash from blacklist.
     */
    public function unbanHash(BannedImageHash $hash)
    {
        $hash->delete();
        return back()->with('success', 'تم إلغاء حظر البصمة بنجاح.');
    }
}
