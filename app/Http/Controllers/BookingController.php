<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function availableServices(Request $request)
    {
        $services = Service::with('user')
            ->whereHas('user', function ($query) {
                $query->where('is_banned', false);
            })
            ->get();

        return response()->json([
            'success' => 'true',
            'message' => 'Available services',
            'data' => $services,
        ]);
    }

    public function store(Request $request)
    {
        if (! Auth::user()->hasRole('buyer')) {
            return response()->json([
                'success' => 'false',
                'message' => 'Only buyer can book services',
            ], 403);
        }

        $request->validate([
            'service_id' => 'required|exists:services,id',
            'order_note' => 'required|string',
        ]);

        $service = Service::findOrFail($request->service_id);

        if ($service->user_id === Auth::id()) {
            return response()->json([
                'success' => 'false',
                'message' => 'You cannot book your own service.',
            ], 400);
        }

        $booking = Booking::create([
            'buyer_id' => Auth::id(),
            'seller_id' => $service->user_id,
            'service_id' => $service->id,
            'order_note' => $request->order_note,
            'due_date' => now()->addDays((int) filter_var($service->estimated_days, FILTER_SANITIZE_NUMBER_INT) ?: 7),
            'status' => 'pending',
        ]);

        $seller = User::find($service->user_id);
        if ($seller) {
            $seller->notify(new AppNotification(Auth::user()->name.' has booked your service.'));
        }

        return response()->json([
            'success' => 'true',
            'message' => 'Booking successful!',
            'data' => $booking,
        ], 201);
    }

    public function myBookings()
    {
        $bookings = Booking::where('buyer_id', Auth::id())
            ->with(['service', 'seller'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => 'true',
            'data' => $bookings,
        ]);
    }
}
