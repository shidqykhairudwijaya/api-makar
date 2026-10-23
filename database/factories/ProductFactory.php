<?php

namespace Database\Factories;

use App\Enums\ProductUnit;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Cabai Merah Keriting Super',
            'Bawang Merah Brebes Pilihan',
            'Tomat Merah Segar',
            'Kentang Dieng Grade A',
            'Wortel Brastagi',
            'Kangkung Segar Ikat',
            'Bayam Hijau Pilihan',
            'Jahe Gajah Segar',
        ]);

        return [
            'seller_id' => User::factory()->seller(),
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 99999),
            'description' => fake()->paragraph(),
            'price' => fake()->numberBetween(15000, 150000),
            'unit' => fake()->randomElement(ProductUnit::cases()),
            'min_order' => fake()->numberBetween(1, 5),
            'stock' => fake()->numberBetween(10, 500),
            'image_url' => 'https://placehold.co/600x400?text='.urlencode($name),
            'is_available' => true,
        ];
    }
}
