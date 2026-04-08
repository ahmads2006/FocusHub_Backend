<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $user          = Auth::user();
        $notifications = $user->notifications()->latest()->take(30)->get();
        $unreadCount   = $user->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'notifications' => $notifications,
                'unread_count'  => $unreadCount,
            ],
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'تم تعليم الإشعار كمقروء.',
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'تم تعليم جميع الإشعارات كمقروءة.',
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الإشعار.',
        ]);
    }
}
