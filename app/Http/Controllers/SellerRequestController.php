<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SellerRequestController extends Controller
{
    public function submitSellerRequest(Request $request)
    {
        $user = auth()->user();

        if ($user->hasRole('buyer') && ! empty($user->nrc_number)) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait a moment as the admin is still reviewing your Seller Request form.',
            ], 400);
        }

        if ($user->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'You are already an approved seller. No need to request again!',
            ], 400);
        }

        $user->update([
            'phone_number' => $request->phone_number,
            'company_name' => $request->company_name,
            'address' => $request->address,
            'bio' => $request->bio,
            'avatar' => $avatarPath ?? $user->avatar,
            'cover_photo' => $coverPath ?? $user->cover_photo,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Seller Request has been submitted.Please waiting for Admin approval!',
        ]);
    }
}
