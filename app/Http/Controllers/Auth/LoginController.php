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
            return response()->json(['message' => 'Email or password is wrong.'], 401);
        }

        if (! $user->hasRole('admin') && $user->is_approved == false) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is pending for admin approval. You cannot log in yet.',
            ], 403);
        }

        if ($user->is_banned) {
            return response()->json(['message' => 'Your account has been banned, cannot login.'], 403);
        }
        $token = $user->createToken('auth_token')->plainTextToken;
        $role = 'buyer';
        if ($user->hasRole('admin')) {
            $role = 'admin';
        } elseif ($user->hasRole('seller')) {
            $role = 'seller';
        }

        return response()->json([
            'message' => 'Login is successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
                'profile_bio' => $user->profile_bio,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ], 201);
    }
}
