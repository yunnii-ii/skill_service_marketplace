<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BroadcastNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::withoutRole('admin')->get();

        return response()->json($users);
    }

    public function update(Request $request)
    {
        $rules = [
            'user_id' => 'required|exists:users,id',
            'name' => 'sometimes|string|max:255',
            'role' => 'sometimes|string',
        ];

        if ($request->has('email')) {
            $rules['email'] = ['required', 'email', Rule::unique('users', 'email')->ignore($request->user_id)];
        }

        $request->validate($rules);

        $user = User::findOrFail($request->user_id);

        $updateData = array_filter($request->only('name', 'email', 'role'), fn ($value) => ! is_null($value));

        $user->update($updateData);

        return response()->json([
            'message' => 'Update Successfully.',
            'user' => $user,
        ], 200);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
        $user = User::findOrFail($request->user_id);
        $user->delete();

        return response()->json([
            'message' => 'User account deleted successfully.',
        ], 201);
    }

    public function toggleBan(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);

        if ($user->hasRole('admin')) {
            return response()->json([
                'message' => 'Admin accounts cannot be banned!',
            ], 403);
        }
        $user->is_banned = ! $user->is_banned;
        $user->save();

        $status = $user->is_banned ? 'banned' : 'unbanned';

        return response()->json([
            'message' => "User has been successfully {$status}.",
            'user' => $user,
        ], 201);
    }

    public function getDashboardStats()
    {
        $totalUsers = User::count();
        $totalCategories = Category::count();
        $bannedUsers = User::where('is_banned', true)->count();
        $totalServices = Service::count();
        $totalBookings = Booking::count();

        $pendingBookings = Booking::where('status', 'pending')->count();
        $completedBookings = Booking::where('status', 'completed')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'users' => [
                    'total' => $totalUsers,
                    'banned' => $bannedUsers,
                    'active' => $totalUsers - $bannedUsers,
                ],
                'categories' => [
                    'total' => $totalCategories,
                ],

                'services' => [
                    'total' => $totalServices,
                ],
                'bookings' => [
                    'total' => $totalBookings,
                    'pending' => $pendingBookings,
                    'completed' => $completedBookings,
                ],
            ],
        ], 201);
    }

    public function broadcastMessage(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $users = User::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();

        Notification::send($users, new BroadcastNotification($request->title, $request->message));

        return response()->json([
            'success' => true,
            'message' => 'Broadcast notification sent successfully to all users.',
        ], 201);
    }
}
