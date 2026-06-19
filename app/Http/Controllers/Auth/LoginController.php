<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    //
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required']);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => 'false',
                'message' => 'Email or password is wrong.',
            ], 401);
        }

        if ((int) $user->is_banned === 1) {
            return response()->json([
                'success' => 'false',
                'message' => 'Your account is inactive, cannot login.',
            ], 403);
        }

        if ((int) $user->is_banned === 2) {
            return response()->json([
                'success' => 'false',
                'message' => 'Your account has been suspended, cannot login.',
            ], 403);
        }

        $user->load('roles');

        $token = $user->createToken('auth_token')->plainTextToken;
        $role = 'buyer';
        if ($user->hasRole('admin')) {
            $role = 'admin';
        } elseif ($user->hasRole('seller')) {
            $role = 'seller';
        }

        return response()->json([
            'success' => 'true',
            'message' => 'Login is successful.',
            'token' => $token,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
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
            ],
        ], 201);
    }
}
