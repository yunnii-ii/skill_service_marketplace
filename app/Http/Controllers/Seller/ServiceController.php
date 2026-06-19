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
    //view services
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
            $service->image_url = $service->image ? asset('storage/'.$service->image) : null;

            return $service;
        });

        return response()->json($services, 200);
    }

    //service create
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
            'success' => true,
            'message' => 'Service create successful..',
            'data' => $this->formatService($service),
        ], 201);
    }

    //service update
    public function update(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'category_id' => 'sometimes|exists:categories,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'estimated_days' => 'sometimes|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $service = Service::where('user_id', Auth::id())->findOrFail($request->service_id);

        $updateData = $request->only([
            'category_id',
            'title',
            'description',
            'price',
            'estimated_days',
        ]);

        if ($request->hasFile('image')) {
            $updateData['image'] = $request->file('image')->store('services', 'public');
        }

        $service->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Service updated successfully.',
            'data' => $this->formatService($service->fresh()),
        ], 200);
    }

    //service delete
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

    //booking status change
    public function changeBookingStatus(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'status' => 'required|in:pending,accepted,in_progress,rejected,completed,cancelled',
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

    //get top rated
    public function topRated()
    {
        $services = Service::with('reviews')->get();
        $services->transform(function ($service) {
            $service->average_rating = round($service->reviews->avg('rating'), 1) ?: 0;
            $service->total_reviews = $service->reviews->count();
            $service->image_url = $service->image ? asset('storage/'.$service->image) : null;

            return $service;
        });

        $topServices = $services->sortByDesc('average_rating')->take(5)->values();

        return response()->json($topServices, 200);
    }

    private function formatService(Service $service): array
    {
        return [
            'id' => $service->id,
            'user_id' => $service->user_id,
            'category_id' => $service->category_id,
            'title' => $service->title,
            'description' => $service->description,
            'price' => $service->price,
            'estimated_days' => $service->estimated_days,
            'image' => $service->image,
            'image_url' => $service->image ? asset('storage/'.$service->image) : null,
            'created_at' => $service->created_at,
            'updated_at' => $service->updated_at,
        ];
    }
}
