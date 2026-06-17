<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{
    //
    public function index(Request $request)
    {
        $query = Service::with('reviews');
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->query('max_price'));
        }
        $services = $query->get();
        // $services = Service::with('reviews')->get();
        $services->transform(function ($service) {
            $service->average_rating = round($service->reviews->avg('rating'), 1) ?: 0;
            $service->total_reviews = $service->reviews->count();

            return $service;
        });

        return response()->json($services, 200);
    }

    public function store(Request $request)
    {
        if (! auth()->user()->hasRole('seller')) {
            return response()->json([
                'success' => 'false',
                'message' => 'Only sellers are allowed to post services. ',
            ], 403);
        }
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'estimated_days' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('services', 'public');
        }

        $service = Service::create([
            'user_id' => Auth::id(),
            'category_id' => $request->category_id,
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'estimated_days' => $request->estimated_days,
            'image' => $imagePath,
        ]);

        return response()->json([
            'success' => 'true',
            'message' => 'Service create successful..',
            'data' => $service
        ], 201);
    }

    public function update(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
        ]);

        $service = Service::where('user_id', Auth::id())->findOrFail($request->service_id);

        $service->update($request->all());

        return response()->json([
            'success' => 'true',
            'message' => 'Service updated successfully.',
            'data' => $service,
        ], 200);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
        ]);

        $service = Service::where('user_id', Auth::id())->findOrFail($request->service_id);
        $service->delete();

        return response()->json([
            'success' => 'true',
            'message' => 'Service deleted successfully.',
        ]);
    }

    public function changeBookingStatus(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'status' => 'required|in:pending,accepted,rejected,completed,cancelled',
        ]);

        $booking = Booking::where('seller_id', Auth::id())->findOrFail($request->booking_id);

        $booking->status = $request->status;
        $booking->save();

        $buyer = User::find($booking->buyer_id);
        if ($buyer) {
            $statusText = str_replace('_', ' ', $request->status);
            $notiMessage = 'Your booking status has been updated to "'.$statusText.'" by the seller.';
            $buyer->notify(new AppNotification($notiMessage));
        }

        return response()->json([
            'success' => 'true',
            'message' => 'Status have been changed.',
            'data' => $booking,
        ], 200);
    }

    public function topRated()
    {
        $services = Service::with('reviews')->get();
        $services->transform(function ($service) {
            $service->average_rating = round($service->reviews->avg('rating'), 1) ?: 0;
            $service->total_reviews = $service->reviews->count();

            return $service;
        });

        $topServices = $services->sortByDesc('average_rating')->take(5)->values();

        return response()->json($topServices, 200);
    }
}
