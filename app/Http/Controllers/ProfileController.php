<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    //
    public function show(){
        return response()->json([
            'success'=> 'true',
            'data'=> Auth::user()
        ], 200);
    }
    public function update(Request $request){
        $user= Auth::user();
        if (!$user->hasAnyRole(['buyer', 'seller'])){
            return response()->json([
                'success'=>'false',
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
            'success'=>'true',
            'message'=>'Profile updated successfully.',
            'data'=> $user
        ], 200);
    }

    public function  getNotifications(){
        return response()->json([
            'success' => 'true',
            'data' => auth()->user()->unreadNotifications
        ]);
}
}
