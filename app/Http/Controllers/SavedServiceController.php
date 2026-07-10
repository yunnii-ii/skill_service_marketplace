<?php

namespace App\Http\Controllers;

use App\Models\SavedService;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SavedServiceController extends Controller
{
    //view save services
    public function index()
    {
        if (! Auth::user()->hasRole('buyer')) {
            return response()->json([
                'success' => false,
                'message' => 'Only buyer can view saved services.',
            ], 403);
        }

        $savedServices = SavedService::with(['service.user', 'service.category'])
            ->where('buyer_id', Auth::id())
            ->latest()
            ->get()
            ->map(fn ($savedService) => $this->formatSavedService($savedService));

        return response()->json([
            'success' => true,
            'data' => $savedServices,
        ]);
    }

    //service save
    public function store(Request $request)
    {
        if (! Auth::user()->hasRole('buyer')) {
            return response()->json([
                'success' => false,
                'message' => 'Only buyer can save services.',
            ], 403);
        }

        $request->validate([
            'service_id' => 'required|exists:services,id',
        ]);

        $service = Service::findOrFail($request->service_id);

        if ((int) $service->user_id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot save your own service.',
            ], 400);
        }

        $savedService = SavedService::firstOrCreate([
            'buyer_id' => Auth::id(),
            'service_id' => $service->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => $savedService->wasRecentlyCreated
                ? 'Service saved successfully.'
                : 'Service is already saved.',
            'data' => $this->formatSavedService($savedService->load(['service.user', 'service.category'])),
        ], $savedService->wasRecentlyCreated ? 201 : 200);
    }

    //remove saved service
    public function destroy(Request $request)
    {
        if (! Auth::user()->hasRole('buyer')) {
            return response()->json([
                'success' => false,
                'message' => 'Only buyer can remove saved services.',
            ], 403);
        }

        $request->validate([
            'service_id' => 'required|exists:services,id',
        ]);

        $deleted = SavedService::where('buyer_id', Auth::id())
            ->where('service_id', $request->service_id)
            ->delete();

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'This service is not in your saved list.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Saved service removed successfully.',
        ]);
    }

    private function formatSavedService(SavedService $savedService): array
    {
        $service = $savedService->service;

        return [
            'id' => $savedService->id,
            'service_id' => $savedService->service_id,
            'saved_at' => $savedService->created_at,
            'service' => $service ? [
                'id' => $service->id,
                'user_id' => $service->user_id,
                'seller' => $service->user ? [
                    'id' => $service->user->id,
                    'name' => $service->user->name,
                    'email' => $service->user->email,
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
                'created_at' => $service->created_at,
                'updated_at' => $service->updated_at,
            ] : null,
        ];
    }
}
