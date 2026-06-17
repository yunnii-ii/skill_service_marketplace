<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SellerRequestController extends Controller
{
    public function submitSellerRequest(Request $request)
    {
        $user = Auth::user();
        if ($user->is_approved) {
            return response()->json(['You are already an approved seller.'], 400);
        }
        $request->validate([
            'phone_number' => 'required|string|regex:/^09\d{9,11}$/',
            'company_name' => 'nullable|string|max:255',
            'position' => 'nullable|string',
            'bio' => 'required|string|min:20',
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'cover_photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'address' => 'required|string|min:15',

            // 'nrc_number' => 'required|string|unique:users,nrc_number,'.$user->id,
            // 'nrc_photo' => 'required|image|mimes:jpeg,png,jpg|max:3072',
        ], [

            'phone_number.regex' => 'The phone number format must be a valid Myanmar phone number.',
            // 'nrc_number.unique' => 'This NRC number has already been verified on another account.',
        ]);

        $avatarPath = $request->file('avatar')->store('avatars', 'public');
        $coverPath = $request->file('cover_photo')->store('covers', 'public');
        // $nrcPath = $request->file('nrc_photo')->store('nrc_photos', 'public');

        $user->update([
            'phone_number' => $request->phone_number,
            'company_name' => $request->company_name,
            'position' => $request->position,
            'bio' => $request->bio,
            'avatar' => $avatarPath,
            'cover_photo' => $coverPath,
            'address' => $request->address,

            // 'nrc_number' => $request->nrc_number,
            // 'nrc_photo' => $nrcPath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your profile and Seller request have been submitted successfully. Waiting for Admin approval!',
        ], 200);
    }
}
