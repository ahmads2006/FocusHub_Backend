<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Message;
use App\Models\User;
use App\Notifications\AlbumInvitationNotification;
use App\Services\Core\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlbumController extends Controller
{
    /**
     * List the authenticated user's albums (owned + collaborative).
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $ownedAlbums = $user->ownedAlbums()
            ->withCount('images')
            ->latest()
            ->get();

        $sharedAlbums = $user->collaborativeAlbums()
            ->wherePivot('status', 'accepted')
            ->withCount('images')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'owned'  => $ownedAlbums,
                'shared' => $sharedAlbums,
            ],
        ]);
    }

    /**
     * Create a new album.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'   => 'required|string|max:255',
            'privacy' => 'nullable|in:public,private,hidden',
        ]);

        $album = Auth::user()->ownedAlbums()->create([
            'title'   => $validated['title'],
            'privacy' => $validated['privacy'] ?? 'public',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الألبوم بنجاح.',
            'data'    => $album,
        ], 201);
    }

    /**
     * Display an album with its images and collaborators.
     */
    public function show(Album $album): JsonResponse
    {
        // Authorization
        if ($album->privacy !== 'public'
            && Auth::id() !== $album->user_id
            && !$album->collaborators->contains(Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالوصول لهذا الألبوم.',
            ], 403);
        }

        $album->load(['images.user', 'images.storage', 'images.settings', 'collaborators']);

        return response()->json([
            'success' => true,
            'data'    => $album,
        ]);
    }

    /**
     * Update an album.
     */
    public function update(Request $request, Album $album): JsonResponse
    {
        $this->authorize('update', $album);

        $validated = $request->validate([
            'title'   => 'required|string|max:255',
            'privacy' => 'required|in:public,private,hidden',
        ]);

        $album->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الألبوم بنجاح.',
            'data'    => $album,
        ]);
    }

    /**
     * Request a deletion OTP via email.
     */
    public function requestDeleteOTP(Album $album): JsonResponse
    {
        $this->authorize('delete', $album);

        $code = rand(100000, 999999);
        Cache::put("album_delete_otp_{$album->id}", $code, now()->addMinutes(10));

        try {
            Mail::to(Auth::user()->email)->send(new \App\Mail\AlbumDeletionOTP($code, $album->title));
        } catch (\Exception $e) {
            Log::error("Failed to send album deletion OTP: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'فشل إرسال البريد الإلكتروني. يرجى المحاولة لاحقاً.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني.',
        ]);
    }

    /**
     * Delete an album after OTP verification.
     */
    public function destroy(Request $request, Album $album): JsonResponse
    {
        $this->authorize('delete', $album);

        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $otpCode = Cache::get("album_delete_otp_{$album->id}");

        if (!$otpCode || $otpCode != $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.',
            ], 422);
        }

        $imageService = app(ImageService::class);
        foreach ($album->images as $image) {
            $imageService->delete($image);
        }

        $album->delete();
        Cache::forget("album_delete_otp_{$album->id}");

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الألبوم وكافة محتوياته بنجاح.',
        ]);
    }

    /**
     * Add a collaborator to the album.
     */
    public function addCollaborator(Request $request, Album $album): JsonResponse
    {
        if (Auth::id() !== $album->user_id) {
            return response()->json(['success' => false, 'message' => 'غير مصرح.'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|string',
            'role'    => 'required|in:admin,contributor,viewer',
        ]);

        $userToAdd = User::find($validated['user_id']);
        if (!$userToAdd) {
            return response()->json(['success' => false, 'message' => 'لم يتم العثور على المستخدم.'], 404);
        }

        if ($userToAdd->id === $album->user_id) {
            return response()->json(['success' => false, 'message' => 'لا يمكنك إضافة نفسك كمتعاون.'], 400);
        }

        if ($album->collaborators->contains($userToAdd->id)) {
            return response()->json(['success' => false, 'message' => 'هذا المستخدم متعاون بالفعل.'], 409);
        }

        $album->collaborators()->attach($userToAdd->id, ['role' => $validated['role']]);
        $userToAdd->notify(new AlbumInvitationNotification($album, Auth::user()));

        Message::create([
            'sender_id'   => Auth::id(),
            'receiver_id' => $userToAdd->id,
            'album_id'    => $album->id,
            'body'        => "لقد قمت بدعوتك للانضمام إلى الألبوم المشترك: {$album->title}.",
        ]);

        return response()->json([
            'success' => true,
            'message' => "تم إضافة {$userToAdd->name} كمتعاون بنجاح.",
        ]);
    }

    /**
     * Update a collaborator's role.
     */
    public function updateCollaboratorRole(Request $request, Album $album, User $user): JsonResponse
    {
        if (Auth::id() !== $album->user_id) {
            return response()->json(['success' => false, 'message' => 'غير مصرح.'], 403);
        }

        $request->validate(['role' => 'required|in:admin,contributor,viewer']);

        if (!$album->collaborators->contains($user->id)) {
            return response()->json(['success' => false, 'message' => 'هذا المستخدم ليس متعاوناً.'], 404);
        }

        if ($album->user_id === $user->id) {
            return response()->json(['success' => false, 'message' => 'لا يمكن تغيير رتبة مالك الألبوم.'], 400);
        }

        $album->collaborators()->updateExistingPivot($user->id, ['role' => $request->role]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث رتبة المتعاون.',
        ]);
    }

    /**
     * Remove a collaborator from the album.
     */
    public function removeCollaborator(Album $album, User $user): JsonResponse
    {
        if (Auth::id() !== $album->user_id) {
            return response()->json(['success' => false, 'message' => 'غير مصرح.'], 403);
        }

        $album->collaborators()->detach($user->id);

        return response()->json([
            'success' => true,
            'message' => "تم إزالة المتعاون {$user->name} بنجاح.",
        ]);
    }

    /**
     * Accept a collaboration invitation.
     */
    public function acceptInvitation(Album $album): JsonResponse
    {
        $userId       = Auth::id();
        $collaborator = $album->collaborators()->where('user_id', $userId)->first();

        if (!$collaborator || $collaborator->pivot->status !== 'invited') {
            return response()->json(['success' => false, 'message' => 'لا توجد دعوة معلقة.'], 403);
        }

        $album->collaborators()->updateExistingPivot($userId, ['status' => 'accepted']);

        return response()->json([
            'success' => true,
            'message' => 'تم قبول الدعوة بنجاح.',
        ]);
    }

    /**
     * Decline a collaboration invitation.
     */
    public function declineInvitation(Album $album): JsonResponse
    {
        $userId       = Auth::id();
        $collaborator = $album->collaborators()->where('user_id', $userId)->first();

        if (!$collaborator || $collaborator->pivot->status !== 'invited') {
            return response()->json(['success' => false, 'message' => 'لا توجد دعوة معلقة.'], 403);
        }

        $album->collaborators()->detach($userId);

        return response()->json([
            'success' => true,
            'message' => 'تم رفض الدعوة.',
        ]);
    }
}
