<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Services\DeliveryCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(
        protected DeliveryCalculatorService $calculator
    ) {}

    /**
     * Calculate delivery fee based on Karawang GPS coordinates, road distance, and commodity weight.
     */
    public function calculateFee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'origin_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'origin_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
        ]);

        $cartItems = $validated['items'] ?? null;

        // If items are not provided explicitly in payload, fetch from authenticated user's cart
        if (empty($cartItems) && $request->user()) {
            $cartItems = Cart::query()
                ->where('buyer_id', $request->user()->id)
                ->with('product')
                ->get();
        }

        if (empty($cartItems)) {
            $cartItems = [];
        }

        $result = $this->calculator->calculate(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $cartItems,
            isset($validated['origin_latitude']) ? (float) $validated['origin_latitude'] : null,
            isset($validated['origin_longitude']) ? (float) $validated['origin_longitude'] : null
        );

        return response()->json([
            'message' => 'Kalkulasi ongkos kirim berhasil dihitung.',
            'data' => $result,
        ]);
    }
}
