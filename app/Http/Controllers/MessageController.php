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

        Message::where('sender_id', $userId)
            ->where('receiver_id', $myId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'data' => $chats,
        ]);
    }

    public function conversations()
    {
        $myId = Auth::id();

        $messages = Message::with(['sender', 'receiver'])
            ->where('sender_id', $myId)
            ->orWhere('receiver_id', $myId)
            ->latest()
            ->get();

        $conversations = $messages
            ->groupBy(fn ($message) => $message->sender_id === $myId ? $message->receiver_id : $message->sender_id)
            ->map(function ($messages, $userId) use ($myId) {
                $lastMessage = $messages->first();
                $user = $lastMessage->sender_id === $myId ? $lastMessage->receiver : $lastMessage->sender;
                $unreadCount = Message::where('sender_id', $userId)
                    ->where('receiver_id', $myId)
                    ->where('is_read', false)
                    ->count();

                return [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => $user->avatar,
                        'avatar_url' => $user->avatar ? asset('storage/'.$user->avatar) : null,
                    ],
                    'last_message' => [
                        'id' => $lastMessage->id,
                        'message' => $lastMessage->message,
                        'is_read' => (bool) $lastMessage->is_read,
                        'created_at' => $lastMessage->created_at,
                    ],
                    'unread_count' => $unreadCount,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    public function unread()
    {
        $myId = Auth::id();

        $unreadMessages = Message::with('sender')
            ->where('receiver_id', $myId)
            ->where('is_read', false)
            ->latest()
            ->get();

        $senders = $unreadMessages
            ->groupBy('sender_id')
            ->map(function ($messages) {
                $sender = $messages->first()->sender;

                return [
                    'user' => [
                        'id' => $sender->id,
                        'name' => $sender->name,
                        'email' => $sender->email,
                        'avatar' => $sender->avatar,
                        'avatar_url' => $sender->avatar ? asset('storage/'.$sender->avatar) : null,
                    ],
                    'unread_count' => $messages->count(),
                    'last_message' => [
                        'id' => $messages->first()->id,
                        'message' => $messages->first()->message,
                        'created_at' => $messages->first()->created_at,
                    ],
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'unread_count' => $unreadMessages->count(),
            'data' => $senders,
        ]);
    }

    public function markAsRead(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        Message::where('sender_id', $request->user_id)
            ->where('receiver_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Messages marked as read.',
        ]);
    }
}
