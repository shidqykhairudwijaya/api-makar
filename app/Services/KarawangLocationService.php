<?php

namespace App\Services;

use App\Models\User;

class KarawangLocationService
{
    /**
     * Catalog of notable locations, landmarks, and districts across Karawang Regency.
     *
     * @var array<int, array{name: string, display_name: string, latitude: float, longitude: float}>
     */
    protected array $landmarks = [
        [
            'name' => 'Pasar Johar Karawang',
            'display_name' => 'Pasar Johar, Jl. Tuparev, Nagasari, Kec. Karawang Barat, Karawang',
            'latitude' => -6.304000,
            'longitude' => 107.306000,
        ],
        [
            'name' => 'Pasar Baru Karawang',
            'display_name' => 'Pasar Baru, Jl. Dr. Tarma, Nagasari, Kec. Karawang Barat, Karawang',
            'latitude' => -6.300500,
            'longitude' => 107.304200,
        ],
        [
            'name' => 'UNSIKA Kampus 1 Telukjambe',
            'display_name' => 'Universitas Singaperbangsa Karawang (UNSIKA), Jl. H.S. Ronggowaluyo, Telukjambe Timur, Karawang',
            'latitude' => -6.326200,
            'longitude' => 107.304500,
        ],
        [
            'name' => 'UNSIKA Kampus 2 Klari',
            'display_name' => 'Universitas Singaperbangsa Karawang Kampus 2, Klari, Karawang',
            'latitude' => -6.347500,
            'longitude' => 107.351200,
        ],
        [
            'name' => 'Galuh Mas Karawang',
            'display_name' => 'Kawasan Niaga Galuh Mas, Sukaharja, Kec. Telukjambe Timur, Karawang',
            'latitude' => -6.321800,
            'longitude' => 107.291700,
        ],
        [
            'name' => 'Grand Taruma Karawang',
            'display_name' => 'Perumahan Grand Taruma, Sukamakmur, Kec. Telukjambe Timur, Karawang',
            'latitude' => -6.302100,
            'longitude' => 107.281500,
        ],
        [
            'name' => 'Resinda Park Mall Karawang',
            'display_name' => 'Resinda Park Mall, Jl. Resinda Raya No. 2, Purwadana, Telukjambe Timur, Karawang',
            'latitude' => -6.299100,
            'longitude' => 107.276400,
        ],
        [
            'name' => 'Alun-Alun Karawang',
            'display_name' => 'Alun-Alun Karawang, Jl. Veteran, Karawang Kulon, Kec. Karawang Barat, Karawang',
            'latitude' => -6.302800,
            'longitude' => 107.301400,
        ],
        [
            'name' => 'Stasiun Kereta Api Karawang',
            'display_name' => 'Stasiun Karawang, Nagasari, Kec. Karawang Barat, Karawang',
            'latitude' => -6.307200,
            'longitude' => 107.301200,
        ],
        [
            'name' => 'Jl. Tuparev Karawang',
            'display_name' => 'Jl. Tuparev, Nagasari, Kec. Karawang Barat, Karawang',
            'latitude' => -6.303100,
            'longitude' => 107.306500,
        ],
        [
            'name' => 'Klari Karawang',
            'display_name' => 'Kecamatan Klari, Kabupaten Karawang, Jawa Barat',
            'latitude' => -6.348200,
            'longitude' => 107.355100,
        ],
        [
            'name' => 'Cikampek Karawang',
            'display_name' => 'Kecamatan Cikampek, Kabupaten Karawang, Jawa Barat',
            'latitude' => -6.417200,
            'longitude' => 107.458900,
        ],
        [
            'name' => 'Rengasdengklok Karawang',
            'display_name' => 'Kecamatan Rengasdengklok, Kabupaten Karawang, Jawa Barat',
            'latitude' => -6.158400,
            'longitude' => 107.298500,
        ],
        [
            'name' => 'Telukjambe Timur Karawang',
            'display_name' => 'Kecamatan Telukjambe Timur, Kabupaten Karawang, Jawa Barat',
            'latitude' => -6.331200,
            'longitude' => 107.301800,
        ],
        [
            'name' => 'Telukjambe Barat Karawang',
            'display_name' => 'Kecamatan Telukjambe Barat, Kabupaten Karawang, Jawa Barat',
            'latitude' => -6.342100,
            'longitude' => 107.251400,
        ],
        [
            'name' => 'Karawang Barat Kota',
            'display_name' => 'Kecamatan Karawang Barat, Kabupaten Karawang, Jawa Barat',
            'latitude' => -6.305100,
            'longitude' => 107.298900,
        ],
        [
            'name' => 'Karawang Timur Kota',
            'display_name' => 'Kecamatan Karawang Timur, Kabupaten Karawang, Jawa Barat',
            'latitude' => -6.315500,
            'longitude' => 107.332100,
        ],
        [
            'name' => 'Kawasan Industri KIIC',
            'display_name' => 'Karawang International Industrial City (KIIC), Sukaluyu, Telukjambe Timur, Karawang',
            'latitude' => -6.368100,
            'longitude' => 107.279200,
        ],
        [
            'name' => 'Kawasan Industri Suryacipta',
            'display_name' => 'Kutamekar, Kec. Ciampel, Kabupaten Karawang, Jawa Barat',
            'latitude' => -6.389200,
            'longitude' => 107.334100,
        ],
    ];

    /**
     * Search locations across Karawang matching query.
     *
     * @return array<int, array{name: string, display_name: string, latitude: float, longitude: float}>
     */
    public function search(string $query): array
    {
        $normalizedQuery = strtolower(trim($query));

        if ($normalizedQuery === '') {
            return array_slice($this->landmarks, 0, 8);
        }

        $results = [];

        foreach ($this->landmarks as $item) {
            $nameMatch = str_contains(strtolower($item['name']), $normalizedQuery);
            $displayMatch = str_contains(strtolower($item['display_name']), $normalizedQuery);

            if ($nameMatch || $displayMatch) {
                $results[] = $item;
            }
        }

        // If no match found in landmark list, synthesize a valid delivery point in Karawang
        if (empty($results)) {
            $results[] = [
                'name' => ucfirst($query).' (Area Karawang)',
                'display_name' => $query.', Kabupaten Karawang, Jawa Barat',
                'latitude' => -6.326200,
                'longitude' => 107.304500,
            ];
        }

        return $results;
    }

    /**
     * Detect best reference coordinate for active user or Karawang default.
     *
     * @return array{success: bool, source: string, latitude: float, longitude: float, name: string, display_name: string}
     */
    public function detect(?User $user = null): array
    {
        if ($user && $user->relationLoaded('profile') && $user->profile) {
            $profile = $user->profile;
            if ($profile->latitude !== null && $profile->longitude !== null) {
                return [
                    'success' => true,
                    'source' => 'user_profile',
                    'latitude' => (float) $profile->latitude,
                    'longitude' => (float) $profile->longitude,
                    'name' => $profile->store_name ?: ($profile->market_name ?: 'Alamat Terdaftar'),
                    'display_name' => $profile->full_address ?: 'Alamat profil terdaftar di Karawang',
                ];
            }
        }

        return [
            'success' => true,
            'source' => 'default_karawang',
            'latitude' => -6.326200,
            'longitude' => 107.304500,
            'name' => 'Kampus UNSIKA Telukjambe',
            'display_name' => 'Universitas Singaperbangsa Karawang (UNSIKA), Jl. H.S. Ronggowaluyo, Telukjambe Timur, Karawang',
        ];
    }
}
