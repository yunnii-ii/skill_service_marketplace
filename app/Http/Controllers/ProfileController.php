<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    //
    public function show(){
        return response()->json([
            'status'=> 'success',
            'data'=> Auth::user()
        ], 200);
    }
    public function update(Request $request){
        $user= Auth::user();
        if (!$user->hasAnyRole(['buyer', 'seller'])){
            return response()->json([
                'status'=>'error',
                'message'=>'You do not have permission to edit this profile'], 403);
        }
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name'=>$request->name,
            'email'=>$request->email,
        ]);

        return response()->json([
            'status'=>'successful',
            'message'=>'Profile updated successfully.',
            'data'=> $user
        ], 200);
    }

    public function  getNotifications(){
        return response()->json(auth()->user()->unreadNotifications);
}
}
