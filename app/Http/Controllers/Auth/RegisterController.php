<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_approved' => false,
        ]);

        $user->assignRole('buyer');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => 'true',
            'message' => 'Register is successful.Please wait for admin approval before logging in.',
            'token' => $token,
            'data' => $user->load('roles'),
        ], 201);
    }
}
