<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Review;
use App\Models\SavedSeller;
use App\Models\SavedService;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BookingController extends Controller
{
    private const ACTIVE_BOOKING_STATUSES = [
        'pending',
        'accepted',
        'in_progress',
    ];

    private const PAYMENT_METHODS = [
        'kbz_pay',
        'aya_pay',
        'cb_pay',
        'uab_pay',
        'kbz_bank',
        'aya_bank',
        'cb_bank',
        'uab_bank',
        'yoma_bank',
    ];

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

    //show services
    public function availableServices(Request $request)
    {
        $services = Service::with('user')
            ->where('is_active', true)
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

    //book-service
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
            'order_note' => 'nullable|string',
        ]);

        $service = Service::findOrFail($request->service_id);

        if (! $service->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This service is currently inactive and cannot be booked.',
            ], 400);
        }

        if ($service->user_id === Auth::id()) {
            return response()->json([
                'success' => 'false',
                'message' => 'You cannot book your own service.',
            ], 400);
        }

        $activeBooking = Booking::where('buyer_id', Auth::id())
            ->where('service_id', $service->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->where('payment_status', 'unpaid')
            ->latest()
            ->first();

        if ($activeBooking) {
            return response()->json([
                'success' => false,
                'message' => 'You have already booked for this service.',
                'data' => [
                    'booking_id' => $activeBooking->id,
                    'service_id' => $activeBooking->service_id,
                    'status' => $activeBooking->status,
                    'payment_status' => $activeBooking->payment_status,
                ],
            ], 409);
        }

        $booking = Booking::create([
            'buyer_id' => Auth::id(),
            'seller_id' => $service->user_id,
            'service_id' => $service->id,
            'order_note' => $request->order_note,
            'due_date' => now()->addDays((int) filter_var($service->estimated_days, FILTER_SANITIZE_NUMBER_INT) ?: 7),
            'status' => 'pending',
            'payment_status' => 'unpaid',
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

    public function paymentMethods()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'mobile_wallets' => [
                    ['key' => 'kbz_pay', 'name' => 'KBZ Pay'],
                    ['key' => 'aya_pay', 'name' => 'AYA Pay'],
                    ['key' => 'cb_pay', 'name' => 'CB Pay'],
                    ['key' => 'uab_pay', 'name' => 'UAB Pay'],
                ],
                'banking' => [
                    ['key' => 'kbz_bank', 'name' => 'KBZ Banking'],
                    ['key' => 'aya_bank', 'name' => 'AYA Banking'],
                    ['key' => 'cb_bank', 'name' => 'CB Banking'],
                    ['key' => 'uab_bank', 'name' => 'UAB Banking'],
                    ['key' => 'yoma_bank', 'name' => 'Yoma Banking'],
                ],
            ],
        ]);
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

    //buyer view each booking details
    public function show(Request $request, ?int $bookingId = null)
    {
        if (! Auth::user()->hasRole('buyer')) {
            return response()->json([
                'success' => false,
                'message' => 'Only buyer can view booking details.',
            ], 403);
        }

        if ($bookingId) {
            $request->merge(['booking_id' => $bookingId]);
        }

        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $booking = Booking::where('buyer_id', Auth::id())
            ->with(['service', 'seller', 'review'])
            ->findOrFail($request->booking_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatBooking($booking),
        ]);
    }

    //buyer accept booking completion 
    public function acceptCompletion(Request $request)
    {
        if (! Auth::user()->hasRole('buyer')) {
            return response()->json([
                'success' => false,
                'message' => 'Only buyer can accept booking completion.',
            ], 403);
        }

        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'payment_method' => 'required|in:'.implode(',', self::PAYMENT_METHODS),
            'payment_proof' => 'required|file|mimes:jpeg,png,jpg,webp,pdf|max:4096',
        ]);

        $booking = Booking::with(['service', 'seller'])
            ->where('buyer_id', Auth::id())
            ->findOrFail($request->booking_id);

        if ($booking->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Seller must mark this booking as completed before buyer can accept it.',
            ], 400);
        }

        if ($booking->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'This booking has already been accepted and paid.',
                'data' => $this->formatBooking($booking),
            ], 409);
        }

        $paymentProofPath = $request->file('payment_proof')->store('payment_proofs', 'public');

        if ($booking->payment_proof) {
            $this->deletePublicFile($booking->payment_proof);
        }

        $booking->update([
            'payment_method' => $request->payment_method,
            'payment_proof' => $paymentProofPath,
            'payment_status' => 'paid',
            'buyer_accepted_at' => now(),
            'paid_at' => now(),
        ]);

        if ($booking->seller) {
            $booking->seller->notify(new AppNotification(Auth::user()->name.' accepted your completed booking. Payment has been marked as paid.'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking completion accepted. Payment has been marked as paid.',
            'data' => $this->formatBooking($booking->fresh(['service', 'seller', 'review'])),
        ]);
    }


    public function buyerStats()
    {
        if (! Auth::user()->hasRole('buyer')) {
            return response()->json([
                'success' => false,
                'message' => 'Only buyer can view booking stats.',
            ], 403);
        }

        $bookingQuery = Booking::where('buyer_id', Auth::id());

        $statusCounts = (clone $bookingQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $completedWithoutReview = (clone $bookingQuery)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereDoesntHave('review')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'bookings' => [
                    'total' => (clone $bookingQuery)->count(),
                    'pending' => (int) ($statusCounts['pending'] ?? 0),
                    'accepted' => (int) ($statusCounts['accepted'] ?? 0),
                    'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
                    'completed' => (int) ($statusCounts['completed'] ?? 0),
                    'rejected' => (int) ($statusCounts['rejected'] ?? 0),
                    'cancelled' => (int) ($statusCounts['cancelled'] ?? 0),
                    'active' => (clone $bookingQuery)->whereIn('status', self::ACTIVE_BOOKING_STATUSES)->count(),
                    'due_today' => (clone $bookingQuery)
                        ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
                        ->whereDate('due_date', now()->toDateString())
                        ->count(),
                    'overdue' => (clone $bookingQuery)
                        ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
                        ->whereDate('due_date', '<', now()->toDateString())
                        ->count(),
                ],
                'reviews' => [
                    'given' => Review::where('buyer_id', Auth::id())->count(),
                    'available_to_review' => $completedWithoutReview,
                ],
                'saved_services' => [
                    'total' => SavedService::where('buyer_id', Auth::id())->count(),
                ],
                'saved_sellers' => [
                    'total' => SavedSeller::where('buyer_id', Auth::id())->count(),
                ],
            ],
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
            // 'payment_method' => $booking->payment_method,
            'payment_method' => $this->paymentMethodName($booking->payment_method),
            'payment_status' => $booking->payment_status ?? 'unpaid',
            'payment_proof' => $booking->payment_proof,
            'payment_proof_url' => $booking->payment_proof ? asset('storage/'.$booking->payment_proof) : null,
            'buyer_accepted_at' => $booking->buyer_accepted_at,
            'paid_at' => $booking->paid_at,
            'due_date' => $booking->due_date,
            'order_note' => $booking->order_note,
            'service' => $booking->service ? [
                'id' => $booking->service->id,
                'category_id' => $booking->service->category_id,
                'title' => $booking->service->title,
                'description' => $booking->service->description,
                'price' => $booking->service->price,
                'estimated_days' => $booking->service->estimated_days,
                'tags' => $booking->service->tags ?? [],
                'image' => $booking->service->image,
                'image_url' => $booking->service->image ? asset('storage/'.$booking->service->image) : null,
            ] : null,
            'seller' => $booking->seller ? $this->formatUser($booking->seller) : null,
            'review' => $booking->review ? [
                'id' => $booking->review->id,
                'rating' => $booking->review->rating,
                'comment' => $booking->review->comment,
                'created_at' => $booking->review->created_at,
                'updated_at' => $booking->review->updated_at,
            ] : null,
            'created_at' => $booking->created_at,
            'updated_at' => $booking->updated_at,
        ];
    }

    private function paymentMethodName(?string $method): ?string
    {
        return match ($method) {
            'kbz_pay' => 'KBZ Pay',
            'aya_pay' => 'AYA Pay',
            'cb_pay' => 'CB Pay',
            'uab_pay' => 'UAB Pay',
            'kbz_bank' => 'KBZ Banking',
            'aya_bank' => 'AYA Banking',
            'cb_bank' => 'CB Banking',
            'uab_bank' => 'UAB Banking',
            'yoma_bank' => 'Yoma Banking',
            default => null,
        };
    }

    private function deletePublicFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
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
