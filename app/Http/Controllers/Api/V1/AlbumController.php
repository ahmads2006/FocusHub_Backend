<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\AlbumInvitation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\AlbumInvitationNotification;
use App\Notifications\AlbumJoinRequestNotification;
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
            'message' => __('messages.album_created'),
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
                'message' => __('messages.album_unauthorized'),
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
            'message' => __('messages.album_updated'),
            'data'    => $album,
        ]);
    }

    /**
     * Request a deletion OTP via email.
     */
    public function requestDeleteOTP(Album $album): JsonResponse
    {
        $this->authorize('delete', $album);

        $code = random_int(100000, 999999);
        Cache::put("album_delete_otp_{$album->id}", $code, now()->addMinutes(10));

        try {
            Mail::to(Auth::user()->email)->queue(new \App\Mail\AlbumDeletionOTP($code, $album->title));
        } catch (\Exception $e) {
            Log::error("Failed to send album deletion OTP: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.email_failed'),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.verification_sent'),
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

        if (!$otpCode || !hash_equals((string) $otpCode, (string) $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => __('messages.invalid_verification'),
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
            'message' => __('messages.album_deleted'),
        ]);
    }

    /**
     * Add a collaborator to the album.
     */
    public function addCollaborator(Request $request, Album $album): JsonResponse
    {
        if (Auth::id() !== $album->user_id) {
            return response()->json(['success' => false, 'message' => __('messages.unauthorized')], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|string',
            'role'    => 'required|in:admin,contributor,viewer',
        ]);

        $userToAdd = User::find($validated['user_id']);
        if (!$userToAdd) {
            return response()->json(['success' => false, 'message' => __('messages.user_not_found')], 404);
        }

        if ($userToAdd->id === $album->user_id) {
            return response()->json(['success' => false, 'message' => __('messages.cannot_add_self_collaborator')], 400);
        }

        if ($album->collaborators->contains($userToAdd->id)) {
            return response()->json(['success' => false, 'message' => __('messages.already_collaborator')], 409);
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
            'message' => __('messages.collaborator_added', ['name' => $userToAdd->name]),
        ]);
    }

    /**
     * Update a collaborator's role.
     */
    public function updateCollaboratorRole(Request $request, Album $album, User $user): JsonResponse
    {
        if (Auth::id() !== $album->user_id) {
            return response()->json(['success' => false, 'message' => __('messages.unauthorized')], 403);
        }

        $request->validate(['role' => 'required|in:admin,contributor,viewer']);

        if (!$album->collaborators->contains($user->id)) {
            return response()->json(['success' => false, 'message' => __('messages.not_collaborator')], 404);
        }

        if ($album->user_id === $user->id) {
            return response()->json(['success' => false, 'message' => __('messages.cannot_change_owner_role')], 400);
        }

        $album->collaborators()->updateExistingPivot($user->id, ['role' => $request->role]);

        return response()->json([
            'success' => true,
            'message' => __('messages.collaborator_role_updated'),
        ]);
    }

    /**
     * Remove a collaborator from the album.
     */
    public function removeCollaborator(Album $album, User $user): JsonResponse
    {
        if (Auth::id() !== $album->user_id) {
            return response()->json(['success' => false, 'message' => __('messages.unauthorized')], 403);
        }

        $album->collaborators()->detach($user->id);

        return response()->json([
            'success' => true,
            'message' => __('messages.collaborator_removed', ['name' => $user->name]),
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
            return response()->json(['success' => false, 'message' => __('messages.no_pending_invite')], 403);
        }

        $album->collaborators()->updateExistingPivot($userId, ['status' => 'accepted']);

        // Auto-create or update group conversation when 3+ members
        $this->syncGroupConversation($album);

        return response()->json([
            'success' => true,
            'message' => __('messages.invite_accepted'),
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
            return response()->json(['success' => false, 'message' => __('messages.no_pending_invite')], 403);
        }

        $album->collaborators()->detach($userId);

        return response()->json([
            'success' => true,
            'message' => __('messages.invite_rejected'),
        ]);
    }

    /**
     * Get all active invitations for the album.
     */
    public function getInvitations(Album $album): JsonResponse
    {
        $this->authorize('update', $album);

        $invitations = $album->invitations()->latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $invitations->map(function ($inv) {
                return [
                    'id'   => $inv->id,
                    'code' => $inv->code,
                    'role' => $inv->role,
                    'uses' => $inv->uses,
                    'url'  => url("/join/{$inv->code}"),
                ];
            }),
        ]);
    }

    /**
     * Generate a new invitation link for a specific role.
     */
    public function generateInvitation(Request $request, Album $album): JsonResponse
    {
        $this->authorize('update', $album);

        $request->validate([
            'role' => 'required|in:admin,contributor,viewer',
        ]);

        $role = $request->role;

        // Generate a unique branded code with role suffix (e.g. opalshot_3Aj89o2f_viewer)
        do {
            $randomPart = \Illuminate\Support\Str::random(8);
            $code = "opalshot_{$randomPart}_{$role}";
        } while (AlbumInvitation::where('code', $code)->exists());

        $invitation = $album->invitations()->create([
            'code'       => $code,
            'role'       => $role,
            'max_uses'   => 1,
            'expires_at' => now()->addHour(), // ⏳ Expires in 1 hour for auto-rotation
        ]);

        return response()->json([
            'success' => true,
            'message' => __('messages.invite_link_created', ['role' => $role]),
            'data'    => [
                'code' => $invitation->code,
                'role' => $invitation->role,
                'url'  => url("/join/{$invitation->code}"),
            ],
        ]);
    }

    /**
     * Delete an invitation link.
     */
    public function deleteInvitation(AlbumInvitation $invitation): JsonResponse
    {
        $this->authorize('update', $invitation->album);

        $invitation->delete();

        return response()->json([
            'success' => true,
            'message' => __('messages.invite_link_deleted'),
        ]);
    }

    /**
     * Join an album using an invitation code.
     */
    public function joinByCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:64',
            'note' => 'nullable|string|max:500', // 📝 Optional intro note
        ]);

        $invitation = AlbumInvitation::where('code', $request->code)->first();

        if (!$invitation || !$invitation->isValid()) {
            return response()->json(['success' => false, 'message' => __('messages.invalid_invite_link')], 404);
        }

        $album = $invitation->album;
        $user = Auth::user();

        if ($album->user_id === $user->id) {
            return response()->json(['success' => false, 'message' => __('messages.already_owner')], 400);
        }

        if ($album->collaborators->contains($user->id)) {
            return response()->json(['success' => false, 'message' => __('messages.already_member')], 409);
        }

        // Add user with the role defined in the invitation, but as PENDING
        $album->collaborators()->attach($user->id, [
            'role'      => $invitation->role,
            'status'    => 'pending', // ⏳ Requires approval
            'join_note' => $request->note, // 📝 Save the intro note
        ]);

        // Increment usage count
        $invitation->increment('uses');

        // 🔔 Trigger Notification for Owner and Admins
        $notifiableUsers = collect([$album->owner])->concat(
            $album->collaborators()
                ->wherePivot('role', 'admin')
                ->wherePivot('status', 'accepted')
                ->get()
        )->unique('id');

        foreach ($notifiableUsers as $notifiable) {
            $notifiable->notify(new AlbumJoinRequestNotification($album, $user, $invitation->role));
        }

        // 🔄 AUTO-ROTATE: If it was a single-use link, generate a new replacement link automatically
        if ($invitation->max_uses === 1) {
            do {
                $newRandomPart = \Illuminate\Support\Str::random(8);
                $newCode = "opalshot_{$newRandomPart}_{$invitation->role}";
            } while (AlbumInvitation::where('code', $newCode)->exists());

            $album->invitations()->create([
                'code'       => $newCode,
                'role'       => $invitation->role,
                'max_uses'   => 1,
                'expires_at' => now()->addHour(),
            ]);

            // Delete the used link to keep DB clean
            $invitation->delete();
        }

        // Sync group conversation if applicable
        $this->syncGroupConversation($album);

        return response()->json([
            'success' => true,
            'message' => __('messages.join_request_sent'),
            'data'    => $album->load('owner'),
        ]);
    }

    /**
     * Approve a pending collaborator join request.
     */
    public function approveCollaborator(Request $request, Album $album, User $user): JsonResponse
    {
        $currentUser = Auth::user();
        $isOwner = $album->user_id === $currentUser->id;

        // Get the pending collaborator data
        $collaborator = $album->collaborators()->where('user_id', $user->id)->first();

        if (!$collaborator || $collaborator->pivot->status !== 'pending') {
            return response()->json(['success' => false, 'message' => __('messages.no_join_request')], 404);
        }

        $pendingRole = $collaborator->pivot->role;

        // Security Rules:
        // 1. If role is 'admin', only Owner can approve.
        if ($pendingRole === 'admin') {
            if (!$isOwner) {
                return response()->json(['success' => false, 'message' => __('messages.only_owner_can_accept_managers')], 403);
            }
        } else {
            // 2. Otherwise, Owner OR any existing Admin can approve.
            $isAdmin = $album->collaborators()
                ->where('user_id', $currentUser->id)
                ->wherePivot('role', 'admin')
                ->wherePivot('status', 'accepted')
                ->exists();

            if (!$isOwner && !$isAdmin) {
                return response()->json(['success' => false, 'message' => __('messages.cannot_accept_join_requests')], 403);
            }
        }

        // Approve
        $album->collaborators()->updateExistingPivot($user->id, [
            'status' => 'accepted',
        ]);

        // Sync group conversation
        $this->syncGroupConversation($album);

        return response()->json([
            'success' => true,
            'message' => __('messages.join_accepted', ['name' => $user->name, 'role' => $pendingRole]),
        ]);
    }

    // ── Private Helpers ──────────────────────────────────────

    /**
     * Auto-create a group conversation when a collaborative album reaches 3+ accepted members.
     * If the group already exists, add the new member to it.
     */
    private function syncGroupConversation(Album $album): void
    {
        // Count: owner + accepted collaborators
        $acceptedCollaborators = $album->collaborators()->wherePivot('status', 'accepted')->get();
        $totalMembers = 1 + $acceptedCollaborators->count(); // 1 = owner

        if ($totalMembers < 3) {
            return; // Not enough members yet
        }

        $conversation = $album->groupConversation;

        if (!$conversation) {
            // Create the group conversation
            $conversation = \App\Models\Conversation::create([
                'type'            => 'group',
                'name'            => '📁 ' . $album->title,
                'is_name_custom'  => false,
                'album_id'        => $album->id,
                'created_by'      => $album->user_id,
                'last_message_at' => now(),
            ]);

            // Add owner as group owner
            $conversation->participants()->attach($album->user_id, [
                'role'      => 'owner',
                'joined_at' => now(),
            ]);

            // Add all accepted collaborators
            foreach ($acceptedCollaborators as $collaborator) {
                $conversation->participants()->attach($collaborator->id, [
                    'role'      => 'member',
                    'joined_at' => now(),
                ]);
            }

            // System message
            Message::create([
                'sender_id'       => $album->user_id,
                'receiver_id'     => $album->user_id,
                'conversation_id' => $conversation->id,
                'body'            => "📣 تم إنشاء مجموعة الدردشة تلقائيًا للألبوم التعاوني: {$album->title}",
            ]);
        } else {
            // Group exists — just add any missing accepted collaborators
            foreach ($acceptedCollaborators as $collaborator) {
                if (!$conversation->hasParticipant($collaborator->id)) {
                    $conversation->participants()->attach($collaborator->id, [
                        'role'      => 'member',
                        'joined_at' => now(),
                    ]);

                    Message::create([
                        'sender_id'       => $album->user_id,
                        'receiver_id'     => $album->user_id,
                        'conversation_id' => $conversation->id,
                        'body'            => "📥 انضم {$collaborator->name} للمجموعة.",
                    ]);

                    $conversation->update(['last_message_at' => now()]);
                }
            }
        }
    }
}

