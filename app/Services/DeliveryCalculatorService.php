<?php

namespace App\Services;

use App\Enums\ProductUnit;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliveryCalculatorService
{
    // Default wholesale produce hub: Pasar Johar Karawang
    public const DEFAULT_HUB_LAT = -6.30320000;
    public const DEFAULT_HUB_LNG = 107.31050000;

    /**
     * Calculate comprehensive delivery fee for market commodities in Karawang.
     *
     * @param  float  $destLat
     * @param  float  $destLng
     * @param  Collection|array  $cartItems (Collection of Cart models or array with product_id & quantity)
     * @param  float|null  $originLat
     * @param  float|null  $originLng
     * @return array
     */
    public function calculate(
        float $destLat,
        float $destLng,
        $cartItems,
        ?float $originLat = null,
        ?float $originLng = null
    ): array {
        $originLat = $originLat ?? self::DEFAULT_HUB_LAT;
        $originLng = $originLng ?? self::DEFAULT_HUB_LNG;

        // 1. Calculate road distance via OSRM with Haversine fallback
        $distanceKm = $this->calculateRoadDistance($originLat, $originLng, $destLat, $destLng, $routingSource);

        // 2. Calculate total estimated weight & unique stall count
        $estimatedWeightKg = 0.0;
        $sellerIds = [];

        foreach ($cartItems as $item) {
            $quantity = is_array($item) ? ($item['quantity'] ?? 1) : $item->quantity;
            $product = null;

            if (is_array($item)) {
                $product = isset($item['product']) ? (object) $item['product'] : Product::find($item['product_id'] ?? null);
            } else {
                $product = $item->product ?? Product::find($item->product_id);
            }

            if ($product) {
                $unitValue = is_string($product->unit) ? $product->unit : ($product->unit?->value ?? 'kg');
                $unitWeight = match ($unitValue) {
                    ProductUnit::Ikat->value, 'ikat' => 0.8,
                    ProductUnit::Peti->value, 'peti' => 15.0,
                    ProductUnit::Karung->value, 'karung' => 30.0,
                    default => 1.0, // 'kg' or default
                };

                $estimatedWeightKg += ($unitWeight * $quantity);

                if (! empty($product->seller_id)) {
                    $sellerIds[$product->seller_id] = true;
                }
            }
        }

        $stallCount = max(1, count($sellerIds));

        // 3. Tarif Komponen
        $baseFee = 8000; // Tarif dasar mencakup 0 - 2 km
        $distanceFee = 0;
        if ($distanceKm > 2.0) {
            $distanceFee = (int) round(($distanceKm - 2.0) * 2000);
        }

        // Surcharge Bobot Karawang
        $weightFee = 0;
        if ($estimatedWeightKg > 80.0) {
            $weightFee = 35000; // Muatan besar (diarahkan ke armada pick-up / Viar roda 3)
        } elseif ($estimatedWeightKg > 40.0) {
            $weightFee = 15000; // Bronjong berat
        } elseif ($estimatedWeightKg > 15.0) {
            $weightFee = 5000;  // Bronjong sedang
        }

        // Insentif Multi-Kios Pasar Induk
        $multiStallFee = ($stallCount > 1) ? (($stallCount - 1) * 2500) : 0;

        $totalFee = $baseFee + $distanceFee + $weightFee + $multiStallFee;

        return [
            'distance_km' => round($distanceKm, 2),
            'estimated_weight_kg' => round($estimatedWeightKg, 1),
            'stall_count' => $stallCount,
            'base_fee' => $baseFee,
            'distance_fee' => $distanceFee,
            'weight_fee' => $weightFee,
            'multi_stall_fee' => $multiStallFee,
            'total_delivery_fee' => (int) $totalFee,
            'formatted_total_fee' => 'Rp '.number_format((float) $totalFee, 0, ',', '.'),
            'routing_source' => $routingSource,
            'hub' => [
                'name' => 'Pasar Johar Karawang (Hub Induk)',
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
     * Compute road distance using OSRM with automatic Haversine fallback.
     */
    protected function calculateRoadDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        ?string &$source = null
    ): float {
        // Validation coordinates
        if ($lat1 === $lat2 && $lon1 === $lon2) {
            $source = 'same_point';
            return 0.5;
        }

        try {
            $url = sprintf(
                'https://router.project-osrm.org/route/v1/driving/%F,%F;%F,%F?overview=false',
                $lon1,
                $lat1,
                $lon2,
                $lat2
            );

            $response = Http::timeout(2.5)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                if (! empty($data['routes'][0]['distance'])) {
                    $source = 'osrm_live';
                    return max(0.5, (float) ($data['routes'][0]['distance'] / 1000.0));
                }
            }
        } catch (\Throwable $e) {
            Log::info('OSRM request timed out or unavailable, using Karawang road-adjusted Haversine: '.$e->getMessage());
        }

        // Fallback: Haversine distance with 1.35x road curvature factor for Karawang
        $source = 'haversine_road_adjusted';
        return $this->haversineKm($lat1, $lon1, $lat2, $lon2) * 1.35;
    }

    /**
     * Standard Great Circle / Haversine formula in Kilometers.
     */
    protected function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return max(0.5, $earthRadius * $c);
    }
}
