<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notifikasi;
use App\Events\NotificationSent;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->get('per_page', 15);
            
            $notifications = Notifikasi::where('id_user', $user->id_user)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
            
            return response()->json([
                'status' => 'success',
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching notifications: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch notifications'
            ], 500);
        }
    }

    /**
     * Get unread notifications count
     */
    public function getUnreadCount()
    {
        try {
            $user = Auth::user();
            $unreadCount = Notifikasi::where('id_user', $user->id_user)
                ->unread()
                ->count();
            
            return response()->json([
                'status' => 'success',
                'data' => ['unread_count' => $unreadCount]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching unread count: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch unread count'
            ], 500);
        }
    }

    /**
     * Get recent unread notifications for popup display
     */
    public function getRecentUnread()
    {
        try {
            $user = Auth::user();
            $notifications = Notifikasi::where('id_user', $user->id_user)
                ->unread()
                ->recent(7) // Last 7 days
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching recent notifications: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch recent notifications'
            ], 500);
        }
    }

    /**
     * Mark a specific notification as read
     */
    public function markAsRead($id)
    {
        try {
            $user = Auth::user();
            $notification = Notifikasi::where('id_notifikasi', $id)
                ->where('id_user', $user->id_user)
                ->first();
            
            if (!$notification) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notification not found'
                ], 404);
            }
            
            $notification->markAsRead();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Notification marked as read'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error marking notification as read: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark notification as read'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        try {
            $user = Auth::user();
            Notifikasi::where('id_user', $user->id_user)
                ->unread()
                ->update(['is_read' => true]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'All notifications marked as read'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error marking all notifications as read: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark all notifications as read'
            ], 500);
        }
    }

    /**
     * Create and broadcast a notification (for testing or manual creation)
     */
    public static function createAndBroadcast($userId, $type, $message, $data = null, $actionUrl = null)
    {
        try {
            \Log::info('🔔 Creating notification', [
                'user_id' => $userId,
                'type' => $type,
                'message' => $message
            ]);
            
            $notification = Notifikasi::createForUser($userId, $type, $message, $data, $actionUrl);
            
            \Log::info('✅ Notification created', [
                'notification_id' => $notification->id_notifikasi
            ]);
            
            // Broadcast the notification in real-time
            broadcast(new \App\Events\NotificationSent($notification));
            
            \Log::info('📡 Notification broadcasted', [
                'notification_id' => $notification->id_notifikasi,
                'user_id' => $userId,
                'type' => $type
            ]);
            
            return $notification;
        } catch (\Exception $e) {
            \Log::error('❌ Error creating notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
