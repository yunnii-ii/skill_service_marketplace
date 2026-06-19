<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    //
    public function store(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string',
        ]);
        $chat = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'message' => $request->message,
        ]);
        $receiver = User::find($request->receiver_id);
        if ($receiver) {
            $notiMessage = Auth::user()->name.' sent you a message';
            $receiver->notify(new AppNotification($notiMessage));
        }

        return response()->json([
            'success' => 'true',
            'message' => 'Message sent',
            'data' => $chat,
        ]);
    }

    public function getMessages(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $myId = Auth::id();
        $userId = $request->user_id;

        $chats = Message::where(function ($query) use ($myId, $userId) {
            $query->where('sender_id', $myId)
                ->where('receiver_id', $userId);
        })
            ->orWhere(function ($query) use ($myId, $userId) {
                $query->where('sender_id', $userId)
                    ->where('receiver_id', $myId);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => 'true',
            'message' => $chats,
        ], 201);
    }
}
