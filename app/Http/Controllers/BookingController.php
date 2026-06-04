<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\AppNotification;
// use App\Http\Controllers\FILTER_SANTIZE_NUMBER_INT;


class BookingController extends Controller
{
    public function availableServices(Request $request)
    {
        $services = Service::with('user')
            ->whereHas('user', function($query) {
                $query->where('is_banned', false);
            })
            ->get();

        return response()->json([
            'message' => 'Avabile services',
            'services' => $services
        ]);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('buyer')){
            return response()->json(['message'=>'Only buyer can book services'], 403);
        }
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'order_note' => 'required|string',
        ]);

        $service = Service::findOrFail($request->service_id);

        $booking = Booking::create([
            'buyer_id' => auth()->id(),
            'seller_id' => $service->user_id,
            'service_id' => $service->id,
            'order_note' => $request->order_note,
            //due_date' => now()->addDays($service->estimated_days ?? 7),
            'due_date' =>now()->addDays((int) filter_var($service->estimated_days, FILTER_SANITIZE_NUMBER_INT) ?: 7),
            'status' => 'pending',
        ]);
        $seller = User::find($service->user_id);
        if ($seller){
              $seller->notify(new AppNotification(auth()->user()->name . " has booked your service."));
        }

        return response()->json([
            'message' => 'Booking successful!',
            'booking' => $booking
        ], 201);
    }

    public function myBookings()
    {
        $bookings = Booking::where('buyer_id', auth()->id())->with('service')->get();
        return response()->json($bookings);
    }
}
