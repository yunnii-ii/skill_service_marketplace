<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    //
    public function store(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);
        $booking = Booking::findOrFail($request->booking_id);
        if ($booking->buyer_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized. You can only review your own bookings.'], 403);
        }

        if ($booking->status !== 'completed') {
            return response()->json(['message' => 'You can only review completed bookings.'], 400);
        }

        $exists = Review::where('booking_id', $booking->id)->exists();
        if ($exists) {
            return response()->json(['message' => 'You have already reviewed this booking.'], 400);
        }
        $review = Review::create([
            'booking_id' => $booking->id,
            'buyer_id' => auth()->id(),
            'seller_id' => $booking->seller_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'message' => 'Review submitted successfully!',
            'review' => $review,
        ], 201);
    }

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

        $averageRating = Review::where('seller_id', $sellerId)->avg('rating');

        return response()->json([
            'success' => true,
            'seller_id' => $sellerId,
            'average_rating' => $averageRating ? round($averageRating, 1) : 0,
            'total_reviews' => $reviews->count(),
            'reviews' => $reviews,
        ], 201);
    }
}
