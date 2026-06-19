<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        $notifications = $user->notifications;
        $formatted = $notifications->map(function ($n) {
            $data = is_array($n->data) ? $n->data : json_decode($n->data, true);

            return [
                'id' => $n->id,
                'message' => $data['message'] ?? ($data['title'] ?? 'New Notification'),
                'time' => $n->created_at->diffForHumans(),
            ];
        });
        // dd($formatted);

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'data' => $formatted,
        ]);

    }

    public function markAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => 'true',
            'message' => 'All notifications marked as read',
        ]);
    }
}
