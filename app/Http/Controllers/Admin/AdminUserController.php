<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BroadcastNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::withoutRole('admin')->get();

        $formattedUsers = $users->map(function ($user) {

            // $approvedStatus = 'pending';
            // if ($user->is_approved == 1) {
            //     $approvedStatus = 'approved';
            // } elseif ($user->is_approved == 2) {
            //     $approvedStatus = 'rejected';
            // }

            $bannedStatus = 'active';
            if ($user->is_banned == 1) {
                $bannedStatus = 'suspended';
            } elseif ($user->is_banned == 2) {
                $bannedStatus = 'permanently_banned';
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first() ?? 'buyer',
                'Status' => $bannedStatus,
                'phone_number' => $user->phone_number,
                'company_name' => $user->company_name,
                'position' => $user->position,
                'address' => $user->address,
                'avatar' => $user->avatar,
                'cover_photo' => $user->cover_photo,
                'bio' => $user->bio,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
        });

        return response()->json([
            'success' => 'true',
            'data' => $formattedUsers,
        ]);
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
            'success' => 'true',
            'message' => 'Update Successfully.',
            'data' => $user,
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);

        if ($user->id === Auth::id()) {
            return response()->json([
                'success' => 'false',
                'message' => 'Error! You cannot delete your own admin account.',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => 'true',
            'message' => 'User account deleted successfully.',
        ]);
    }

    // (Toggle Ban)
    public function toggleBan(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);

        if ($user->hasRole('admin')) {
            return response()->json([
                'success' => 'false',
                'message' => 'Error! Admin accounts cannot be banned!',
            ], 403);
        }

        $user->is_banned = ! $user->is_banned;
        $user->save();

        $status = $user->is_banned ? 'Active' : 'Inactive';

        return response()->json([
            'success' => 'true',
            'message' => "User has been successfully {$success}.",
            'data' => $user,
        ]);
    }

    // Dashboard
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
            'success' => 'true',
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
        ]);
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
            'success' => 'true',
            'message' => 'Broadcast notification sent successfully to all users.',
        ], 200);
    }

    // Approve User
    public function approveUser(Request $request)
    {

        $user = User::findOrFail($request->user_id);

        if ($user->is_approved) {
            return response()->json([
                'success' => 'false',
                'message' => 'This user account is already approved.',
            ], 400);
        }

        $user->is_approved = true;
        $user->save();

        return response()->json([
            'successf' => 'true',
            'message' => "User {$user->name} has been successfully approved and notified.",
            'data' => $user,
        ]);
    }

    public function approveSeller(Request $request)
    {
        $user = User::findOrFail($request->user_id);

        $user->syncRoles(['seller']);

        return response()->json([
            'success' => true,
            'message' => 'You approved this user',
        ]);
    }
}
