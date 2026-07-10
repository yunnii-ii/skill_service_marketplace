<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

class ForgotPasswordController extends Controller
{
    public function store(Request $request)
    {
        $request->merge([
            'email' => is_string($request->email) ? trim($request->email) : $request->email,
        ]);

        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'There is no account with this email.',
            ], 404);
        }

        $token = Password::broker()->createToken($user);

        try {
            $user->notify(new PasswordResetCodeNotification($token));
        } catch (Throwable $exception) {
            Log::error('Failed to send password reset email.', [
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Password reset email could not be sent. Please try again later.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset token has been sent to your email.',
        ]);
    }
}
