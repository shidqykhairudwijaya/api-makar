<?php

namespace App\Services;

use App\Enums\ProductUnit;
use App\Models\Product;

class DeliveryCalculatorService
{
    /**
     * Default logistics central hub in Karawang.
     */
    public const DEFAULT_HUB = [
        'name' => 'Pasar Johar Karawang (Hub Logistik Pusat)',
        'latitude' => -6.304000,
        'longitude' => 107.306000,
    ];

    /**
     * Calculate comprehensive delivery fee breakdown.
     *
     * @param  array{latitude: float, longitude: float, origin_latitude?: float|null, origin_longitude?: float|null, items?: array<int, array{product_id: int, quantity: int}>|null}  $params
     * @return array<string, mixed>
     */
    public function calculate(array $params): array
    {
        $destLat = (float) $params['latitude'];
        $destLng = (float) $params['longitude'];

        $originLat = isset($params['origin_latitude']) && $params['origin_latitude'] !== null
            ? (float) $params['origin_latitude']
            : self::DEFAULT_HUB['latitude'];

        $originLng = isset($params['origin_longitude']) && $params['origin_longitude'] !== null
            ? (float) $params['origin_longitude']
            : self::DEFAULT_HUB['longitude'];

        $hubName = self::DEFAULT_HUB['name'];

        // 1. Calculate Geodesic Distance via Haversine Formula
        $distanceKm = $this->calculateHaversineDistance($originLat, $originLng, $destLat, $destLng);
        $distanceKm = max(1.0, round($distanceKm, 1));

        // 2. Calculate Estimated Weight & Stall Count from Items
        $items = $params['items'] ?? [];
        $weightInfo = $this->calculateWeightAndStalls($items);
        $estimatedWeightKg = $weightInfo['weight_kg'];
        $stallCount = $weightInfo['stall_count'];

        // 3. Tariff Components
        $baseFee = 10000; // Base fee for up to 3 km

        // Distance fee: Rp 2.500 per km after first 3 km
        $distanceFee = $distanceKm > 3.0
            ? (int) (ceil($distanceKm - 3.0) * 2500)
            : 0;

        // Weight fee: Rp 1.000 per kg above 5 kg threshold
        $weightFee = $estimatedWeightKg > 5.0
            ? (int) (ceil($estimatedWeightKg - 5.0) * 1000)
            : 0;

        // Multi-stall fee: Rp 3.000 per additional stall pickup
        $multiStallFee = $stallCount > 1
            ? (int) (($stallCount - 1) * 3000)
            : 0;

        $totalFee = $baseFee + $distanceFee + $weightFee + $multiStallFee;

        return [
            'distance_km' => $distanceKm,
            'estimated_weight_kg' => round($estimatedWeightKg, 1),
            'stall_count' => $stallCount,
            'base_fee' => $baseFee,
            'distance_fee' => $distanceFee,
            'weight_fee' => $weightFee,
            'multi_stall_fee' => $multiStallFee,
            'total_delivery_fee' => $totalFee,
            'formatted_total_fee' => 'Rp '.number_format((float) $totalFee, 0, ',', '.'),
            'routing_source' => 'haversine_osrm_karawang',
            'hub' => [
                'name' => $hubName,
                'latitude' => $originLat,
                'longitude' => $originLng,
            ],
            'destination' => [
                'latitude' => $destLat,
                'longitude' => $destLng,
            ],
        ];
    }

    /**
     * Compute Haversine distance in kilometers between two GPS coordinates.
     */
    protected function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    /**
     * Determine estimated commodity weight and stall count.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @return array{weight_kg: float, stall_count: int}
     */
    protected function calculateWeightAndStalls(array $items): array
    {
        if (empty($items)) {
            return ['weight_kg' => 1.0, 'stall_count' => 1];
        }

        $productIds = array_column($items, 'product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $totalWeight = 0.0;
        $sellerIds = [];

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 1);
            $product = $products->get($productId);

            if ($product) {
                $sellerIds[$product->seller_id] = true;
                $multiplier = match ($product->unit) {
                    ProductUnit::Kg => 1.0,
                    ProductUnit::Karung => 25.0,
                    ProductUnit::Peti => 10.0,
                    ProductUnit::Ikat => 0.5,
                    default => 1.0,
                };
                $totalWeight += $quantity * $multiplier;
            } else {
                $totalWeight += $quantity * 1.0;
            }
        }

        return [
            'weight_kg' => max(1.0, $totalWeight),
            'stall_count' => max(1, count($sellerIds)),
        ];
    }
}
