<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    //
    public function  index(Request $request){
        return response()->json([
            'success' => 'true',
            'notifications' => $request->user()->unreadNotifications
        ],201);
    }

    public function markAsRead(Request $request){
        $request->user()->unreadNotifications->markAsRead();
        return response()->json([
            'success' => 'true',
            'message' => 'All notifications marked as read'
        ]);
    }
}
