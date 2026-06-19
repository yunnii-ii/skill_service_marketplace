<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    private const SELLER_REQUEST_STATUSES = [
        0 => 'Pending',
        1 => 'Approved',
        2 => 'Rejected',
    ];

    //
    public function show(){
        $user = Auth::user();

        return response()->json([
            'success'=> true,
            'data'=> $this->formatUser($user)
        ], 200);
    }
    public function update(Request $request){
        $user= Auth::user();
        if (!$user->hasAnyRole(['buyer', 'seller'])){
            return response()->json([
                'success'=>'false',
                'message'=>'You do not have permission to edit this profile'], 403);
        }
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone_number' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'bio' => 'nullable|string',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $updateData = $request->only([
            'name',
            'email',
            'phone_number',
            'company_name',
            'position',
            'address',
            'bio',
        ]);

        if ($request->hasFile('avatar')) {
            $updateData['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        if ($request->hasFile('cover_photo')) {
            $updateData['cover_photo'] = $request->file('cover_photo')->store('cover_photos', 'public');
        }

        $user->update($updateData);

        return response()->json([
            'success'=> true,
            'message'=>'Profile updated successfully.',
            'data'=> $this->formatUser($user->fresh())
        ], 200);
    }

    public function  getNotifications(){
        return response()->json([
            'success' => 'true',
            'data' => auth()->user()->unreadNotifications
        ]);
}

    private function formatUser($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'company_name' => $user->company_name,
            'position' => $user->position,
            'address' => $user->address,
            'avatar' => $user->avatar,
            'avatar_url' => $user->avatar ? asset('storage/'.$user->avatar) : null,
            'cover_photo' => $user->cover_photo,
            'cover_photo_url' => $user->cover_photo ? asset('storage/'.$user->cover_photo) : null,
            'bio' => $user->bio,
            'is_approved' => (int) $user->is_approved === 1,
            'approval_status' => self::SELLER_REQUEST_STATUSES[$user->is_approved] ?? 'Pending',
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
