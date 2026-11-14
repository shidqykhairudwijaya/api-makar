<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Services\DeliveryCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * Detect user's current location via IP geolocation and user profile fallback.
     */
    public function detectLocation(Request $request): JsonResponse
    {
        // 1. Check if user is authenticated and has saved profile coordinates
        if ($request->user() && $request->user()->profile && $request->user()->profile->latitude && $request->user()->profile->longitude) {
            return response()->json([
                'success' => true,
                'source' => 'user_profile',
                'latitude' => (float) $request->user()->profile->latitude,
                'longitude' => (float) $request->user()->profile->longitude,
                'name' => $request->user()->profile->store_name ?: $request->user()->name,
                'display_name' => $request->user()->profile->full_address ?: 'Lokasi Tersimpan di Profil Akun',
            ]);
        }

        // 2. Try IP Geolocation (instant, no browser permission needed)
        try {
            $ip = $request->ip();
            $targetUrl = ($ip && ! in_array($ip, ['127.0.0.1', '::1'], true))
                ? "http://ip-api.com/json/{$ip}?fields=status,country,regionName,city,lat,lon"
                : 'http://ip-api.com/json/?fields=status,country,regionName,city,lat,lon';

            $response = Http::withoutVerifying()->timeout(3.0)->get($targetUrl);

            if ($response->successful() && $response->json('status') === 'success') {
                $lat = (float) $response->json('lat');
                $lon = (float) $response->json('lon');
                $city = (string) $response->json('city');
                $region = (string) $response->json('regionName');

                return response()->json([
                    'success' => true,
                    'source' => 'ip_geolocation',
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'name' => "Koneksi Jaringan ({$city})",
                    'display_name' => "Terdeteksi dari jaringan internet Anda: {$city}, {$region}",
                ]);
            }
        } catch (\Throwable $e) {
            Log::info('IP Geolocation lookup exception: '.$e->getMessage());
        }

        // 3. Fallback: Pusat Wilayah Karawang (Sekitar UNSIKA Kampus 1)
        return response()->json([
            'success' => true,
            'source' => 'karawang_center',
            'latitude' => -6.326200,
            'longitude' => 107.304500,
            'name' => 'Pusat Wilayah Karawang (Sekitar UNSIKA)',
            'display_name' => 'Jl. HS. Ronggo Waluyo, Telukjambe Timur, Karawang Barat',
        ]);
    }

    /**
     * Search location points in Karawang using curated local dataset + Nominatim OpenStreetMap.
     */
    public function searchLocation(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if (strlen($query) < 2) {
            return response()->json([
                'data' => [],
            ]);
        }

        $results = [];
        $lowerQ = strtolower($query);

        // 1. Comprehensive Curated Karawang Database (Instant, 0ms, matches malls, markets, perumahan, campuses)
        $karawangPois = [
            // Malls & Shopping Centers
            ['name' => 'Mall Karawang Central Plaza (KCP)', 'aliases' => ['mall kcp', 'kcp', 'karawang central plaza', 'plaza kcp'], 'display_name' => 'Galuh Mas, Sukaluyu, Telukjambe Timur, Karawang, Jawa Barat', 'latitude' => -6.327611, 'longitude' => 107.292475],
            ['name' => 'Mall Technomart Karawang', 'aliases' => ['technomart', 'tekno mart', 'mall tekno'], 'display_name' => 'Kawasan Galuh Mas, Sukaharja, Telukjambe Timur, Karawang', 'latitude' => -6.326800, 'longitude' => 107.293300],
            ['name' => 'Festive Walk Galuh Mas', 'aliases' => ['festive walk', 'festive', 'mall festive'], 'display_name' => 'Jl. Galuh Mas Raya, Sukaharja, Telukjambe Timur, Karawang', 'latitude' => -6.327000, 'longitude' => 107.293900],
            ['name' => 'Resinda Park Mall', 'aliases' => ['resinda mall', 'mall resinda', 'resinda park', 'rpm'], 'display_name' => 'Jl. Resinda Raya No. 2, Purwadana, Telukjambe Timur, Karawang', 'latitude' => -6.301598, 'longitude' => 107.278341],
            ['name' => 'Ramayana Mall Karawang', 'aliases' => ['ramayana', 'mall ramayana'], 'display_name' => 'Jl. Tuparev No. 15, Nagasari, Karawang Barat', 'latitude' => -6.305200, 'longitude' => 107.300600],
            ['name' => 'Mega M Karawang', 'aliases' => ['mega m', 'mall mega m'], 'display_name' => 'Jl. Ahmad Yani No. 1, Nagasari, Karawang Barat', 'latitude' => -6.307500, 'longitude' => 107.304000],

            // Pasar Tradisional
            ['name' => 'Pasar Johar Karawang (Hub Induk)', 'aliases' => ['pasar johar', 'johar', 'pasar johar karawang'], 'display_name' => 'Jl. Tujuh Pahlawan Revolusi, Adiarsa Timur, Karawang Timur', 'latitude' => -6.303200, 'longitude' => 107.310500],
            ['name' => 'Pasar Baru Karawang', 'aliases' => ['pasar baru', 'pasar baru karawang'], 'display_name' => 'Jl. Tujuh Pahlawan Revolusi, Nagasari, Karawang Barat', 'latitude' => -6.309270, 'longitude' => 107.294961],
            ['name' => 'Pasar Rengasdengklok', 'aliases' => ['pasar dengklok', 'rengasdengklok'], 'display_name' => 'Pusat Pasar Kecamatan Rengasdengklok, Karawang Utara', 'latitude' => -6.158000, 'longitude' => 107.298000],
            ['name' => 'Pasar Cikampek', 'aliases' => ['pasar cikampek', 'cikampek'], 'display_name' => 'Jl. Stasiun Cikampek, Cikampek Kota, Karawang Timur', 'latitude' => -6.421500, 'longitude' => 107.458000],
            ['name' => 'Pasar Kosambi Karawang', 'aliases' => ['pasar kosambi', 'kosambi'], 'display_name' => 'Duren, Klari, Karawang, Jawa Barat', 'latitude' => -6.347000, 'longitude' => 107.368000],
            ['name' => 'Pasar Bersih Galuh Mas', 'aliases' => ['pasar bersih', 'pasar galuh mas'], 'display_name' => 'Kawasan Mandiri Galuh Mas, Sukaharja, Karawang Barat', 'latitude' => -6.324500, 'longitude' => 107.295200],

            // Perumahan & Pemukiman Populer Karawang
            ['name' => 'Perumahan Galuh Mas Karawang', 'aliases' => ['galuh mas', 'perumahan galuh mas', 'cluster galuh mas'], 'display_name' => 'Kawasan Mandiri Galuh Mas, Telukjambe Timur, Karawang Barat', 'latitude' => -6.319000, 'longitude' => 107.298000],
            ['name' => 'Perumahan Grand Taruma Karawang', 'aliases' => ['grand taruma', 'taruma', 'perumahan grand taruma'], 'display_name' => 'Jl. Tarumanagara, Sukamakmur, Karawang Barat', 'latitude' => -6.307389, 'longitude' => 107.282489],
            ['name' => 'Perumahan Resinda Karawang', 'aliases' => ['resinda', 'perumahan resinda', 'cluster resinda'], 'display_name' => 'Jl. Boulevard Resinda, Purwadana, Telukjambe Timur, Karawang', 'latitude' => -6.294757, 'longitude' => 107.269768],
            ['name' => 'Perumahan Puri Telukjambe', 'aliases' => ['puri telukjambe', 'puri', 'perum puri'], 'display_name' => 'Sirnabaya, Telukjambe Timur, Karawang', 'latitude' => -6.338000, 'longitude' => 107.302000],
            ['name' => 'Perumahan Karaba Indah', 'aliases' => ['karaba indah', 'karaba', 'perumahan karaba'], 'display_name' => 'Wadas, Telukjambe Timur, Karawang Barat', 'latitude' => -6.321000, 'longitude' => 107.288000],
            ['name' => 'Perumahan Mahkota Regency', 'aliases' => ['mahkota regency', 'mahkota'], 'display_name' => 'Sirnabaya, Telukjambe Timur (Dekat Kampus UNSIKA), Karawang', 'latitude' => -6.328000, 'longitude' => 107.315000],
            ['name' => 'Perumahan Bintang Alam', 'aliases' => ['bintang alam', 'perumahan bintang alam'], 'display_name' => 'Telukjambe, Telukjambe Timur, Karawang', 'latitude' => -6.332000, 'longitude' => 107.312000],
            ['name' => 'Perumahan Citra Swarna Grande', 'aliases' => ['citra swarna', 'grande klari', 'citra swarna grande'], 'display_name' => 'Pancawati, Klari, Karawang', 'latitude' => -6.342000, 'longitude' => 107.351000],
            ['name' => 'Perumahan Green Garden Karawang', 'aliases' => ['green garden', 'perum green garden'], 'display_name' => 'Nagasari, Karawang Barat, Karawang', 'latitude' => -6.308000, 'longitude' => 107.318000],
            ['name' => 'Perumahan Griya Indah Karawang', 'aliases' => ['griya indah', 'perumahan griya indah'], 'display_name' => 'Kondangjaya, Karawang Timur, Karawang', 'latitude' => -6.315000, 'longitude' => 107.322000],
            ['name' => 'Perumahan Rolling Hills Karawang', 'aliases' => ['rolling hills', 'rolling hills karawang'], 'display_name' => 'Kawasan Karawang Barat Interchange, Sukamakmur, Karawang', 'latitude' => -6.335000, 'longitude' => 107.281000],
            ['name' => 'Perumahan Parkland Podomoro Karawang', 'aliases' => ['podomoro karawang', 'parkland podomoro'], 'display_name' => 'Jl. International Interchange Karawang Barat, Wadas, Telukjambe Timur', 'latitude' => -6.341000, 'longitude' => 107.285000],

            // Kampus & Pendidikan
            ['name' => 'Universitas Singaperbangsa Karawang (UNSIKA Kampus 1)', 'aliases' => ['unsika', 'kampus unsika', 'universitas singaperbangsa karawang'], 'display_name' => 'Jl. HS. Ronggo Waluyo, Puseurjaya, Telukjambe Timur, Karawang', 'latitude' => -6.322926, 'longitude' => 107.306229],
            ['name' => 'UNSIKA Kampus 2 Karawang', 'aliases' => ['unsika 2', 'kampus 2 unsika'], 'display_name' => 'Jl. Lingkar Luar Tanjungpura, Karawang Barat', 'latitude' => -6.345000, 'longitude' => 107.308000],
            ['name' => 'Politeknik Kelautan dan Perikanan Karawang', 'aliases' => ['poltek kp', 'politeknik kp'], 'display_name' => 'Jl. Baru Tanjungpura, Karawang Barat', 'latitude' => -6.331000, 'longitude' => 107.304000],
            ['name' => 'STIKes Kharisma Karawang', 'aliases' => ['stikes kharisma', 'kharisma'], 'display_name' => 'Jl. Pangkal Perjuangan, Karawang Barat', 'latitude' => -6.318000, 'longitude' => 107.292000],

            // Rumah Sakit & Fasilitas Kesehatan
            ['name' => 'RSUD Karawang', 'aliases' => ['rsud karawang', 'rsud'], 'display_name' => 'Jl. Galuh Mas Raya No. 1, Sukaharja, Telukjambe Timur, Karawang', 'latitude' => -6.333000, 'longitude' => 107.306000],
            ['name' => 'RS Mandaya Karawang', 'aliases' => ['rs mandaya', 'mandaya'], 'display_name' => 'Jl. Arteri Tol Karawang Barat, Sukamakmur, Telukjambe Timur', 'latitude' => -6.312000, 'longitude' => 107.285000],
            ['name' => 'RS Primaya Karawang', 'aliases' => ['rs primaya', 'primaya'], 'display_name' => 'Kavling Komersial Galuh Mas, Telukjambe Timur, Karawang', 'latitude' => -6.328500, 'longitude' => 107.294500],
            ['name' => 'RS Lira Medika', 'aliases' => ['rs lira medika', 'lira medika'], 'display_name' => 'Jl. Surotokunto No. 201, Rawagabus, Karawang Timur', 'latitude' => -6.314000, 'longitude' => 107.332000],

            // Landmark, Stasiun & Jalan Utama
            ['name' => 'Alun-Alun Karawang', 'aliases' => ['alun-alun', 'alun alun karawang'], 'display_name' => 'Jl. Alun-Alun Barat, Karawang Kulon, Karawang Barat', 'latitude' => -6.305500, 'longitude' => 107.298500],
            ['name' => 'Stasiun Karawang', 'aliases' => ['stasiun karawang', 'stasiun kota'], 'display_name' => 'Nagasari, Karawang Barat, Karawang', 'latitude' => -6.303800, 'longitude' => 107.301500],
            ['name' => 'Terminal Klari Karawang', 'aliases' => ['terminal klari', 'klari'], 'display_name' => 'Jl. Raya Klari, Duren, Klari, Karawang', 'latitude' => -6.345000, 'longitude' => 107.360000],
            ['name' => 'Kawasan Industri KIIC Karawang', 'aliases' => ['kiic', 'kawasan kiic'], 'display_name' => 'Karawang International Industrial City, Telukjambe Barat', 'latitude' => -6.357000, 'longitude' => 107.262000],
            ['name' => 'Kawasan Industri Suryacipta', 'aliases' => ['suryacipta', 'surya cipta'], 'display_name' => 'Jl. Surya Utama, Ciampel, Karawang', 'latitude' => -6.385000, 'longitude' => 107.345000],
            ['name' => 'Jl. Tuparev Karawang', 'aliases' => ['tuparev', 'jl tuparev', 'jalan tuparev'], 'display_name' => 'Jl. Tuparev, Pusat Niaga Karawang Kota, Jawa Barat', 'latitude' => -6.305000, 'longitude' => 107.305000],
            ['name' => 'Jl. HS. Ronggo Waluyo', 'aliases' => ['ronggo waluyo', 'ronggowaluyo', 'jl hs ronggowaluyo'], 'display_name' => 'Jl. HS. Ronggo Waluyo, Koridor Kampus UNSIKA, Karawang', 'latitude' => -6.325000, 'longitude' => 107.305500],
        ];

        // Search local curated Karawang data first
        foreach ($karawangPois as $poi) {
            $matched = false;
            if (str_contains(strtolower($poi['name']), $lowerQ) || str_contains(strtolower($poi['display_name']), $lowerQ)) {
                $matched = true;
            } else {
                foreach ($poi['aliases'] as $alias) {
                    if (str_contains($alias, $lowerQ) || str_contains($lowerQ, $alias)) {
                        $matched = true;
                        break;
                    }
                }
            }

            if ($matched) {
                $results[] = [
                    'name' => $poi['name'],
                    'display_name' => $poi['display_name'],
                    'latitude' => (float) $poi['latitude'],
                    'longitude' => (float) $poi['longitude'],
                ];
            }
        }

        // 2. Query Nominatim OpenStreetMap (Free OSM Global API with SSL verification disabled on Windows local dev)
        try {
            $searchTerms = str_contains($lowerQ, 'karawang') ? $query : "{$query} Karawang";
            $url = 'https://nominatim.openstreetmap.org/search?format=json&q='.urlencode($searchTerms).'&countrycodes=id&limit=8';

            $response = Http::withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'MarmaMarketplace/1.0 (contact: admin@makar.id)',
                ])
                ->timeout(3.0)
                ->get($url);

            if ($response->successful()) {
                $raw = $response->json();
                foreach ($raw as $item) {
                    $lat = (float) $item['lat'];
                    $lon = (float) $item['lon'];

                    // Deduplicate against existing results by proximity (< 200m)
                    $exists = false;
                    foreach ($results as $r) {
                        if (abs($r['latitude'] - $lat) < 0.002 && abs($r['longitude'] - $lon) < 0.002) {
                            $exists = true;
                            break;
                        }
                    }

                    if (! $exists) {
                        $name = $item['name'] ?: explode(',', (string) $item['display_name'])[0];
                        $results[] = [
                            'name' => $name,
                            'display_name' => $item['display_name'],
                            'latitude' => $lat,
                            'longitude' => $lon,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::info('Nominatim geocoding error: '.$e->getMessage());
        }

        return response()->json([
            'data' => array_slice($results, 0, 10),
        ]);
    }
}
