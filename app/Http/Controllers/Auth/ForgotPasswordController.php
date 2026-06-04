<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordController extends Controller
{
    //
    public function store(Request $request){
        $request->validate(['email'=>'required|email']);
        $user=User::where('email', $request->email)->first();
        if (!$user){
            return response()->json(['message'=>'There is no account with this email.'],404);
        }
        return response()->json([
            'message'=>'',
            'reset_token'=>'RESET_TOKEN_DEMO_9988'
        ]);
    }

}
