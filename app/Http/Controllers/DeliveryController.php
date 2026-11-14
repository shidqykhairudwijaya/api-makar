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

    /**
     * Search location points in Karawang using Nominatim OSM with local fallback.
     */
    public function searchLocation(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if (strlen($query) < 2) {
            return response()->json([
                'data' => [],
            ]);
        }

        $searchTerms = str_contains(strtolower($query), 'karawang') ? $query : "{$query} Karawang";
        $results = [];

        try {
            $url = 'https://nominatim.openstreetmap.org/search?format=json&q='.urlencode($searchTerms).'&countrycodes=id&limit=6';
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'MarmaMarketplace/1.0 (contact: admin@makar.id)',
            ])->timeout(3.5)->get($url);

            if ($response->successful()) {
                $raw = $response->json();
                foreach ($raw as $item) {
                    $results[] = [
                        'name' => $item['name'] ?: $item['display_name'],
                        'display_name' => $item['display_name'],
                        'latitude' => (float) $item['lat'],
                        'longitude' => (float) $item['lon'],
                    ];
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Nominatim geocoding error: '.$e->getMessage());
        }

        if (empty($results)) {
            $localPoints = [
                ['name' => 'Universitas Singaperbangsa Karawang (UNSIKA)', 'display_name' => 'Jl. HS. Ronggo Waluyo, Puseurjaya, Telukjambe Timur, Karawang', 'latitude' => -6.3229, 'longitude' => 107.3062],
                ['name' => 'Perumahan Galuh Mas Karawang', 'display_name' => 'Kawasan Mandiri Galuh Mas, Telukjambe Timur, Karawang Barat', 'latitude' => -6.3190, 'longitude' => 107.2980],
                ['name' => 'Perumahan Grand Taruma Karawang', 'display_name' => 'Jl. Tarumanagara, Sukamakmur, Karawang Barat', 'latitude' => -6.3074, 'longitude' => 107.2825],
                ['name' => 'Pasar Johar Karawang (Hub Induk)', 'display_name' => 'Jl. Tuparev No. 12, Adiarsa Timur, Karawang Timur', 'latitude' => -6.3032, 'longitude' => 107.3105],
                ['name' => 'Perumahan Puri Telukjambe', 'display_name' => 'Sirnabaya, Telukjambe Timur, Karawang', 'latitude' => -6.3380, 'longitude' => 107.3020],
                ['name' => 'Kawasan Industri KIIC', 'display_name' => 'Karawang International Industrial City, Telukjambe Barat', 'latitude' => -6.3570, 'longitude' => 107.2620],
                ['name' => 'Terminal Klari Karawang', 'display_name' => 'Jl. Raya Klari, Duren, Klari, Karawang', 'latitude' => -6.3450, 'longitude' => 107.3600],
                ['name' => 'Rengasdengklok Karawang', 'display_name' => 'Pusat Kecamatan Rengasdengklok, Karawang Utara', 'latitude' => -6.1580, 'longitude' => 107.2980],
                ['name' => 'Cikampek Kota', 'display_name' => 'Cikampek Kota, Karawang Timur', 'latitude' => -6.4210, 'longitude' => 107.4570],
            ];

            $lowerQ = strtolower($query);
            foreach ($localPoints as $point) {
                if (str_contains(strtolower($point['name']), $lowerQ) || str_contains(strtolower($point['display_name']), $lowerQ)) {
                    $results[] = $point;
                }
            }
        }

        return response()->json([
            'data' => $results,
        ]);
    }
}
