<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class VerificationController extends Controller
{
    //
    public function verify(Request $request){
        $request->validate(['email'=>'required|email']);
        $user=User::where('email', $request->email)->first();

        if (!$user){
            return response()->json([
                'success' => 'false',
                'message' => 'User not found.'
            ], 404);
        }
        if ($user->email_verified_at !== null){
            return response()->json([
                'success' => 'false',
                'message' => 'This account has been already verified.'
            ]);
        }
        $user->email_verified_at=now();
        $user->save();
        return response()->json([
            'success' => 'true',
            'message' => 'Email Verification is successful.'
        ]);
    }
}
