<?php

namespace Tests\Feature;

use App\Enums\ProductUnit;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $seller;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::factory()->buyer()->create();
        UserProfile::factory()->create(['user_id' => $this->buyer->id]);

        $this->seller = User::factory()->seller()->create();
        UserProfile::factory()->create([
            'user_id' => $this->seller->id,
            'store_name' => 'Kios Cabai Makmur',
            'market_name' => 'Pasar Induk Kramat Jati',
        ]);

        $category = Category::factory()->create();

        $this->product = Product::factory()->create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'name' => 'Cabai Rawit Merah',
            'price' => 40000,
            'unit' => ProductUnit::Kg,
            'min_order' => 5,
            'stock' => 100,
            'is_available' => true,
        ]);
    }

    public function test_buyer_can_get_cart_items_and_summary(): void
    {
        Cart::factory()->create([
            'buyer_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        Sanctum::actingAs($this->buyer);

        $response = $this->getJson('/api/cart');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'buyer_id',
                        'product_id',
                        'quantity',
                        'subtotal',
                        'formatted_subtotal',
                        'product' => [
                            'id',
                            'name',
                            'price',
                            'formatted_price',
                            'seller' => [
                                'id',
                                'name',
                                'profile',
                            ],
                        ],
                    ],
                ],
                'summary' => [
                    'total_items',
                    'total_price',
                    'formatted_total_price',
                ],
            ])
            ->assertJsonPath('summary.total_items', 10)
            ->assertJsonPath('summary.total_price', 400000);
    }

    public function test_buyer_can_add_product_to_cart(): void
    {
        Sanctum::actingAs($this->buyer);

        $response = $this->postJson('/api/cart', [
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.product_id', $this->product->id)
            ->assertJsonPath('data.quantity', 5)
            ->assertJsonPath('data.subtotal', 200000);

        $this->assertDatabaseHas('carts', [
            'buyer_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);
    }

    public function test_adding_same_product_increments_existing_quantity(): void
    {
        Cart::factory()->create([
            'buyer_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        Sanctum::actingAs($this->buyer);

        $response = $this->postJson('/api/cart', [
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.quantity', 15)
            ->assertJsonPath('data.subtotal', 600000);

        $this->assertDatabaseHas('carts', [
            'buyer_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 15,
        ]);
    }

    public function test_cannot_add_product_below_min_order(): void
    {
        Sanctum::actingAs($this->buyer);

        $response = $this->postJson('/api/cart', [
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Jumlah pembelian minimal untuk produk ini adalah 5 kg.',
            ]);

        $this->assertDatabaseMissing('carts', [
            'buyer_id' => $this->buyer->id,
            'product_id' => $this->product->id,
        ]);
    }

    public function test_cannot_add_product_exceeding_stock(): void
    {
        Sanctum::actingAs($this->buyer);

        $response = $this->postJson('/api/cart', [
            'product_id' => $this->product->id,
            'quantity' => 150,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Jumlah melebihi stok yang tersedia (sisa stok: 100).',
            ]);
    }

    public function test_cannot_add_unavailable_product(): void
    {
        $this->product->update(['is_available' => false]);

        Sanctum::actingAs($this->buyer);

        $response = $this->postJson('/api/cart', [
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Produk tidak tersedia untuk dibeli.',
            ]);
    }

    public function test_seller_cannot_add_own_product_to_cart(): void
    {
        Sanctum::actingAs($this->seller);

        // Even if bypass middleware or admin, seller_id check prevents buying own product
        $buyerActingAsSeller = User::factory()->buyer()->create();
        $this->product->update(['seller_id' => $buyerActingAsSeller->id]);

        Sanctum::actingAs($buyerActingAsSeller);

        $response = $this->postJson('/api/cart', [
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Anda tidak dapat membeli produk dari kios Anda sendiri.',
            ]);
    }

    public function test_seller_role_cannot_access_cart_endpoints(): void
    {
        Sanctum::actingAs($this->seller);

        $response = $this->getJson('/api/cart');

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Akses ditolak. Peran tidak sesuai.',
            ]);
    }

    public function test_buyer_can_update_cart_item_quantity(): void
    {
        $cart = Cart::factory()->create([
            'buyer_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        Sanctum::actingAs($this->buyer);

        $response = $this->putJson("/api/cart/{$cart->id}", [
            'quantity' => 20,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.quantity', 20)
            ->assertJsonPath('data.subtotal', 800000);

        $this->assertDatabaseHas('carts', [
            'id' => $cart->id,
            'quantity' => 20,
        ]);
    }

    public function test_buyer_cannot_update_cart_of_another_buyer(): void
    {
        $otherBuyer = User::factory()->buyer()->create();
        $cart = Cart::factory()->create([
            'buyer_id' => $otherBuyer->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        Sanctum::actingAs($this->buyer);

        $response = $this->putJson("/api/cart/{$cart->id}", [
            'quantity' => 10,
        ]);

        $response->assertStatus(403);
    }

    public function test_buyer_can_remove_item_from_cart(): void
    {
        $cart = Cart::factory()->create([
            'buyer_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        Sanctum::actingAs($this->buyer);

        $response = $this->deleteJson("/api/cart/{$cart->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Item berhasil dihapus dari keranjang.',
            ]);

        $this->assertDatabaseMissing('carts', [
            'id' => $cart->id,
        ]);
    }

    public function test_buyer_cannot_delete_cart_of_another_buyer(): void
    {
        $otherBuyer = User::factory()->buyer()->create();
        $cart = Cart::factory()->create([
            'buyer_id' => $otherBuyer->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        Sanctum::actingAs($this->buyer);

        $response = $this->deleteJson("/api/cart/{$cart->id}");

        $response->assertStatus(403);
    }

    public function test_buyer_can_clear_entire_cart(): void
    {
        Cart::factory()->count(3)->create([
            'buyer_id' => $this->buyer->id,
        ]);

        Sanctum::actingAs($this->buyer);

        $response = $this->deleteJson('/api/cart');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Keranjang belanja berhasil dikosongkan.',
            ]);

        $this->assertDatabaseMissing('carts', [
            'buyer_id' => $this->buyer->id,
        ]);
    }
}
