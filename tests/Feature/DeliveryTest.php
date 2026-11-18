<?php

namespace Tests\Feature;

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_calculate_delivery_fee_with_valid_coordinates(): void
    {
        $payload = [
            'latitude' => -6.326200,
            'longitude' => 107.304500,
        ];

        $response = $this->postJson('/api/delivery/calculate-fee', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'distance_km',
                    'estimated_weight_kg',
                    'stall_count',
                    'base_fee',
                    'distance_fee',
                    'weight_fee',
                    'multi_stall_fee',
                    'total_delivery_fee',
                    'formatted_total_fee',
                    'routing_source',
                    'hub' => ['name', 'latitude', 'longitude'],
                    'destination' => ['latitude', 'longitude'],
                ],
            ])
            ->assertJsonPath('message', 'Ongkos kirim berhasil dihitung.')
            ->assertJsonPath('data.base_fee', 10000);
    }

    public function test_calculate_fee_validates_required_coordinates(): void
    {
        $response = $this->postJson('/api/delivery/calculate-fee', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_calculate_fee_incorporates_multi_stall_and_weight(): void
    {
        $seller1 = User::factory()->seller()->create();
        $seller2 = User::factory()->seller()->create();

        $product1 = Product::factory()->create([
            'seller_id' => $seller1->id,
            'unit' => ProductUnit::Karung, // 25kg
        ]);

        $product2 = Product::factory()->create([
            'seller_id' => $seller2->id,
            'unit' => ProductUnit::Kg,
        ]);

        $payload = [
            'latitude' => -6.380000,
            'longitude' => 107.350000,
            'items' => [
                ['product_id' => $product1->id, 'quantity' => 2], // 50kg
                ['product_id' => $product2->id, 'quantity' => 5], // 5kg
            ],
        ];

        $response = $this->postJson('/api/delivery/calculate-fee', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.stall_count', 2)
            ->assertJsonPath('data.multi_stall_fee', 3000); // 1 extra stall

        $this->assertGreaterThan(0, $response->json('data.weight_fee'));
    }

    public function test_can_search_location_in_karawang(): void
    {
        $response = $this->getJson('/api/delivery/search-location?q=johar');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['name', 'display_name', 'latitude', 'longitude'],
                ],
            ]);

        $this->assertNotEmpty($response->json('data'));
        $this->assertStringContainsStringIgnoringCase('johar', $response->json('data.0.name'));
    }

    public function test_can_detect_default_karawang_location_unauthenticated(): void
    {
        $response = $this->getJson('/api/delivery/detect-location');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('source', 'default_karawang')
            ->assertJsonPath('latitude', -6.3262);
    }

    public function test_can_detect_location_from_user_profile(): void
    {
        $user = User::factory()->buyer()->create();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'store_name' => 'Kios Berkah Jaya',
            'full_address' => 'Jl. Tuparev No. 88, Karawang',
            'latitude' => -6.303100,
            'longitude' => 107.306500,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/delivery/detect-location');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('source', 'user_profile')
            ->assertJsonPath('latitude', -6.3031)
            ->assertJsonPath('longitude', 107.3065);
    }
}
