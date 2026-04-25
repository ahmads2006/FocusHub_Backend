<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\SupportRequestNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class SupportController extends Controller
{
    // ═══════════════════════════════════════════
    // USER ENDPOINTS
    // ═══════════════════════════════════════════

    /**
     * Get or create a support conversation for the current user.
     */
    public function getOrCreateConversation(): JsonResponse
    {
        $userId = Auth::id();

        $conversation = SupportConversation::forUser($userId)
            ->whereIn('status', ['pending', 'active'])
            ->latest()
            ->first();

        if (!$conversation) {
            $conversation = SupportConversation::create([
                'user_id' => $userId,
                'status'  => 'pending',
            ]);
        }

        $messages = $conversation->messages()
            ->with('sender:id')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($msg) => $this->formatMessage($msg, $userId));

        return response()->json([
            'success' => true,
            'data'    => [
                'conversation_id' => $conversation->id,
                'status'          => $conversation->status,
                'messages'        => $messages,
            ],
        ]);
    }

    /**
     * Send a message in a support conversation.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|uuid|exists:support_conversations,id',
            'body'            => 'required|string|max:2000',
        ]);

        $userId       = Auth::id();
        $conversation = SupportConversation::findOrFail($request->conversation_id);

        if ($conversation->user_id !== $userId) {
            return response()->json(['success' => false, 'message' => __('messages.unauthorized')], 403);
        }

        // Reopen if closed
        if ($conversation->status === 'closed') {
            $conversation->update(['status' => 'pending', 'admin_id' => null]);
        }

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $userId,
            'body'            => $request->body,
        ]);

        // Auto-reply + admin notification on first real message
        if (!$conversation->isClaimed()) {
            $hasAutoReply = $conversation->messages()
                ->where('is_system', true)
                ->where('body', 'like', '%شكراً لتواصلك%')
                ->exists();

            if (!$hasAutoReply) {
                SupportMessage::create([
                    'conversation_id' => $conversation->id,
                    'sender_id'       => null,
                    'body'            => 'شكراً لتواصلك مع دعم OpticVault! سيتم الرد عليك قريباً من فريق الدعم. 💬',
                    'is_system'       => true,
                ]);

                $user   = Auth::user();
                $admins = User::where('role', 'admin')
                    ->orWhere('role', 'super_admin')
                    ->where('id', '!=', $userId)
                    ->get();

                if ($admins->isNotEmpty()) {
                    Notification::send($admins, new SupportRequestNotification($user));
                }
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatMessage($message, $userId),
        ], 201);
    }

    /**
     * Poll for new messages (user side).
     */
    public function pollMessages(SupportConversation $conversation, Request $request): JsonResponse
    {
        $userId = Auth::id();
        if ($conversation->user_id !== $userId) {
            return response()->json(['success' => false, 'message' => __('messages.unauthorized')], 403);
        }

        $afterId     = $request->query('after_id', 0);
        $newMessages = $conversation->messages()
            ->where('id', '>', $afterId)
            ->with('sender:id')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($msg) => $this->formatMessage($msg, $userId));

        return response()->json([
            'success' => true,
            'data'    => [
                'messages' => $newMessages,
                'status'   => $conversation->fresh()->status,
            ],
        ]);
    }

    // ═══════════════════════════════════════════
    // ADMIN ENDPOINTS
    // ═══════════════════════════════════════════

    /**
     * Get pending + active conversation counts.
     */
    public function pendingCount(): JsonResponse
    {
        $count        = SupportConversation::pending()->count();
        $myActiveCount = SupportConversation::active()
            ->forAdmin(Auth::id())
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'pending_count'   => $count,
                'my_active_count' => $myActiveCount,
            ],
        ]);
    }

    /**
     * List pending + my active conversations.
     */
    public function pendingConversations(): JsonResponse
    {
        $adminId = Auth::id();

        $pending = SupportConversation::pending()
            ->with(['user:id', 'user.profile:id,user_id,name,profile_picture'])
            ->withCount('messages')
            ->latest()
            ->get()
            ->map(fn(SupportConversation $c) => [
                'id'             => $c->id,
                'user_name'      => $c->user->name,
                'user_avatar'    => $c->user->avatar,
                'messages_count' => $c->messages_count,
                'status'         => 'pending',
                'created_at'     => $c->created_at->diffForHumans(),
                'last_message'   => $c->messages()->latest()->first()?->body ?? '',
            ]);

        $myActive = SupportConversation::active()
            ->forAdmin($adminId)
            ->with(['user:id', 'user.profile:id,user_id,name,profile_picture'])
            ->withCount('messages')
            ->latest('updated_at')
            ->get()
            ->map(fn(SupportConversation $c) => [
                'id'             => $c->id,
                'user_name'      => $c->user->name,
                'user_avatar'    => $c->user->avatar,
                'messages_count' => $c->messages_count,
                'status'         => 'active',
                'created_at'     => $c->created_at->diffForHumans(),
                'last_message'   => $c->messages()->latest()->first()?->body ?? '',
            ]);

        return response()->json([
            'success' => true,
            'data'    => ['pending' => $pending, 'active' => $myActive],
        ]);
    }

    /**
     * Claim a support conversation.
     */
    public function claimConversation(SupportConversation $conversation): JsonResponse
    {
        if ($conversation->isClaimed() && !$conversation->isClaimedBy(Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => __('messages.chat_reserved'),
            ], 409);
        }

        $conversation->update([
            'admin_id' => Auth::id(),
            'status'   => 'active',
        ]);

        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => null,
            'body'            => 'تم قبول محادثتك! ' . Auth::user()->name . ' من فريق الدعم سيساعدك الآن. 🎯',
            'is_system'       => true,
        ]);

        return response()->json(['success' => true, 'message' => __('messages.chat_accepted')]);
    }

    /**
     * Get messages for a claimed conversation (admin).
     */
    public function adminMessages(SupportConversation $conversation): JsonResponse
    {
        $adminId = Auth::id();
        if ($conversation->admin_id !== $adminId) {
            return response()->json(['success' => false, 'message' => __('messages.not_your_chat')], 403);
        }

        $messages = $conversation->messages()
            ->with('sender:id')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($msg) => $this->formatMessage($msg, $adminId));

        return response()->json([
            'success' => true,
            'data'    => [
                'messages' => $messages,
                'user'     => [
                    'id'     => $conversation->user->id,
                    'name'   => $conversation->user->name,
                    'avatar' => $conversation->user->avatar,
                ],
            ],
        ]);
    }

    /**
     * Send a reply in a claimed conversation (admin).
     */
    public function adminSend(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|uuid|exists:support_conversations,id',
            'body'            => 'required|string|max:2000',
        ]);

        $adminId      = Auth::id();
        $conversation = SupportConversation::findOrFail($request->conversation_id);

        if ($conversation->admin_id !== $adminId) {
            return response()->json(['success' => false, 'message' => __('messages.not_your_chat')], 403);
        }

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $adminId,
            'body'            => $request->body,
        ]);

        $conversation->touch();

        return response()->json([
            'success' => true,
            'data'    => $this->formatMessage($message, $adminId),
        ], 201);
    }

    /**
     * Poll for new messages (admin side).
     */
    public function adminPoll(SupportConversation $conversation, Request $request): JsonResponse
    {
        $adminId = Auth::id();
        if ($conversation->admin_id !== $adminId) {
            return response()->json(['success' => false, 'message' => __('messages.not_your_chat')], 403);
        }

        $afterId     = $request->query('after_id', 0);
        $newMessages = $conversation->messages()
            ->where('id', '>', $afterId)
            ->with('sender:id')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($msg) => $this->formatMessage($msg, $adminId));

        return response()->json([
            'success' => true,
            'data'    => ['messages' => $newMessages],
        ]);
    }

    /**
     * Close a conversation (admin).
     */
    public function closeConversation(SupportConversation $conversation): JsonResponse
    {
        $adminId = Auth::id();
        if ($conversation->admin_id !== $adminId) {
            return response()->json(['success' => false, 'message' => __('messages.not_your_chat')], 403);
        }

        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => null,
            'body'            => 'تم إغلاق هذه المحادثة. شكراً لتواصلك مع دعم OpticVault! 🙏',
            'is_system'       => true,
        ]);

        $conversation->update(['status' => 'closed']);

        return response()->json(['success' => true, 'message' => __('messages.chat_closed')]);
    }

    // ═══════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════

    private function formatMessage(SupportMessage $msg, string $viewerId): array
    {
        return [
            'id'        => $msg->id,
            'body'      => $msg->body,
            'is_mine'   => $msg->sender_id === $viewerId,
            'is_system' => $msg->is_system,
            'is_faq'    => $msg->is_faq,
            'sender_id' => $msg->sender_id,
            'created_at' => $msg->created_at->format('H:i'),
            'date'       => $msg->created_at->format('Y-m-d'),
        ];
    }
}
