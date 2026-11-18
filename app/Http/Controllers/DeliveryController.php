<?php

namespace App\Http\Controllers;

use App\Http\Requests\Delivery\CalculateDeliveryFeeRequest;
use App\Services\DeliveryCalculatorService;
use App\Services\KarawangLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(
        protected DeliveryCalculatorService $calculator,
        protected KarawangLocationService $locationService,
    ) {}

    /**
     * Calculate delivery fee based on GPS coordinates, road distance, and commodity weight.
     */
    public function calculateFee(CalculateDeliveryFeeRequest $request): JsonResponse
    {
        $breakdown = $this->calculator->calculate($request->validated());

        return response()->json([
            'message' => 'Ongkos kirim berhasil dihitung.',
            'data' => $breakdown,
        ]);
    }

    /**
     * Search locations and landmarks in Karawang Regency.
     */
    public function searchLocation(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $results = $this->locationService->search($query);

        return response()->json([
            'data' => $results,
        ]);
    }

    /**
     * Detect user's current reference location via profile or default Karawang hub.
     */
    public function detectLocation(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->load('profile');
        }

        $detected = $this->locationService->detect($user);

        return response()->json($detected);
    }
}
