<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\SellerRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class SellerRequestController extends Controller
{
    public function submitSellerRequest(Request $request)
    {
        $user = auth()->user();
        $isAlreadySeller = $user->hasRole('seller');

        $request->validate([
            'phone_number' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'address' => 'required|string',
            'bio' => 'nullable|string',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $avatarPath = $user->avatar;
        $coverPath = $user->cover_photo;

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        if ($request->hasFile('cover_photo')) {
            $coverPath = $request->file('cover_photo')->store('cover_photos', 'public');
        }

        $updateData = [
            'phone_number' => $request->phone_number,
            'company_name' => $request->company_name,
            'position' => $request->position,
            'address' => $request->address,
            'bio' => $request->bio,
            'avatar' => $avatarPath,
            'cover_photo' => $coverPath,
        ];

        if (! $isAlreadySeller) {
            $updateData['is_approved'] = 0;
        }

        $user->update($updateData);

        if (! $isAlreadySeller) {
            $admins = User::role('admin')->get();
            Notification::send($admins, new SellerRequestNotification('Seller Request အသစ်ရောက်ရှိလာပါသည်။', 'new_seller_request', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_name' => $user->company_name,
            ]));
        }

        return response()->json([
            'success' => true,
            'message' => $isAlreadySeller
                ? 'Seller profile has been updated successfully.'
                : 'Seller Request has been submitted.Please waiting for Admin approval!',
            'data' => $this->formatSellerRequest($user->fresh()),
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
            'status' => match ((int) $user->is_banned) {
                1 => 'Inactive',
                2 => 'Suspended',
                default => 'Active',
            },
            'is_approved' => (int) $user->is_approved === 1,
            'approval_status' => match ((int) $user->is_approved) {
                1 => 'Approved',
                2 => 'Rejected',
                default => 'Pending',
            },
            'phone_number' => $user->phone_number,
            'company_name' => $user->company_name,
            'position' => $user->position,
            'address' => $user->address,
            'avatar' => $user->avatar,
            'avatar_url' => $user->avatar ? asset('storage/'.$user->avatar) : null,
            'cover_photo' => $user->cover_photo,
            'cover_photo_url' => $user->cover_photo ? asset('storage/'.$user->cover_photo) : null,
            'bio' => $user->bio,
            'requested_at' => $user->updated_at,
        ];
    }
}
