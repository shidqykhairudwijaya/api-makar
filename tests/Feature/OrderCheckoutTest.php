<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ProductUnit;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_order_endpoints(): void
    {
        $this->getJson('/api/orders')->assertStatus(401);
        $this->postJson('/api/orders/checkout')->assertStatus(401);
    }

    public function test_seller_cannot_checkout_orders(): void
    {
        $seller = User::factory()->seller()->create();
        Sanctum::actingAs($seller);

        $this->postJson('/api/orders/checkout')->assertStatus(403);
    }

    public function test_checkout_fails_when_cart_is_empty(): void
    {
        $buyer = User::factory()->buyer()->create();
        UserProfile::factory()->create([
            'user_id' => $buyer->id,
            'full_address' => 'Jl. Pasar Baru No. 12',
        ]);
        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/orders/checkout');

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Keranjang belanja Anda masih kosong.');
    }

    public function test_checkout_fails_when_product_stock_is_insufficient(): void
    {
        $buyer = User::factory()->buyer()->create();
        UserProfile::factory()->create([
            'user_id' => $buyer->id,
            'full_address' => 'Jl. Pasar Baru No. 12',
        ]);
        Sanctum::actingAs($buyer);

        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'name' => 'Bawang Merah Brebes',
            'price' => 35000,
            'stock' => 5,
            'min_order' => 1,
            'is_available' => true,
        ]);

        Cart::factory()->create([
            'buyer_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 10, // Exceeds stock (5)
        ]);

        $response = $this->postJson('/api/orders/checkout');

        $response->assertStatus(422)
            ->assertJsonPath('message', "Stok untuk produk 'Bawang Merah Brebes' tidak mencukupi (tersisa 5).");

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_fails_when_product_is_not_available(): void
    {
        $buyer = User::factory()->buyer()->create();
        UserProfile::factory()->create([
            'user_id' => $buyer->id,
            'full_address' => 'Jl. Pasar Baru No. 12',
        ]);
        Sanctum::actingAs($buyer);

        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'name' => 'Cabai Rawit Merah',
            'is_available' => false,
            'stock' => 100,
            'min_order' => 1,
        ]);

        Cart::factory()->create([
            'buyer_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->postJson('/api/orders/checkout');

        $response->assertStatus(422)
            ->assertJsonPath('message', "Produk 'Cabai Rawit Merah' saat ini tidak tersedia untuk dipesan.");

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_fails_when_buyer_has_no_address(): void
    {
        $buyer = User::factory()->buyer()->create();
        // Buyer has no profile and no address
        Sanctum::actingAs($buyer);

        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'stock' => 20,
            'min_order' => 1,
            'is_available' => true,
        ]);

        Cart::factory()->create([
            'buyer_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $response = $this->postJson('/api/orders/checkout');

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Alamat pengiriman belum diatur. Silakan lengkapi profil Anda atau masukkan alamat pengiriman pada saat checkout.');
    }

    public function test_successful_multi_vendor_checkout_from_cart(): void
    {
        $buyer = User::factory()->buyer()->create([
            'name' => 'Pak Slamet',
            'phone' => '081299988877',
        ]);

        UserProfile::factory()->create([
            'user_id' => $buyer->id,
            'store_name' => 'Warung Sayur Slamet',
            'market_name' => 'Pasar Jaya Tebet',
            'block_number' => 'Kios A-01',
            'full_address' => 'Jl. Tebet Barat No. 10',
            'latitude' => -6.23000000,
            'longitude' => 106.85000000,
        ]);

        Sanctum::actingAs($buyer);

        // Seller 1 at Pasar Induk
        $seller1 = User::factory()->seller()->create(['name' => 'Haji Basri']);
        UserProfile::factory()->create([
            'user_id' => $seller1->id,
            'store_name' => 'Kios Bawang Berkah',
            'market_name' => 'Pasar Induk Kramat Jati',
            'block_number' => 'Blok B No. 12',
        ]);
        $product1 = Product::factory()->create([
            'seller_id' => $seller1->id,
            'name' => 'Bawang Merah Super',
            'unit' => ProductUnit::Kg,
            'price' => 40000,
            'stock' => 50,
            'min_order' => 5,
            'is_available' => true,
        ]);

        // Seller 2 at Pasar Induk
        $seller2 = User::factory()->seller()->create(['name' => 'Pak Joko']);
        UserProfile::factory()->create([
            'user_id' => $seller2->id,
            'store_name' => 'Kios Cabai Makmur',
            'market_name' => 'Pasar Induk Kramat Jati',
            'block_number' => 'Blok C No. 04',
        ]);
        $product2 = Product::factory()->create([
            'seller_id' => $seller2->id,
            'name' => 'Cabai Rawit Merah Peti',
            'unit' => ProductUnit::Peti,
            'price' => 250000,
            'stock' => 10,
            'min_order' => 1,
            'is_available' => true,
        ]);

        // Buyer adds both items into cart
        Cart::factory()->create([
            'buyer_id' => $buyer->id,
            'product_id' => $product1->id,
            'quantity' => 10, // Subtotal: 10 * 40.000 = 400.000
        ]);

        Cart::factory()->create([
            'buyer_id' => $buyer->id,
            'product_id' => $product2->id,
            'quantity' => 2, // Subtotal: 2 * 250.000 = 500.000
        ]);

        $payload = [
            'notes' => 'Tolong jemput sebelum jam 04.00 subuh.',
            'delivery_fee' => 25000,
        ];

        $response = $this->postJson('/api/orders/checkout', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.subtotal_products', 900000)
            ->assertJsonPath('data.delivery_fee', 25000)
            ->assertJsonPath('data.total_amount', 925000)
            ->assertJsonPath('data.order_status', 'pending')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.notes', 'Tolong jemput sebelum jam 04.00 subuh.')
            ->assertJsonPath('data.delivery_address_snapshot.recipient_name', 'Pak Slamet')
            ->assertJsonPath('data.delivery_address_snapshot.store_name', 'Warung Sayur Slamet')
            ->assertJsonCount(2, 'data.items');

        // Verify database state: order created
        $this->assertDatabaseHas('orders', [
            'buyer_id' => $buyer->id,
            'subtotal_products' => 900000,
            'delivery_fee' => 25000,
            'total_amount' => 925000,
            'order_status' => OrderStatus::Pending->value,
        ]);

        // Verify order items snapshots
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product1->id,
            'seller_id' => $seller1->id,
            'product_name_snapshot' => 'Bawang Merah Super',
            'unit_snapshot' => 'kg',
            'price_at_purchase' => 40000,
            'quantity' => 10,
            'subtotal' => 400000,
            'pickup_status' => 'waiting',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product2->id,
            'seller_id' => $seller2->id,
            'product_name_snapshot' => 'Cabai Rawit Merah Peti',
            'unit_snapshot' => 'peti',
            'price_at_purchase' => 250000,
            'quantity' => 2,
            'subtotal' => 500000,
            'pickup_status' => 'waiting',
        ]);

        // Verify stock is decremented
        $product1->refresh();
        $this->assertSame(40, $product1->stock); // 50 - 10

        $product2->refresh();
        $this->assertSame(8, $product2->stock); // 10 - 2

        // Verify cart is cleared
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_checkout_with_custom_delivery_address_override(): void
    {
        $buyer = User::factory()->buyer()->create();
        Sanctum::actingAs($buyer);

        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'stock' => 10,
            'min_order' => 1,
            'price' => 50000,
            'is_available' => true,
        ]);

        Cart::factory()->create([
            'buyer_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $customAddress = [
            'recipient_name' => 'Pak Joko Cabang 2',
            'phone' => '081234556677',
            'store_name' => 'Warung Cabang Pancoran',
            'full_address' => 'Jl. Pancoran Barat No. 8',
            'latitude' => -6.24000000,
            'longitude' => 106.84000000,
        ];

        $response = $this->postJson('/api/orders/checkout', [
            'delivery_address' => $customAddress,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.delivery_address_snapshot.recipient_name', 'Pak Joko Cabang 2')
            ->assertJsonPath('data.delivery_address_snapshot.store_name', 'Warung Cabang Pancoran')
            ->assertJsonPath('data.delivery_address_snapshot.full_address', 'Jl. Pancoran Barat No. 8');
    }

    public function test_buyer_can_list_their_orders(): void
    {
        $buyer1 = User::factory()->buyer()->create();
        $buyer2 = User::factory()->buyer()->create();

        Order::factory()->count(2)->create(['buyer_id' => $buyer1->id]);
        Order::factory()->count(3)->create(['buyer_id' => $buyer2->id]);

        Sanctum::actingAs($buyer1);

        $response = $this->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_buyer_can_view_their_order_detail(): void
    {
        $buyer = User::factory()->buyer()->create();
        $order = Order::factory()->create(['buyer_id' => $buyer->id]);
        OrderItem::factory()->create(['order_id' => $order->id]);

        Sanctum::actingAs($buyer);

        $response = $this->getJson("/api/orders/{$order->order_number}");

        $response->assertStatus(200)
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_buyer_cannot_view_another_buyers_order(): void
    {
        $buyer1 = User::factory()->buyer()->create();
        $buyer2 = User::factory()->buyer()->create();

        $order1 = Order::factory()->create(['buyer_id' => $buyer1->id]);

        Sanctum::actingAs($buyer2);

        $response = $this->getJson("/api/orders/{$order1->order_number}");

        $response->assertStatus(403);
    }

    public function test_buyer_can_cancel_pending_order_and_inventory_is_restored(): void
    {
        $buyer = User::factory()->buyer()->create();
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'stock' => 20, // After decrement during checkout
        ]);

        $order = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'order_status' => OrderStatus::Pending,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        Sanctum::actingAs($buyer);

        $response = $this->postJson("/api/orders/{$order->order_number}/cancel", [
            'reason' => 'Warung tutup karena renovasi.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Pesanan berhasil dibatalkan.')
            ->assertJsonPath('data.order_status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Warung tutup karena renovasi.');

        // Assert product stock was restored by 10 (20 + 10 = 30)
        $product->refresh();
        $this->assertSame(30, $product->stock);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => OrderStatus::Cancelled->value,
            'cancellation_reason' => 'Warung tutup karena renovasi.',
        ]);
    }

    public function test_buyer_cannot_cancel_order_that_is_already_in_transit(): void
    {
        $buyer = User::factory()->buyer()->create();
        $order = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'order_status' => OrderStatus::InTransit,
        ]);

        Sanctum::actingAs($buyer);

        $response = $this->postJson("/api/orders/{$order->order_number}/cancel");

        $response->assertStatus(422)
            ->assertJsonPath('message', "Pesanan dengan status 'in_transit' tidak dapat dibatalkan.");
    }
}
