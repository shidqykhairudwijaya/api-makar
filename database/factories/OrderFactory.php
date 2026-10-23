<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(100000, 2000000);
        $deliveryFee = 25000;

        return [
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.strtoupper(fake()->unique()->bothify('####??')),
            'buyer_id' => User::factory()->buyer(),
            'courier_id' => null,
            'subtotal_products' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total_amount' => $subtotal + $deliveryFee,
            'order_status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'delivery_address_snapshot' => [
                'recipient_name' => fake()->name(),
                'phone' => fake()->phoneNumber(),
                'market_name' => 'Pasar Subur Jaya',
                'block_number' => 'Kios B-12',
                'full_address' => fake()->address(),
            ],
            'notes' => fake()->sentence(),
            'ordered_at' => now(),
            'completed_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];
    }

    /**
     * Indicate that the order is confirmed and assigned to a courier.
     */
    public function assignedToCourier(?User $courier = null): static
    {
        return $this->state(fn (array $attributes) => [
            'courier_id' => $courier?->id ?? User::factory()->courier(),
            'order_status' => OrderStatus::InTransit,
        ]);
    }

    /**
     * Indicate that the order has been completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'completed_at' => now(),
        ]);
    }
}
