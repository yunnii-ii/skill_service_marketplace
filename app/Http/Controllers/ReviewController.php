<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{   //review create
    public function store(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $booking = Booking::findOrFail($request->booking_id);

        if ($booking->buyer_id !== auth()->id()) {
            return response()->json([
                'success' => 'false',
                'message' => 'Unauthorized. You can only review your own bookings.',
            ], 403);
        }

        if ($booking->status !== 'completed') {
            return response()->json([
                'success' => 'false',
                'message' => 'You can only review completed bookings.',
            ], 400);
        }

        if ($booking->payment_status !== 'paid') {
            return response()->json([
                'success' => 'false',
                'message' => 'Please accept completion and release payment before reviewing this booking.',
            ], 400);
        }

        $exists = Review::where('booking_id', $booking->id)->exists();
        if ($exists) {
            return response()->json([
                'success' => 'false',
                'message' => 'You have already reviewed this booking.',
            ], 400);
        }

        $review = Review::create([
            'booking_id' => $booking->id,
            'buyer_id' => auth()->id(),
            'seller_id' => $booking->seller_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'success' => 'true',
            'message' => 'Review submitted successfully!',
            'data' => $review,
        ], 201);
    }

    //view seller reviews
    public function getSellerReviews(Request $request)
    {
        $request->validate([
            'seller_id' => 'required|exists:users,id',
        ]);

        $sellerId = $request->seller_id;

        $reviews = Review::where('seller_id', $sellerId)
            ->with('buyer:id,name')
            ->latest()
            ->get();

        $averageRating = $reviews->avg('rating');

        return response()->json([
            'success' => 'true',
            'data' => [
                'seller_id' => (int) $sellerId,
                'average_rating' => $averageRating ? round($averageRating, 1) : 0,
                'total_reviews' => $reviews->count(),
                'reviews' => $reviews,
            ],
        ], 200);
    }
}
