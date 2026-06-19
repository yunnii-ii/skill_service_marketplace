<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminLoginController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'You are not admin'], 403);
        }

        $token = $user->createToken('admin_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Admin login successful',
            'token' => $token,
            'data' => $this->formatAdmin($user),
        ]);
    }

    //show admin profile
    public function profile(Request $request)
    {
        $admin = $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->formatAdmin($admin),
        ]);
    }

    //admin profile update
    public function updateProfile(Request $request)
    {
        $admin = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,'.$admin->id,
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $updateData = array_filter($request->only('name', 'email'), fn ($value) => ! is_null($value));

        if ($request->hasFile('avatar')) {
            $this->deletePublicFile($admin->avatar);
            $updateData['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        if ($request->hasFile('cover_photo')) {
            $this->deletePublicFile($admin->cover_photo);
            $updateData['cover_photo'] = $request->file('cover_photo')->store('cover_photos', 'public');
        }

        $admin->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Admin profile updated successfully.',
            'data' => $this->formatAdmin($admin->fresh()),
        ]);
    }

    //delete admin details
    public function deleteProfileDetails(Request $request)
    {
        $admin = $request->user();

        $request->validate([
            'avatar' => 'sometimes|boolean',
            'cover_photo' => 'sometimes|boolean',
        ]);

        $deleteAvatar = $request->boolean('avatar');
        $deleteCoverPhoto = $request->boolean('cover_photo');
        $updateData = [];

        if (! $deleteAvatar && ! $deleteCoverPhoto) {
            return response()->json([
                'success' => false,
                'message' => 'Please choose avatar or cover_photo to delete.',
            ], 422);
        }

        if ($deleteAvatar) {
            $this->deletePublicFile($admin->avatar);
            $updateData['avatar'] = null;
        }

        if ($deleteCoverPhoto) {
            $this->deletePublicFile($admin->cover_photo);
            $updateData['cover_photo'] = null;
        }

        if (! empty($updateData)) {
            $admin->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Admin profile details deleted successfully.',
            'data' => $this->formatAdmin($admin->fresh()),
        ]);
    }

    //admin logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin logout successful',
        ]);
    }

    private function formatAdmin(User $admin): array
    {
        return [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'admin',
            'avatar' => $admin->avatar,
            'avatar_url' => $admin->avatar ? asset('storage/'.$admin->avatar) : null,
            'cover_photo' => $admin->cover_photo,
            'cover_photo_url' => $admin->cover_photo ? asset('storage/'.$admin->cover_photo) : null,
        ];
    }

    private function deletePublicFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
