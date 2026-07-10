<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Models\SavedService;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{
    public function tags()
    {
        return response()->json([
            'success' => true,
            'data' => config('service_tags', []),
        ]);
    }

    // view services
    public function index(Request $request)
    {
        $query = Service::with('reviews')->where('is_active', true);
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")
                    ->orWhereJsonContains('tags', $search);
            });
        }

        if ($request->has('tag')) {
            $query->whereJsonContains('tags', $request->query('tag'));
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

    //buyer view each service details
    public function show(Request $request, ?int $serviceId = null)
    {
        if (! Auth::user()->hasRole('buyer')) {
            return response()->json([
                'success' => false,
                'message' => 'Only buyer can view service details here.',
            ], 403);
        }

        if ($serviceId) {
            $request->merge(['service_id' => $serviceId]);
        }

        $request->validate([
            'service_id' => 'required|exists:services,id',
        ]);

        $service = Service::with(['user', 'category', 'reviews.buyer'])
            ->where('is_active', true)
            ->findOrFail($request->service_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatServiceDetails($service),
        ]);
    }

    // service create
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
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
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
            'tags' => $this->normalizeTags($request->input('tags', [])),
            'image' => $imagePath,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Service create successful..',
            'data' => $this->formatService($service),
        ], 201);
    }

    // service update
    public function update(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'category_id' => 'sometimes|exists:categories,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'estimated_days' => 'sometimes|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
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

        if ($request->has('tags')) {
            $updateData['tags'] = $this->normalizeTags($request->input('tags', []));
        }

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

    // service delete
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

    public function myServices()
    {
        if (! Auth::user()->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Only sellers can view their services.',
            ], 403);
        }

        $services = Service::with('reviews')
            ->where('user_id', Auth::id())
            ->latest()
            ->get()
            ->map(fn ($service) => $this->formatSellerService($service));

        return response()->json([
            'success' => true,
            'message' => 'Service lists',
            'data' => $services,
        ]);
    }

    //seller view each own service details
    public function showOwnService(Request $request, ?int $serviceId = null)
    {
        if (! Auth::user()->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Only sellers can view their service details.',
            ], 403);
        }

        if ($serviceId) {
            $request->merge(['service_id' => $serviceId]);
        }

        $request->validate([
            'service_id' => 'required|exists:services,id',
        ]);

        $service = Service::with(['category', 'reviews.buyer'])
            ->where('user_id', Auth::id())
            ->findOrFail($request->service_id);

        return response()->json([
            'success' => true,
            'message' => 'Seller service details.',
            'data' => $this->formatSellerServiceDetails($service),
        ]);
    }

    //service status change
    public function changeServiceStatus(Request $request)
    {
        if (! Auth::user()->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Only sellers can change service status.',
            ], 403);
        }

        $request->validate([
            'service_id' => 'required|exists:services,id',
            'is_active' => 'required|boolean',
        ]);

        $service = Service::where('user_id', Auth::id())->findOrFail($request->service_id);
        $service->update([
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json([
            'success' => true,
            'message' => $service->is_active ? 'Service is now active.' : 'Service is now inactive.',
            'data' => $this->formatSellerService($service->fresh('reviews')),
        ]);
    }

    //seller dashboard
    public function dashboard()
    {
        if (! Auth::user()->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Only sellers can view seller dashboard.',
            ], 403);
        }

        $bookingQuery = Booking::where('seller_id', Auth::id());
        $statusCounts = (clone $bookingQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $serviceIds = Service::where('user_id', Auth::id())->pluck('id');
        $reviewCount = Booking::whereIn('service_id', $serviceIds)
            ->whereHas('review')
            ->count();
        $averageRating = Review::where('seller_id', Auth::id())->avg('rating');

        $completedEarnings = Booking::where('seller_id', Auth::id())
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->sum('services.price');

        $monthStart = now()->startOfMonth();
        $thisMonthBookings = Booking::where('seller_id', Auth::id())
            ->where('created_at', '>=', $monthStart)
            ->count();
        $thisMonthEarnings = Booking::where('seller_id', Auth::id())
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->where('paid_at', '>=', $monthStart)
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->sum('services.price');
        $totalBookings = (clone $bookingQuery)->count();
        $completedBookings = (int) ($statusCounts['completed'] ?? 0);

        return response()->json([
            'success' => true,
            'role' => 'seller',
            'data' => [
                'services' => [
                    'total' => $serviceIds->count(),
                    'active' => Service::where('user_id', Auth::id())->where('is_active', true)->count(),
                    'inactive' => Service::where('user_id', Auth::id())->where('is_active', false)->count(),
                ],
                'bookings' => [
                    'total' => $totalBookings,
                    'pending' => (int) ($statusCounts['pending'] ?? 0),
                    'accepted' => (int) ($statusCounts['accepted'] ?? 0),
                    'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
                    'completed' => $completedBookings,
                    'rejected' => (int) ($statusCounts['rejected'] ?? 0),
                    'cancelled' => (int) ($statusCounts['cancelled'] ?? 0),
                    'this_month' => $thisMonthBookings,
                ],
                'reviews' => [
                    'total' => $reviewCount,
                    'average_rating' => $averageRating ? round($averageRating, 1) : 0,
                ],
                'earnings' => [
                    'completed_total' => (float) $completedEarnings,
                    'this_month' => (float) $thisMonthEarnings,
                ],
            ],
        ]);
    }

    //seller analytics
    public function analytics()
    {
        if (! Auth::user()->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Only sellers can view analytics.',
            ], 403);
        }

        $sellerId = Auth::id();
        $bookingQuery = Booking::where('seller_id', $sellerId);
        $statusCounts = (clone $bookingQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalBookings = (clone $bookingQuery)->count();
        $completedBookings = (int) ($statusCounts['completed'] ?? 0);
        $rejectedBookings = (int) ($statusCounts['rejected'] ?? 0);
        $cancelledBookings = (int) ($statusCounts['cancelled'] ?? 0);

        $completedEarnings = Booking::where('seller_id', $sellerId)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->sum('services.price');

        $monthStart = now()->startOfMonth();
        $thisMonthEarnings = Booking::where('seller_id', $sellerId)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->where('paid_at', '>=', $monthStart)
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->sum('services.price');

        $reviews = Review::where('seller_id', $sellerId);
        $averageRating = (clone $reviews)->avg('rating');
        $serviceIds = Service::where('user_id', $sellerId)->pluck('id');
        $topServices = Service::with('reviews')
            ->where('user_id', $sellerId)
            ->withCount('bookings')
            ->get()
            ->map(function ($service) {
                $completedBookings = Booking::where('service_id', $service->id)
                    ->where('status', 'completed')
                    ->where('payment_status', 'paid')
                    ->count();
                $completedEarnings = Booking::where('service_id', $service->id)
                    ->where('status', 'completed')
                    ->where('payment_status', 'paid')
                    ->join('services', 'bookings.service_id', '=', 'services.id')
                    ->sum('services.price');

                return [
                    'id' => $service->id,
                    'title' => $service->title,
                    'booking_count' => $service->bookings_count,
                    'completed_bookings' => $completedBookings,
                    'completed_earnings' => (float) $completedEarnings,
                    'average_rating' => round($service->reviews->avg('rating'), 1) ?: 0,
                    'saved_count' => SavedService::where('service_id', $service->id)->count(),
                    'is_active' => (bool) $service->is_active,
                    'status' => $service->is_active ? 'Active' : 'Inactive',
                ];
            })
            ->sortByDesc('booking_count')
            ->take(5)
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Seller analytics.',
            'data' => [
                'earnings' => [
                    'completed_total' => (float) $completedEarnings,
                    'this_month' => (float) $thisMonthEarnings,
                ],
                'reviews' => [
                    'total' => (clone $reviews)->count(),
                    'average_rating' => $averageRating ? round($averageRating, 1) : 0,
                    'five_star' => (clone $reviews)->where('rating', 5)->count(),
                    'four_star' => (clone $reviews)->where('rating', 4)->count(),
                    'three_star' => (clone $reviews)->where('rating', 3)->count(),
                    'two_star' => (clone $reviews)->where('rating', 2)->count(),
                    'one_star' => (clone $reviews)->where('rating', 1)->count(),
                ],
                'booking_status_counts' => [
                    'total' => $totalBookings,
                    'pending' => (int) ($statusCounts['pending'] ?? 0),
                    'accepted' => (int) ($statusCounts['accepted'] ?? 0),
                    'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
                    'completed' => $completedBookings,
                    'rejected' => $rejectedBookings,
                    'cancelled' => $cancelledBookings,
                ],
                'performance' => [
                    'completion_rate' => $totalBookings > 0 ? round(($completedBookings / $totalBookings) * 100, 1) : 0,
                    'rejection_rate' => $totalBookings > 0 ? round(($rejectedBookings / $totalBookings) * 100, 1) : 0,
                    'cancellation_rate' => $totalBookings > 0 ? round(($cancelledBookings / $totalBookings) * 100, 1) : 0,
                ],
                'services' => [
                    'total' => $serviceIds->count(),
                    'active' => Service::where('user_id', $sellerId)->where('is_active', true)->count(),
                    'inactive' => Service::where('user_id', $sellerId)->where('is_active', false)->count(),
                    'saved_by_buyers' => SavedService::whereIn('service_id', $serviceIds)->count(),
                    'top_services' => $topServices,
                ],
            ],
        ]);
    }

    //seller earning details
    public function earnings(Request $request)
    {
        if (! Auth::user()->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Only sellers can view earnings.',
            ], 403);
        }

        $request->validate([
            'from' => 'sometimes|date',
            'to' => 'sometimes|date|after_or_equal:from',
            'service_id' => 'sometimes|exists:services,id',
        ]);

        $sellerId = Auth::id();

        if ($request->filled('service_id')) {
            $ownsService = Service::where('user_id', $sellerId)
                ->where('id', $request->service_id)
                ->exists();

            if (! $ownsService) {
                return response()->json([
                    'success' => false,
                    'message' => 'This service does not belong to this seller.',
                ], 403);
            }
        }

        $query = Booking::where('seller_id', $sellerId)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->with(['buyer', 'service'])
            ->latest('paid_at');

        if ($request->filled('from')) {
            $query->whereDate('paid_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('paid_at', '<=', $request->to);
        }

        if ($request->filled('service_id')) {
            $query->where('service_id', $request->service_id);
        }

        $completedBookings = $query->get();
        $totalEarnings = $completedBookings->sum(fn ($booking) => (float) ($booking->service->price ?? 0));
        $thisMonthEarnings = Booking::where('seller_id', $sellerId)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->where('paid_at', '>=', now()->startOfMonth())
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->sum('services.price');

        $earningsByService = $completedBookings
            ->groupBy('service_id')
            ->map(function ($bookings) {
                $service = $bookings->first()->service;

                return [
                    'service_id' => $service?->id,
                    'title' => $service?->title,
                    'completed_orders' => $bookings->count(),
                    'total_earnings' => (float) $bookings->sum(fn ($booking) => (float) ($booking->service->price ?? 0)),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Seller earning details.',
            'data' => [
                'summary' => [
                    'total_earnings' => (float) $totalEarnings,
                    'this_month_earnings' => (float) $thisMonthEarnings,
                    'completed_orders' => $completedBookings->count(),
                    'average_order_value' => $completedBookings->count() > 0
                        ? round($totalEarnings / $completedBookings->count(), 2)
                        : 0,
                ],
                'by_service' => $earningsByService,
                'orders' => $completedBookings->map(fn ($booking) => $this->formatEarningBooking($booking))->values(),
            ],
        ]);
    }

    //seller view booking lists
    public function bookings(Request $request)
    {
        if (! Auth::user()->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Only sellers can view booking lists.',
            ], 403);
        }

        $request->validate([
            'status' => 'sometimes|in:pending,accepted,in_progress,rejected,completed,cancelled',
            'payment_status' => 'sometimes|in:unpaid,paid',
        ]);

        $query = Booking::where('seller_id', Auth::id())
            ->with(['buyer', 'service', 'review'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $bookings = $query->get()
            ->map(fn ($booking) => $this->formatBooking($booking));

        return response()->json([
            'success' => true,
            'message' => 'Seller booking lists.',
            'data' => $bookings,
        ]);
    }

    //seller view each booking details
    public function showBooking(Request $request, ?int $bookingId = null)
    {
        if (! Auth::user()->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Only sellers can view booking details.',
            ], 403);
        }

        if ($bookingId) {
            $request->merge(['booking_id' => $bookingId]);
        }

        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $booking = Booking::with(['buyer', 'service', 'review'])->find($request->booking_id);

        if (! $booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found.',
            ], 404);
        }

        if ((int) $booking->seller_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'This booking does not belong to this seller.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Seller booking details.',
            'data' => $this->formatBooking($booking),
        ]);
    }

    //change booking status
    public function changeBookingStatus(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'status' => 'required|in:pending,accepted,in_progress,rejected,completed,cancelled',
        ]);

        $booking = Booking::find($request->booking_id);

        if (! $booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found.',
            ], 404);
        }

        if ((int) $booking->seller_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'This booking does not belong to this seller.',
            ], 403);
        }

        $booking->status = $request->status;
        $booking->save();

        $buyer = User::find($booking->buyer_id);
        if ($buyer) {
            $statusText = str_replace('_', ' ', $request->status);
            $notiMessage = $request->status === 'completed'
                ? 'Seller marked your booking as completed. Please accept completion to release payment.'
                : 'Your booking status has been updated to "'.$statusText.'" by the seller.';
            $buyer->notify(new AppNotification($notiMessage));
        }

        return response()->json([
            'success' => 'true',
            'message' => 'Status have been changed.',
            'data' => $this->formatBooking($booking->fresh(['buyer', 'service', 'review']), false),
        ], 200);
    }

    // get top rated
    public function topRated()
    {
        $services = Service::with('reviews')
            ->where('is_active', true)
            ->get();
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
            'tags' => $service->tags ?? [],
            'image' => $service->image,
            'image_url' => $service->image ? asset('storage/'.$service->image) : null,
            'is_active' => (bool) $service->is_active,
            'status' => $service->is_active ? 'Active' : 'Inactive',
            'created_at' => $service->created_at,
            'updated_at' => $service->updated_at,
        ];
    }

    private function formatSellerService(Service $service): array
    {
        return [
            'id' => $service->id,
            'user_id' => $service->user_id,
            'category_id' => $service->category_id,
            'title' => $service->title,
            'description' => $service->description,
            'price' => $service->price,
            'estimated_days' => $service->estimated_days,
            'tags' => $service->tags ?? [],
            'image' => $service->image,
            'image_url' => $service->image ? asset('storage/'.$service->image) : null,
            'is_active' => (bool) $service->is_active,
            'status' => $service->is_active ? 'Active' : 'Inactive',
            'average_rating' => round($service->reviews->avg('rating'), 1) ?: 0,
            'total_reviews' => $service->reviews->count(),
            'booking_count' => Booking::where('service_id', $service->id)->count(),
            'created_at' => $service->created_at,
            'updated_at' => $service->updated_at,
        ];
    }

    private function formatSellerServiceDetails(Service $service): array
    {
        $statusCounts = Booking::where('service_id', $service->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'id' => $service->id,
            'user_id' => $service->user_id,
            'category' => $service->category ? [
                'id' => $service->category->id,
                'name' => $service->category->name,
            ] : null,
            'title' => $service->title,
            'description' => $service->description,
            'price' => $service->price,
            'estimated_days' => $service->estimated_days,
            'tags' => $service->tags ?? [],
            'image' => $service->image,
            'image_url' => $service->image ? asset('storage/'.$service->image) : null,
            'is_active' => (bool) $service->is_active,
            'status' => $service->is_active ? 'Active' : 'Inactive',
            'average_rating' => round($service->reviews->avg('rating'), 1) ?: 0,
            'total_reviews' => $service->reviews->count(),
            'saved_count' => SavedService::where('service_id', $service->id)->count(),
            'bookings' => [
                'total' => Booking::where('service_id', $service->id)->count(),
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'accepted' => (int) ($statusCounts['accepted'] ?? 0),
                'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
                'completed' => (int) ($statusCounts['completed'] ?? 0),
                'rejected' => (int) ($statusCounts['rejected'] ?? 0),
                'cancelled' => (int) ($statusCounts['cancelled'] ?? 0),
            ],
            'reviews' => $service->reviews
                ->sortByDesc('created_at')
                ->map(fn ($review) => [
                    'id' => $review->id,
                    'booking_id' => $review->booking_id,
                    'buyer' => $review->buyer ? [
                        'id' => $review->buyer->id,
                        'name' => $review->buyer->name,
                        'email' => $review->buyer->email,
                    ] : null,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at,
                    'updated_at' => $review->updated_at,
                ])
                ->values(),
            'created_at' => $service->created_at,
            'updated_at' => $service->updated_at,
        ];
    }

    private function formatBooking(Booking $booking, bool $includePaymentDetails = true): array
    {
        $data = [
            'id' => $booking->id,
            'service_id' => $booking->service_id,
            'buyer_id' => $booking->buyer_id,
            'seller_id' => $booking->seller_id,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status ?? 'unpaid',
            'due_date' => $booking->due_date,
            'order_note' => $booking->order_note,
            'buyer' => $booking->buyer ? [
                'id' => $booking->buyer->id,
                'name' => $booking->buyer->name,
                'email' => $booking->buyer->email,
                'phone_number' => $booking->buyer->phone_number,
                'avatar' => $booking->buyer->avatar,
                'avatar_url' => $booking->buyer->avatar ? asset('storage/'.$booking->buyer->avatar) : null,
            ] : null,
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

        if ($includePaymentDetails) {
            $data = array_merge([
                'id' => $data['id'],
                'service_id' => $data['service_id'],
                'buyer_id' => $data['buyer_id'],
                'seller_id' => $data['seller_id'],
                'status' => $data['status'],
                // 'payment_method' => $booking->payment_method,
                'payment_method' => $this->paymentMethodName($booking->payment_method),
                'payment_status' => $data['payment_status'],
                'payment_proof' => $booking->payment_proof,
                'payment_proof_url' => $booking->payment_proof ? asset('storage/'.$booking->payment_proof) : null,
                'buyer_accepted_at' => $booking->buyer_accepted_at,
                'paid_at' => $booking->paid_at,
            ], array_diff_key($data, array_flip([
                'id',
                'service_id',
                'buyer_id',
                'seller_id',
                'status',
                'payment_status',
            ])));
        }

        return $data;
    }

    private function formatEarningBooking(Booking $booking): array
    {
        return [
            'booking_id' => $booking->id,
            'amount' => (float) ($booking->service->price ?? 0),
            'payment_method' => $booking->payment_method,
            'payment_method_name' => $this->paymentMethodName($booking->payment_method),
            'payment_status' => $booking->payment_status ?? 'unpaid',
            'payment_proof' => $booking->payment_proof,
            'payment_proof_url' => $booking->payment_proof ? asset('storage/'.$booking->payment_proof) : null,
            'status' => $booking->status,
            'completed_at' => $booking->updated_at,
            'paid_at' => $booking->paid_at,
            'buyer' => $booking->buyer ? [
                'id' => $booking->buyer->id,
                'name' => $booking->buyer->name,
                'email' => $booking->buyer->email,
            ] : null,
            'service' => $booking->service ? [
                'id' => $booking->service->id,
                'title' => $booking->service->title,
                'price' => $booking->service->price,
            ] : null,
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

    private function formatServiceDetails(Service $service): array
    {
        return [
            'id' => $service->id,
            'user_id' => $service->user_id,
            'seller' => $service->user ? [
                'id' => $service->user->id,
                'name' => $service->user->name,
                'email' => $service->user->email,
                'role' => $service->user->getRoleNames()->first() ?? 'seller',
                'avatar' => $service->user->avatar,
                'avatar_url' => $service->user->avatar ? asset('storage/'.$service->user->avatar) : null,
                'cover_photo' => $service->user->cover_photo,
                'cover_photo_url' => $service->user->cover_photo ? asset('storage/'.$service->user->cover_photo) : null,
                'bio' => $service->user->bio,
            ] : null,
            'category' => $service->category ? [
                'id' => $service->category->id,
                'name' => $service->category->name,
            ] : null,
            'title' => $service->title,
            'description' => $service->description,
            'price' => $service->price,
            'estimated_days' => $service->estimated_days,
            'tags' => $service->tags ?? [],
            'image' => $service->image,
            'image_url' => $service->image ? asset('storage/'.$service->image) : null,
            'average_rating' => round($service->reviews->avg('rating'), 1) ?: 0,
            'total_reviews' => $service->reviews->count(),
            'reviews' => $service->reviews
                ->sortByDesc('created_at')
                ->map(fn ($review) => [
                    'id' => $review->id,
                    'booking_id' => $review->booking_id,
                    'buyer' => $review->buyer ? [
                        'id' => $review->buyer->id,
                        'name' => $review->buyer->name,
                        'email' => $review->buyer->email,
                    ] : null,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at,
                    'updated_at' => $review->updated_at,
                ])
                ->values(),
            'is_saved' => SavedService::where('buyer_id', Auth::id())
                ->where('service_id', $service->id)
                ->exists(),
            'created_at' => $service->created_at,
            'updated_at' => $service->updated_at,
        ];
    }

    private function normalizeTags(array $tags): array
    {
        return collect($tags)
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
