<?php

namespace Database\Factories;

use App\Enums\PickupStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->numberBetween(20000, 100000);
        $qty = fake()->numberBetween(2, 20);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'seller_id' => User::factory()->seller(),
            'product_name_snapshot' => 'Cabai Merah Keriting Super',
            'unit_snapshot' => 'kg',
            'price_at_purchase' => $price,
            'quantity' => $qty,
            'subtotal' => $price * $qty,
            'pickup_status' => PickupStatus::Waiting,
        ];
    }
}
