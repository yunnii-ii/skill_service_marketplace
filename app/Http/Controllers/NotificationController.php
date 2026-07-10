<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->get()
            ->map(fn ($notification) => $this->formatNotification($notification));

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'role' => $user->getRoleNames()->first(),
            'unread_count' => $user->unreadNotifications()->count(),
            'data' => $notifications,
        ]);

    }

    public function unread(Request $request)
    {
        $notifications = $request->user()
            ->unreadNotifications()
            ->latest()
            ->get()
            ->map(fn ($notification) => $this->formatNotification($notification));

        return response()->json([
            'success' => true,
            'unread_count' => $notifications->count(),
            'data' => $notifications,
        ]);
    }

    public function markAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
    }

    public function markOneAsRead(Request $request)
    {
        $request->validate([
            'notification_id' => 'required|uuid',
        ]);

        $notification = $request->user()
            ->notifications()
            ->where('id', $request->notification_id)
            ->first();

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'notification_id' => 'required|uuid',
        ]);

        $deleted = $request->user()
            ->notifications()
            ->where('id', $request->notification_id)
            ->delete();

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully',
        ]);
    }

    private function formatNotification($notification): array
    {
        $data = is_array($notification->data)
            ? $notification->data
            : json_decode($notification->data, true);

        return [
            'id' => $notification->id,
            'message' => $data['message'] ?? ($data['title'] ?? 'New Notification'),
            'type' => $data['type'] ?? null,
            'data' => $data['data'] ?? null,
            'is_read' => ! is_null($notification->read_at),
            'read_at' => $notification->read_at,
            'time' => $notification->created_at->diffForHumans(),
            'created_at' => $notification->created_at,
        ];
    }
}
