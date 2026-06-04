<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
    //
    public function store(Request $request){
        $request->validate([
            'email'=>'required|email',
            'password'=>'required|string|min:6|confirmed',
        ]);
        $user =User::where('email', $request->email)->first();
        if (!$user){
            return response()->json(['message'=>'User not found'],404);
        }
        $user->password=Hash::make($request->password);
        $user->save();
        return response()->json(['message'=>'Password changed successfully']);
    }
}
