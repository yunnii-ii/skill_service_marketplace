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

    public function availableServices(Request $request)
    {
        $services = Service::with('user')
            ->whereHas('user', function ($query) {
                $query->where('is_banned', 0);
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
            ->get()
            ->map(fn ($booking) => $this->formatBooking($booking));

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    private function formatBooking(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'service_id' => $booking->service_id,
            'buyer_id' => $booking->buyer_id,
            'seller_id' => $booking->seller_id,
            'status' => $booking->status,
            'due_date' => $booking->due_date,
            'order_note' => $booking->order_note,
            'service' => $booking->service ? [
                'id' => $booking->service->id,
                'category_id' => $booking->service->category_id,
                'title' => $booking->service->title,
                'description' => $booking->service->description,
                'price' => $booking->service->price,
                'estimated_days' => $booking->service->estimated_days,
                'image' => $booking->service->image,
                'image_url' => $booking->service->image ? asset('storage/'.$booking->service->image) : null,
            ] : null,
            'seller' => $booking->seller ? $this->formatUser($booking->seller) : null,
            'created_at' => $booking->created_at,
            'updated_at' => $booking->updated_at,
        ];
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first() ?? 'buyer',
            'status' => self::USER_STATUSES[$user->is_banned] ?? 'Active',
            'phone_number' => $user->phone_number,
            'company_name' => $user->company_name,
            'position' => $user->position,
            'address' => $user->address,
            'avatar' => $user->avatar,
            'avatar_url' => $user->avatar ? asset('storage/'.$user->avatar) : null,
            'cover_photo' => $user->cover_photo,
            'cover_photo_url' => $user->cover_photo ? asset('storage/'.$user->cover_photo) : null,
            'bio' => $user->bio,
            'is_approved' => (int) $user->is_approved === 1,
            'approval_status' => self::SELLER_REQUEST_STATUSES[$user->is_approved] ?? 'Pending',
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
