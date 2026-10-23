<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfile>
 */
class UserProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'store_name' => fake()->company(),
            'market_name' => 'Pasar Induk Kramat Jati',
            'block_number' => 'Blok '.fake()->randomElement(['A', 'B', 'C', 'D']).' No. '.fake()->numberBetween(1, 100),
            'full_address' => fake()->address(),
            'latitude' => fake()->latitude(-6.3, -6.1),
            'longitude' => fake()->longitude(106.7, 106.9),
        ];
    }
}
