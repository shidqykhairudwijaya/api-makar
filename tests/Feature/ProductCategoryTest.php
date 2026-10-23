<?php

namespace Tests\Feature;

use App\Enums\ProductUnit;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_get_all_categories_with_products_count(): void
    {
        $category = Category::factory()->create([
            'name' => 'Sayuran Segar',
            'slug' => 'sayuran-segar',
        ]);

        Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'is_available' => true,
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'is_available' => false,
        ]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'image_url',
                        'products_count',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'slug' => 'sayuran-segar',
                'products_count' => 3,
            ]);
    }

    public function test_public_can_get_products_with_pagination_and_relationships(): void
    {
        $seller = User::factory()->seller()->create(['name' => 'Pak Joko']);
        UserProfile::factory()->create([
            'user_id' => $seller->id,
            'store_name' => 'Kios Sayur Berkah',
            'market_name' => 'Pasar Induk Kramat Jati',
        ]);

        $category = Category::factory()->create(['name' => 'Bumbu']);

        Product::factory()->create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Bawang Putih Kating',
            'price' => 45000,
            'is_available' => true,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'seller_id',
                        'category_id',
                        'name',
                        'slug',
                        'price',
                        'formatted_price',
                        'unit',
                        'min_order',
                        'stock',
                        'is_available',
                        'seller' => [
                            'id',
                            'name',
                            'profile' => [
                                'store_name',
                                'market_name',
                            ],
                        ],
                        'category' => [
                            'id',
                            'name',
                            'slug',
                        ],
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonPath('data.0.name', 'Bawang Putih Kating')
            ->assertJsonPath('data.0.seller.profile.store_name', 'Kios Sayur Berkah');
    }

    public function test_public_can_filter_products_by_category_slug_and_search(): void
    {
        $catA = Category::factory()->create(['slug' => 'sayuran']);
        $catB = Category::factory()->create(['slug' => 'buah']);

        Product::factory()->create([
            'category_id' => $catA->id,
            'name' => 'Bayam Hijau',
            'is_available' => true,
        ]);

        Product::factory()->create([
            'category_id' => $catB->id,
            'name' => 'Pisang Ambon',
            'is_available' => true,
        ]);

        $responseFilter = $this->getJson('/api/products?category=sayuran');
        $responseFilter->assertStatus(200);
        $this->assertCount(1, $responseFilter->json('data'));
        $this->assertEquals('Bayam Hijau', $responseFilter->json('data.0.name'));

        $responseSearch = $this->getJson('/api/products?search=Pisang');
        $responseSearch->assertStatus(200);
        $this->assertCount(1, $responseSearch->json('data'));
        $this->assertEquals('Pisang Ambon', $responseSearch->json('data.0.name'));
    }

    public function test_public_can_sort_products_by_price(): void
    {
        Product::factory()->create(['name' => 'Mahal', 'price' => 100000, 'is_available' => true]);
        Product::factory()->create(['name' => 'Murah', 'price' => 20000, 'is_available' => true]);

        $responseAsc = $this->getJson('/api/products?sort=price_asc');
        $responseAsc->assertStatus(200);
        $this->assertEquals('Murah', $responseAsc->json('data.0.name'));

        $responseDesc = $this->getJson('/api/products?sort=price_desc');
        $responseDesc->assertStatus(200);
        $this->assertEquals('Mahal', $responseDesc->json('data.0.name'));
    }

    public function test_public_can_view_single_product_by_slug(): void
    {
        $product = Product::factory()->create([
            'name' => 'Cabai Rawit Merah',
            'slug' => 'cabai-rawit-merah',
            'is_available' => true,
        ]);

        $response = $this->getJson('/api/products/cabai-rawit-merah');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.slug', 'cabai-rawit-merah');
    }

    public function test_public_cannot_view_unavailable_product(): void
    {
        Product::factory()->create([
            'slug' => 'produk-habis',
            'is_available' => false,
        ]);

        $response = $this->getJson('/api/products/produk-habis');

        $response->assertStatus(404);
    }

    public function test_seller_can_create_product_with_auto_generated_slug(): void
    {
        $seller = User::factory()->seller()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($seller);

        $payload = [
            'category_id' => $category->id,
            'name' => 'Tomat Merah Segar Grade A',
            'description' => 'Tomat segar dipetik langsung dari kebun.',
            'price' => 18000,
            'unit' => ProductUnit::Kg->value,
            'min_order' => 2,
            'stock' => 100,
            'image_url' => 'https://example.com/tomat.jpg',
            'is_available' => true,
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Tomat Merah Segar Grade A')
            ->assertJsonPath('data.seller_id', $seller->id)
            ->assertJsonPath('data.unit', 'kg');

        $slug = $response->json('data.slug');
        $this->assertStringStartsWith('tomat-merah-segar-grade-a', $slug);

        $this->assertDatabaseHas('products', [
            'name' => 'Tomat Merah Segar Grade A',
            'seller_id' => $seller->id,
            'slug' => $slug,
        ]);
    }

    public function test_buyer_cannot_create_product(): void
    {
        $buyer = User::factory()->buyer()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($buyer);

        $payload = [
            'category_id' => $category->id,
            'name' => 'Sayur Kol',
            'price' => 10000,
            'unit' => ProductUnit::Kg->value,
            'stock' => 10,
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_create_product(): void
    {
        $category = Category::factory()->create();

        $payload = [
            'category_id' => $category->id,
            'name' => 'Sayur Kol',
            'price' => 10000,
            'unit' => ProductUnit::Kg->value,
            'stock' => 10,
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(401);
    }

    public function test_seller_can_view_their_own_products_including_unavailable(): void
    {
        $sellerA = User::factory()->seller()->create();
        $sellerB = User::factory()->seller()->create();

        Product::factory()->create([
            'seller_id' => $sellerA->id,
            'name' => 'Produk Seller A Aktif',
            'is_available' => true,
        ]);
        Product::factory()->create([
            'seller_id' => $sellerA->id,
            'name' => 'Produk Seller A Nonaktif',
            'is_available' => false,
        ]);
        Product::factory()->create([
            'seller_id' => $sellerB->id,
            'name' => 'Produk Seller B',
            'is_available' => true,
        ]);

        Sanctum::actingAs($sellerA);

        $response = $this->getJson('/api/seller/products');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_seller_can_update_their_own_product(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'name' => 'Kentang Dieng Lama',
            'price' => 25000,
        ]);

        Sanctum::actingAs($seller);

        $response = $this->putJson("/api/products/{$product->slug}", [
            'price' => 30000,
            'stock' => 50,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.price', 30000)
            ->assertJsonPath('data.stock', 50);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => 30000,
            'stock' => 50,
        ]);
    }

    public function test_seller_cannot_update_another_sellers_product(): void
    {
        $sellerA = User::factory()->seller()->create();
        $sellerB = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'seller_id' => $sellerA->id,
            'price' => 25000,
        ]);

        Sanctum::actingAs($sellerB);

        $response = $this->putJson("/api/products/{$product->slug}", [
            'price' => 99999,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Anda tidak memiliki akses untuk mengubah produk ini.',
            ]);
    }

    public function test_seller_can_soft_delete_their_own_product(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
        ]);

        Sanctum::actingAs($seller);

        $response = $this->deleteJson("/api/products/{$product->slug}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('products', [
            'id' => $product->id,
        ]);
    }

    public function test_seller_cannot_delete_another_sellers_product(): void
    {
        $sellerA = User::factory()->seller()->create();
        $sellerB = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'seller_id' => $sellerA->id,
        ]);

        Sanctum::actingAs($sellerB);

        $response = $this->deleteJson("/api/products/{$product->slug}");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Anda tidak memiliki akses untuk menghapus produk ini.',
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'deleted_at' => null,
        ]);
    }
}
