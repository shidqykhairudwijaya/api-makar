<?php

namespace Tests\Feature;

use App\Enums\ProductUnit;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_calculate_delivery_fee_for_karawang_destination(): void
    {
        $seller = User::factory()->seller()->create();
        $category = Category::factory()->create();

        $product1 = Product::factory()->create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Cabai Merah Keriting',
            'unit' => ProductUnit::Kg,
            'price' => 35000,
            'stock' => 100,
        ]);

        $payload = [
            'latitude' => -6.3262, // Unsika
            'longitude' => 107.3045,
            'items' => [
                [
                    'product_id' => $product1->id,
                    'quantity' => 10,
                ],
            ],
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
                    'hub',
                    'destination',
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['distance_km']);
        $this->assertEquals(10.0, $data['estimated_weight_kg']);
        $this->assertGreaterThanOrEqual(8000, $data['total_delivery_fee']);
    }
}
