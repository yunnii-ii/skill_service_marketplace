<?php

namespace App\Http\Controllers;

use App\Models\SavedSeller;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SavedSellerController extends Controller
{
    private const USER_STATUSES = [
        0 => 'Active',
        1 => 'Inactive',
        2 => 'Suspended',
    ];

    private const SELLER_REQUEST_STATUSES = [
        0 => 'Pending',
        1 => 'Approved',
        2 => 'Rejected',
    ];

    //view saved sellers
    public function index()
    {
        if (! Auth::user()->hasRole('buyer')) {
            return response()->json([
                'success' => false,
                'message' => 'Only buyer can view saved sellers.',
            ], 403);
        }

        $savedSellers = SavedSeller::with('seller')
            ->where('buyer_id', Auth::id())
            ->latest()
            ->get()
            ->map(fn ($savedSeller) => $this->formatSavedSeller($savedSeller));

        return response()->json([
            'success' => true,
            'data' => $savedSellers,
        ]);
    }

    //save seller
    public function store(Request $request)
    {
        if (! Auth::user()->hasRole('buyer')) {
            return response()->json([
                'success' => false,
                'message' => 'Only buyer can save sellers.',
            ], 403);
        }

        $request->validate([
            'seller_id' => 'required|exists:users,id',
        ]);

        $seller = User::findOrFail($request->seller_id);

        if (! $seller->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'This user is not a seller.',
            ], 422);
        }

        if ((int) $seller->id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot save yourself.',
            ], 400);
        }

        $savedSeller = SavedSeller::firstOrCreate([
            'buyer_id' => Auth::id(),
            'seller_id' => $seller->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => $savedSeller->wasRecentlyCreated
                ? 'Seller saved successfully.'
                : 'Seller is already saved.',
            'data' => $this->formatSavedSeller($savedSeller->load('seller')),
        ], $savedSeller->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request)
    {
        if (! Auth::user()->hasRole('buyer')) {
            return response()->json([
                'success' => false,
                'message' => 'Only buyer can remove saved sellers.',
            ], 403);
        }

        $request->validate([
            'seller_id' => 'required|exists:users,id',
        ]);

        $deleted = SavedSeller::where('buyer_id', Auth::id())
            ->where('seller_id', $request->seller_id)
            ->delete();

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'This seller is not in your saved list.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Saved seller removed successfully.',
        ]);
    }

    private function formatSavedSeller(SavedSeller $savedSeller): array
    {
        $seller = $savedSeller->seller;

        return [
            'id' => $savedSeller->id,
            'seller_id' => $savedSeller->seller_id,
            'saved_at' => $savedSeller->created_at,
            'seller' => $seller ? [
                'id' => $seller->id,
                'name' => $seller->name,
                'email' => $seller->email,
                'role' => $seller->getRoleNames()->first() ?? 'seller',
                'status' => self::USER_STATUSES[$seller->is_banned] ?? 'Active',
                'phone_number' => $seller->phone_number,
                'company_name' => $seller->company_name,
                'position' => $seller->position,
                'address' => $seller->address,
                'avatar' => $seller->avatar,
                'avatar_url' => $seller->avatar ? asset('storage/'.$seller->avatar) : null,
                'cover_photo' => $seller->cover_photo,
                'cover_photo_url' => $seller->cover_photo ? asset('storage/'.$seller->cover_photo) : null,
                'bio' => $seller->bio,
                'is_approved' => (int) $seller->is_approved === 1,
                'approval_status' => self::SELLER_REQUEST_STATUSES[$seller->is_approved] ?? 'Pending',
                'services_count' => Service::where('user_id', $seller->id)->count(),
                'created_at' => $seller->created_at,
                'updated_at' => $seller->updated_at,
            ] : null,
        ];
    }
}
