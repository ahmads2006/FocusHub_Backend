<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

use App\Models\Album;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\AlbumDeletionOTP;
use Illuminate\Support\Facades\Log;

class AlbumController extends Controller
{
    /**
     * Display the specified album and its collaborators.
     */
    public function show(Album $album)
    {
        // Authorization check: User must be owner or collaborator to see private/hidden albums
        if ($album->privacy !== 'public' && 
            Auth::id() !== $album->user_id && 
            !$album->collaborators->contains(Auth::id())) {
            abort(403, 'غير مصرح لك بالوصول لهذا الألبوم.');
        }

        $album->load(['images.user', 'collaborators']);
        
        return view('albums.show', compact('album'));
    }

    /**
     * Add a collaborator to the album.
     */
    public function addCollaborator(Request $request, Album $album)
    {
        // Only owner can add collaborators
        if (Auth::id() !== $album->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|in:admin,contributor,viewer',
        ]);

        $userToAdd = User::where('email', $validated['email'])->first();

        if ($userToAdd->id === $album->user_id) {
            return back()->withErrors(['email' => 'لا يمكنك إضافة نفسك كمتعاون (أنت مالك الألبوم).']);
        }

        if ($album->collaborators->contains($userToAdd->id)) {
            return back()->withErrors(['email' => 'هذا المستخدم متعاون بالفعل في هذا الألبوم.']);
        }

        $album->collaborators()->attach($userToAdd->id, ['role' => $validated['role']]);

        return back()->with('success', "تم إضافة {$userToAdd->name} كمتعاون بنجاح.");
    }

    /**
     * Update the specifies album.
     */
    public function update(Request $request, Album $album)
    {
        $this->authorize('update', $album);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'privacy' => 'required|in:public,private,hidden',
        ]);

        $album->update($validated);

        return back()->with('success', 'تم تحديث الألبوم بنجاح.');
    }

    /**
     * Request a deletion OTP via email.
     */
    public function requestDeleteOTP(Album $album)
    {
        $this->authorize('delete', $album);

        $code = rand(100000, 999999);
        
        // Store in Redis Cache with 10 min TTL
        \Illuminate\Support\Facades\Cache::put("album_delete_otp_{$album->id}", $code, now()->addMinutes(10));

        try {
            Mail::to(Auth::user()->email)->send(new AlbumDeletionOTP($code, $album->title));
        } catch (\Exception $e) {
            Log::error("Failed to send album deletion OTP: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'فشل إرسال البريد الإلكتروني. يرجى المحاولة لاحقاً.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني.']);
    }

    /**
     * Remove the specified album from storage after OTP verification.
     */
    public function destroy(Request $request, Album $album)
    {
        $this->authorize('delete', $album);

        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $otpCode = \Illuminate\Support\Facades\Cache::get("album_delete_otp_{$album->id}");

        if (!$otpCode || $otpCode != $request->otp) {
            return back()->with('error', 'رمز التحقق غير صحيح أو منتهي الصلاحية.');
        }

        // Cleanup: Delete pictures if the user wants to (or just the album)
        // For now, let's just delete the album - Eloquent will handle cleanup if Cascade is set, 
        // but we should probably manually delete images to clear cloud/local storage via ImageService.
        
        $imageService = app(\App\Services\Core\ImageService::class);
        foreach ($album->images as $image) {
            $imageService->delete($image);
        }

        $album->delete();

        // Clear OTP from Redis
        \Illuminate\Support\Facades\Cache::forget("album_delete_otp_{$album->id}");

        return redirect()->route('images.index')->with('success', 'تم حذف الألبوم وكافة محتوياته بنجاح.');
    }

    /**
     * Remove a collaborator from the album.
     */
    public function removeCollaborator(Album $album, User $user)
    {
        // Only owner can remove collaborators
        if (Auth::id() !== $album->user_id) {
            abort(403);
        }

        $album->collaborators()->detach($user->id);

        return back()->with('success', "تم إزالة المتعاون {$user->name} بنجاح.");
    }
}
