<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\SupportRequestNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SupportChatController extends Controller
{
    /**
     * المستخدم: الحصول على المحادثة الحالية أو إنشاء واحدة جديدة.
     */
    public function getOrCreateConversation(): JsonResponse
    {
        $userId = Auth::id();

        // ابحث عن محادثة قائمة (pending أو active) لهذا المستخدم
        $conversation = SupportConversation::forUser($userId)
            ->whereIn('status', ['pending', 'active'])
            ->latest()
            ->first();

        if (!$conversation) {
            // أنشئ محادثة جديدة
            $conversation = SupportConversation::create([
                'user_id' => $userId,
                'status' => 'pending',
            ]);
        }

        $messages = $conversation->messages()
            ->with('sender:id')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($msg) => $this->formatMessage($msg, $userId));

        return response()->json([
            'conversation_id' => $conversation->id,
            'status' => $conversation->status,
            'messages' => $messages,
        ]);
    }

    /**
     * المستخدم: إرسال رسالة في محادثة الدعم.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|uuid|exists:support_conversations,id',
            'body' => 'required|string|max:2000',
        ]);

        $userId = Auth::id();
        $conversation = SupportConversation::findOrFail($request->conversation_id);

        // تأكد أن المستخدم هو صاحب المحادثة
        if ($conversation->user_id !== $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // إذا كانت المحادثة مغلقة، أعد فتحها
        if ($conversation->status === 'closed') {
            $conversation->update(['status' => 'pending', 'admin_id' => null]);
        }

        // أنشئ رسالة المستخدم
        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userId,
            'body' => $request->body,
        ]);

        // إذا لم يكن هناك أدمن بعد → رسالة تلقائية + إشعار
        if (!$conversation->isClaimed()) {
            // هل هذه أول رسالة حقيقية من المستخدم؟
            $hasAutoReply = $conversation->messages()
                ->where('is_system', true)
                ->where('body', 'like', '%شكراً لتواصلك%')
                ->exists();

            if (!$hasAutoReply) {
                // رسالة تلقائية
                SupportMessage::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => null,
                    'body' => 'شكراً لتواصلك مع دعم OpticVault! سيتم الرد عليك قريباً من فريق الدعم. 💬',
                    'is_system' => true,
                ]);

                // إشعار لجميع الأدمنز
                $user = Auth::user();
                $admins = User::where('role', 'admin')
                    ->orWhere('role', 'super_admin')
                    ->where('id', '!=', $userId)
                    ->get();

                if ($admins->isNotEmpty()) {
                    Notification::send($admins, new SupportRequestNotification($user));
                }
            }
        }

        return response()->json($this->formatMessage($message, $userId), 201);
    }

    /**
     * المستخدم: Polling للرسائل الجديدة.
     */
    public function pollMessages(SupportConversation $conversation, Request $request): JsonResponse
    {
        $userId = Auth::id();
        if ($conversation->user_id !== $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $afterId = $request->query('after_id', 0);

        $newMessages = $conversation->messages()
            ->where('id', '>', $afterId)
            ->with('sender:id')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($msg) => $this->formatMessage($msg, $userId));

        return response()->json([
            'messages' => $newMessages,
            'status' => $conversation->fresh()->status,
        ]);
    }

    // ═══════════════════════════════════════════
    // ADMIN ENDPOINTS
    // ═══════════════════════════════════════════

    /**
     * الأدمن: عدد المحادثات المعلقة (للعرض في الـ badge).
     */
    public function pendingCount(): JsonResponse
    {
        $count = SupportConversation::pending()->count();
        // أيضاً المحادثات المسندة لهذا الأدمن
        $myActiveCount = SupportConversation::active()
            ->forAdmin(Auth::id())
            ->count();

        return response()->json([
            'pending_count' => $count,
            'my_active_count' => $myActiveCount,
        ]);
    }

    /**
     * الأدمن: قائمة المحادثات المعلقة + المسندة لي.
     */
    public function pendingConversations(): JsonResponse
    {
        $adminId = Auth::id();

        // المحادثات المعلقة (يمكن لأي أدمن قبولها)
        $pending = SupportConversation::pending()
            ->with(['user:id', 'user.profile:id,user_id,name,profile_picture'])
            ->withCount('messages')
            ->latest()
            ->get()
            ->map(fn(SupportConversation $c) => [
                'id' => $c->id,
                'user_name' => $c->user->name,
                'user_avatar' => $c->user->avatar,
                'messages_count' => $c->messages_count,
                'status' => 'pending',
                'created_at' => $c->created_at->diffForHumans(),
                'last_message' => $c->messages()->latest()->first()?->body ?? '',
            ]);

        // المحادثات المسندة لهذا الأدمن
        $myActive = SupportConversation::active()
            ->forAdmin($adminId)
            ->with(['user:id', 'user.profile:id,user_id,name,profile_picture'])
            ->withCount('messages')
            ->latest('updated_at')
            ->get()
            ->map(fn(SupportConversation $c) => [
                'id' => $c->id,
                'user_name' => $c->user->name,
                'user_avatar' => $c->user->avatar,
                'messages_count' => $c->messages_count,
                'status' => 'active',
                'created_at' => $c->created_at->diffForHumans(),
                'last_message' => $c->messages()->latest()->first()?->body ?? '',
            ]);

        return response()->json([
            'pending' => $pending,
            'active' => $myActive,
        ]);
    }

    /**
     * الأدمن: قبول محادثة (Claim).
     */
    public function claimConversation(SupportConversation $conversation): JsonResponse
    {
        // لا يمكن قبول محادثة محجوزة لأدمن آخر
        if ($conversation->isClaimed() && !$conversation->isClaimedBy(Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => 'هذه المحادثة محجوزة لأدمن آخر بالفعل.',
            ], 409);
        }

        $conversation->update([
            'admin_id' => Auth::id(),
            'status' => 'active',
        ]);

        // رسالة نظام: الأدمن انضم
        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => null,
            'body' => 'تم قبول محادثتك! ' . Auth::user()->name . ' من فريق الدعم سيساعدك الآن. 🎯',
            'is_system' => true,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * الأدمن: جلب رسائل محادثة.
     */
    public function adminMessages(SupportConversation $conversation): JsonResponse
    {
        $adminId = Auth::id();

        // يجب أن تكون محجوزة لهذا الأدمن
        if ($conversation->admin_id !== $adminId) {
            return response()->json(['error' => 'Not your conversation'], 403);
        }

        $messages = $conversation->messages()
            ->with('sender:id')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($msg) => $this->formatMessage($msg, $adminId));

        return response()->json([
            'messages' => $messages,
            'user' => [
                'id' => $conversation->user->id,
                'name' => $conversation->user->name,
                'avatar' => $conversation->user->avatar,
            ],
        ]);
    }

    /**
     * الأدمن: إرسال رد.
     */
    public function adminSend(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|uuid|exists:support_conversations,id',
            'body' => 'required|string|max:2000',
        ]);

        $adminId = Auth::id();
        $conversation = SupportConversation::findOrFail($request->conversation_id);

        if ($conversation->admin_id !== $adminId) {
            return response()->json(['error' => 'Not your conversation'], 403);
        }

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $adminId,
            'body' => $request->body,
        ]);

        $conversation->touch(); // Update updated_at

        return response()->json($this->formatMessage($message, $adminId), 201);
    }

    /**
     * الأدمن: Polling للرسائل الجديدة.
     */
    public function adminPoll(SupportConversation $conversation, Request $request): JsonResponse
    {
        $adminId = Auth::id();
        if ($conversation->admin_id !== $adminId) {
            return response()->json(['error' => 'Not your conversation'], 403);
        }

        $afterId = $request->query('after_id', 0);

        $newMessages = $conversation->messages()
            ->where('id', '>', $afterId)
            ->with('sender:id')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($msg) => $this->formatMessage($msg, $adminId));

        return response()->json(['messages' => $newMessages]);
    }

    /**
     * الأدمن: إغلاق محادثة.
     */
    public function closeConversation(SupportConversation $conversation): JsonResponse
    {
        $adminId = Auth::id();
        if ($conversation->admin_id !== $adminId) {
            return response()->json(['error' => 'Not your conversation'], 403);
        }

        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => null,
            'body' => 'تم إغلاق هذه المحادثة. شكراً لتواصلك مع دعم OpticVault! 🙏',
            'is_system' => true,
        ]);

        $conversation->update(['status' => 'closed']);

        return response()->json(['success' => true]);
    }

    // ═══════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════

    private function formatMessage(SupportMessage $msg, string $viewerId): array
    {
        return [
            'id' => $msg->id,
            'body' => $msg->body,
            'is_mine' => $msg->sender_id === $viewerId,
            'is_system' => $msg->is_system,
            'is_faq' => $msg->is_faq,
            'sender_id' => $msg->sender_id,
            'created_at' => $msg->created_at->format('H:i'),
            'date' => $msg->created_at->format('Y-m-d'),
        ];
    }
}
