<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
Use App\Notifications\AppNotification;

class ServiceController extends Controller
{
    //
    public function index(Request $request)
    {
        $query = Service::with('reviews');
        if ($request->has('search')){
            $search = $request->query('search');
            $query->where(function($q) use ($search){
                $q->where('title', 'LIKE', "%{$search}%")->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if($request->has('max_price')){
            $query->where('price', '<=', $request->query('max_price'));
        }
        $services= $query->get();
        //$services = Service::with('reviews')->get();
        $services->transform(function($service){
            $service->average_rating= round($service->reviews->avg('rating'), 1) ?: 0;
            $service->total_reviews = $service->reviews->count();
            return $service;
        });
        return response()->json($services, 200);
    }

    public function store(Request $request)
    {
        if(!auth()->user()->hasRole('seller')){
            return response()->json([
                'status' =>'error',
                'message' => 'Only sellers are allowed to post services. '
            ], 403);
        }
        $request->validate([
            'title' => 'required|string',
            'description' => 'required',
            'price' => 'required|numeric',
            'estimated_days' => 'required|string',
        ]);

        $service = Service::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'estimated_days' => $request->estimated_days,
        ]);

        return response()->json(['message' => 'Service create successful..', 'service' => $service]);
    }

    public function update(Request $request, $id)
    {
        $service = Service::where('user_id', Auth::id())->findOrFail($id);
        $service->update($request->all());

        return response()->json(['message' => 'Service updated successful.', 'service' => $service]);
    }

    public function destroy($id)
    {
        $service = Service::where('user_id', Auth::id())->findOrFail($id);
        $service->delete();

        return response()->json(['message' => 'Service deleted successful.']);
    }

    public function changeBookingStatus(Request $request, $id)
    {
    $request->validate([
        'status' => 'required|in:in_progress,completed,cancelled'
    ]);

    $booking = Booking::where('seller_id', auth()->id())->findOrFail($id);

    $booking->status = $request->status;
    $booking->save();

    $buyer = User::find($booking->buyer_id);
    if ($buyer){
        $statusText = str_replace('_', ' ', $request->status);
        $notiMessage = "Your booking status has been updated to" . $statusText . "by the seller.";
        $buyer->notify(new AppNotification($notiMessage));
    }

    return response()->json([
        'message' => 'Status have been changed.',
        'booking' => $booking
    ]);
    }

    public function topRated(){
        $services = Service::with('reviews')->get();
        $services->transform(function($service){
            $service->average_rating = round($service->reviews->avg('rating'), 1) ?: 0;
            $service->total_reviews = $service->reviews->count();
            return $service;
        });

        $topServices = $services->sortByDesc('average_rating')->take(5)->values();
        return response()->json($topServices, 200);
    }
}
