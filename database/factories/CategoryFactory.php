<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Sayuran Hijau',
            'Bawang & Cabai',
            'Umbi-umbian',
            'Buah Segar',
            'Rempah & Bumbu',
            'Kacang-kacangan',
            'Daging & Ayam',
            'Ikan & Hasil Laut',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'image_url' => 'https://placehold.co/400x400?text='.urlencode($name),
        ];
    }
}
