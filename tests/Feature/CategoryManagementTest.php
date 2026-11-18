<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_category_via_admin_categories_endpoint(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $payload = [
            'name' => 'Bumbu Basah & Pasta',
            'image_url' => 'https://images.unsplash.com/photo-example',
        ];

        $response = $this->postJson('/api/admin/categories', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Kategori komoditas berhasil ditambahkan.')
            ->assertJsonPath('data.name', 'Bumbu Basah & Pasta')
            ->assertJsonPath('data.slug', 'bumbu-basah-pasta');

        $this->assertDatabaseHas('categories', [
            'name' => 'Bumbu Basah & Pasta',
            'slug' => 'bumbu-basah-pasta',
        ]);
    }

    public function test_seller_or_admin_can_create_category_via_fallback_endpoint(): void
    {
        $seller = User::factory()->seller()->create();
        Sanctum::actingAs($seller);

        $payload = [
            'name' => 'Aneka Jamur Segar',
        ];

        $response = $this->postJson('/api/categories', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Aneka Jamur Segar')
            ->assertJsonPath('data.slug', 'aneka-jamur-segar');

        $this->assertDatabaseHas('categories', [
            'name' => 'Aneka Jamur Segar',
        ]);
    }

    public function test_buyer_cannot_create_category(): void
    {
        $buyer = User::factory()->buyer()->create();
        Sanctum::actingAs($buyer);

        $this->postJson('/api/admin/categories', ['name' => 'Test'])->assertStatus(403);
        $this->postJson('/api/categories', ['name' => 'Test'])->assertStatus(403);
    }

    public function test_unauthenticated_cannot_create_category(): void
    {
        $this->postJson('/api/admin/categories', ['name' => 'Test'])->assertStatus(401);
        $this->postJson('/api/categories', ['name' => 'Test'])->assertStatus(401);
    }

    public function test_create_category_validates_required_name_and_uniqueness(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        Category::factory()->create(['name' => 'Sayuran Hijau']);

        $this->postJson('/api/admin/categories', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->postJson('/api/admin/categories', ['name' => 'Sayuran Hijau'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
