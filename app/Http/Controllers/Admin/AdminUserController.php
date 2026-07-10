<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BroadcastNotification;
use App\Notifications\SellerRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    private const USER_STATUSES = [
        0 => 'Active',
        1 => 'Inactive',
        2 => 'Suspended',
    ];

    private const SELLER_REQUEST_STATUSES = [
        0 => 'Pending',
        1 => 'Approved',
        2 => 'Rejected',
    ];

    // all user lists
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => $this->formattedUsers(),
        ]);
    }


    public function show(Request $request, ?int $userId = null)
    {
        $request->merge([
            'user_id' => $userId ?? $request->query('user_id'),
        ]);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::with(['roles', 'sentMessage', 'receivedMessage'])
            ->withoutRole('admin')
            ->findOrFail($validated['user_id']);

        return response()->json([
            'success' => true,
            'data' => $this->formatUserDetail($user),
        ]);
    }

    // user update
    public function update(Request $request)
    {
        $rules = [
            'user_id' => 'required|exists:users,id',
            'name' => 'sometimes|string|max:255',
            'role' => 'sometimes|string|in:buyer,seller',
            'status' => 'sometimes|string|in:Active,Inactive,Suspended,active,inactive,suspended',
            'phone_number' => 'sometimes|nullable|string|max:255',
            'company_name' => 'sometimes|nullable|string|max:255',
            'position' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string',
            'bio' => 'sometimes|nullable|string',
        ];

        if ($request->has('email')) {
            $rules['email'] = ['required', 'email', Rule::unique('users', 'email')->ignore($request->user_id)];
        }

        $request->validate($rules);

        $user = User::findOrFail($request->user_id);

        if ($user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Error! Admin account cannot be updated here.',
            ], 403);
        }

        $updateData = array_filter($request->only(
            'name',
            'email',
            'phone_number',
            'company_name',
            'position',
            'address',
            'bio'
        ), fn ($value) => ! is_null($value));

        if ($request->filled('status')) {
            $statuses = array_flip(array_map('strtolower', self::USER_STATUSES));
            $updateData['is_banned'] = $statuses[strtolower($request->status)];
        }

        $user->update($updateData);

        if ($request->filled('role')) {
            $user->syncRoles([$request->role]);
        }

        $updatedUser = $this->formatUser($user->fresh(), 0);

        return response()->json([
            'success' => true,
            'message' => 'Update Successfully.',
            'data' => $updatedUser,
        ]);
    }

    // user delete
    public function destroy(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);

        if ($user->id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Error! You cannot delete your own admin account.',
            ], 403);
        }

        if ($user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Error! Admin accounts cannot be deleted here.',
            ], 403);
        }

        DB::transaction(function () use ($user) {
            Booking::where('buyer_id', $user->id)
                ->orWhere('seller_id', $user->id)
                ->delete();

            $user->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'User account deleted successfully.',
        ]);
    }

    // (Toggle Ban)
    public function toggleBan(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'status' => 'nullable|string|in:Active,Inactive,Suspended,active,inactive,suspended',
        ]);

        $user = User::findOrFail($request->user_id);

        if ($user->hasRole('admin')) {
            return response()->json([
                'success' => 'false',
                'message' => 'Error! Admin accounts cannot be banned!',
            ], 403);
        }

        if ($request->filled('status')) {
            $statuses = array_flip(array_map('strtolower', self::USER_STATUSES));
            $user->is_banned = $statuses[strtolower($request->status)];
        } else {
            $user->is_banned = (int) $user->is_banned === 0 ? 1 : 0;
        }

        $user->save();

        $status = self::USER_STATUSES[$user->is_banned];
        $users = $this->formattedUsers();

        return response()->json([
            'success' => true,
            'message' => "User status has been changed to {$status}.",
            'updated_user' => $users->firstWhere('id', $user->id),
            'data' => $users,
        ]);
    }

    private function formattedUsers()
    {
        return User::withoutRole('admin')
            ->get()
            ->map(fn ($user, $index) => $this->formatUser($user, $index));
    }

    private function formatUser(User $user, int $index): array
    {
        $userStatus = self::USER_STATUSES[$user->is_banned] ?? 'Active';

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first() ?? 'buyer',
            'status' => $userStatus,
            'is_approved' => $user->is_approved,
            'phone_number' => $user->phone_number,
            'company_name' => $user->company_name,
            'position' => $user->position,
            'address' => $user->address,
            'avatar' => $user->avatar,
            'avatar_url' => $user->avatar ? asset('storage/'.$user->avatar) : null,
            'cover_photo' => $user->cover_photo,
            'cover_photo_url' => $user->cover_photo ? asset('storage/'.$user->cover_photo) : null,
            'bio' => $user->bio,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    private function formatUserDetail(User $user): array
    {
        $userStatus = self::USER_STATUSES[$user->is_banned] ?? 'Active';

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first() ?? 'buyer',
            'status' => $userStatus,
            'is_approved' => $user->is_approved,
            'phone_number' => $user->phone_number,
            'company_name' => $user->company_name,
            'position' => $user->position,
            'address' => $user->address,
            'avatar' => $user->avatar,
            'avatar_url' => $user->avatar ? asset('storage/'.$user->avatar) : null,
            'cover_photo' => $user->cover_photo,
            'cover_photo_url' => $user->cover_photo ? asset('storage/'.$user->cover_photo) : null,
            'bio' => $user->bio,
            'booking_count' => Booking::where('buyer_id', $user->id)
                ->orWhere('seller_id', $user->id)
                ->count(),
            'service_count' => Service::where('user_id', $user->id)->count(),
            'sent_message_count' => $user->sentMessage->count(),
            'received_message_count' => $user->receivedMessage->count(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    // view all seller request
    public function sellerRequests()
    {
        $requests = User::role('buyer')
            ->whereNotNull('company_name')
            ->where('is_approved', false)
            ->latest('updated_at')
            ->get()
            ->map(fn ($user) => $this->formatSellerRequest($user));

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    public function showSellerRequest(Request $request, ?int $userId = null)
    {
        $request->merge([
            'user_id' => $userId ?? $request->query('user_id'),
        ]);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::role('buyer')
            ->whereNotNull('company_name')
            ->where('is_approved', false)
            ->findOrFail($validated['user_id']);

        return response()->json([
            'success' => true,
            'data' => $this->formatSellerRequest($user),
        ]);
    }

    private function formatSellerRequest(User $user): array
    {
        $user->loadMissing('roles');
        $role = $user->getRoleNames()->first() ?? 'buyer';

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
            'status' => self::USER_STATUSES[$user->is_banned] ?? 'Active',
            'is_approved' => (int) $user->is_approved === 1,
            'approval_status' => self::SELLER_REQUEST_STATUSES[$user->is_approved] ?? 'Pending',
            'phone_number' => $user->phone_number,
            'company_name' => $user->company_name,
            'position' => $user->position,
            'address' => $user->address,
            'bio' => $user->bio,
            'avatar' => $user->avatar,
            'avatar_url' => $user->avatar ? asset('storage/'.$user->avatar) : null,
            'cover_photo' => $user->cover_photo,
            'cover_photo_url' => $user->cover_photo ? asset('storage/'.$user->cover_photo) : null,
            'requested_at' => $user->updated_at,
        ];
    }

    // Dashboard
    public function getDashboardStats()
    {
        $totalUsers = User::count();
        $totalCategories = Category::count();
        $activeUsers = User::where('is_banned', 0)->count();
        $inactiveUsers = User::where('is_banned', 1)->count();
        $suspendedUsers = User::where('is_banned', 2)->count();
        $totalServices = Service::count();
        $totalBookings = Booking::count();

        $pendingBookings = Booking::where('status', 'pending')->count();
        $inProgressBookings = Booking::where('status', 'in_progress')->count();
        $completedBookings = Booking::where('status', 'completed')->count();

        return response()->json([
            'success' => 'true',
            'data' => [
                'users' => [
                    'total' => $totalUsers,
                    'active' => $activeUsers,
                    'inactive' => $inactiveUsers,
                    'suspended' => $suspendedUsers,
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
                    'in_progress' => $inProgressBookings,
                    'completed' => $completedBookings,
                ],
            ],
        ]);
    }

    // view services
    public function services()
    {
        $services = Service::with(['category', 'reviews'])
            ->latest()
            ->get()
            ->map(fn ($service, $index) => $this->formatService($service, $index));

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    // view each service
    public function showService(Request $request, ?int $serviceId = null)
    {
        $request->merge([
            'service_id' => $serviceId ?? $request->query('service_id'),
        ]);

        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
        ]);

        $service = Service::with(['category', 'reviews'])->findOrFail($validated['service_id']);

        return response()->json([
            'success' => true,
            'data' => $this->formatService($service, 0),
        ]);
    }

    private function formatService(Service $service, int $index): array
    {
        return [
            'id' => $service->id,
            'title' => $service->title,
            'description' => $service->description,
            'price' => $service->price,
            'estimated_days' => $service->estimated_days,
            'tags' => $service->tags ?? [],
            'image' => $service->image,
            'average_rating' => round($service->reviews->avg('rating'), 1) ?: 0,
            'total_reviews' => $service->reviews->count(),
            'category' => $service->category ? [
                'id' => $service->category->id,
                'name' => $service->category->name,
            ] : null,
            'created_at' => $service->created_at,
            'updated_at' => $service->updated_at,
        ];
    }

    // service delete
    public function deleteService(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
        ]);

        $service = Service::findOrFail($request->service_id);

        DB::transaction(function () use ($service) {
            Booking::where('service_id', $service->id)->delete();

            $service->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Service deleted successfully.',
        ]);
    }

    // system notice
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

    // approve seller
    public function approveSeller(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);

        if ($user->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'This user is already a seller.',
                'data' => $this->formatUser($user, 0),
            ], 400);
        }

        $user->is_approved = true;
        $user->save();
        $user->syncRoles(['seller']);
        $user->notify(new SellerRequestNotification('Your seller request has been approved.', 'seller_approved'));

        $user = $user->fresh();
        $user->load('roles');

        return response()->json([
            'success' => true,
            'message' => "User {$user->name} has been approved as seller and notified.",
            'data' => $this->formatUser($user, 0),
        ]);
    }

    // reject seller request
    public function rejectSeller(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'message' => 'required|string|max:1000',
        ]);

        $user = User::findOrFail($request->user_id);

        if ($user->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'This user is already a seller and cannot be rejected.',
                'data' => $this->formatUser($user, 0),
            ], 400);
        }

        $user->is_approved = 2;
        $user->save();
        $user->notify(new SellerRequestNotification(
            'Your seller request has been rejected. Message: '.$request->message,
            'seller_rejected',
            [
                'message' => $request->message,
                'user_id' => $user->id,
            ]
        ));

        $user = $user->fresh();
        $user->load('roles');

        return response()->json([
            'success' => true,
            'message' => "User {$user->name}'s seller request has been rejected and notified.",
            'reason' => $request->message,
            'data' => $this->formatSellerRequest($user),
        ]);
    }
}
