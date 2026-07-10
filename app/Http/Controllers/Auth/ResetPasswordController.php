<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ResetPasswordController extends Controller
{
    public function store(Request $request)
    {
        $request->merge([
            'email' => is_string($request->email) ? trim($request->email) : $request->email,
            'token' => is_string($request->token ?? $request->code)
                ? trim($request->token ?? $request->code)
                : ($request->token ?? $request->code),
            'password_confirmation' => $request->password_confirmation ?? $request->confirm_password,
        ]);

        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'token', 'password', 'password_confirmation'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                if (method_exists($user, 'tokens')) {
                    $user->tokens()->delete();
                }
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => match ($status) {
                    Password::INVALID_USER => 'There is no account with this email.',
                    Password::INVALID_TOKEN => 'Invalid or expired reset token.',
                    default => 'Password could not be reset.',
                },
                'status' => $status,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }
}
