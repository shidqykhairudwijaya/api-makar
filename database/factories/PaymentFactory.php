<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'amount_paid' => fake()->numberBetween(100000, 2000000),
            'payment_proof_url' => 'https://placehold.co/800x1200?text=Bukti+Transfer',
            'verified_by' => null,
            'paid_at' => now(),
            'external_reference_id' => 'PAY-'.strtoupper(fake()->bothify('????-#####')),
        ];
    }

    /**
     * Indicate that the payment has been verified by an admin.
     */
    public function verified(?User $admin = null): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_by' => $admin?->id ?? User::factory()->admin(),
        ]);
    }
}
