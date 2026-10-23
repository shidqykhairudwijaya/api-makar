<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PickupStatus;
use App\Enums\ProductUnit;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user creation with profile and role cast.
     */
    public function test_user_can_have_profile_and_enums_are_cast(): void
    {
        $user = User::factory()->seller()->create([
            'phone' => '081234567890',
        ]);

        $profile = UserProfile::factory()->create([
            'user_id' => $user->id,
            'store_name' => 'Toko Cabai Berkah',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
        ]);

        $this->assertInstanceOf(UserRole::class, $user->role);
        $this->assertSame(UserRole::Seller, $user->role);
        $this->assertSame('Toko Cabai Berkah', $user->profile->store_name);
        $this->assertSame($user->id, $profile->user->id);
        $this->assertIsFloat($profile->latitude);
    }

    /**
     * Test product relations and SoftDeletes.
     */
    public function test_product_belongs_to_seller_and_category_with_soft_deletes(): void
    {
        $seller = User::factory()->seller()->create();
        $category = Category::factory()->create(['name' => 'Sayuran']);

        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'unit' => ProductUnit::Kg,
            'price' => 50000,
        ]);

        $this->assertInstanceOf(ProductUnit::class, $product->unit);
        $this->assertSame($seller->id, $product->seller->id);
        $this->assertSame($category->id, $product->category->id);

        // Test SoftDeletes
        $product->delete();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertNull(Product::find($product->id));
        $this->assertNotNull(Product::withTrashed()->find($product->id));
    }

    /**
     * Test cart unique constraint per buyer and product.
     */
    public function test_cart_item_associates_buyer_and_product(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = Product::factory()->create();

        $cart = Cart::factory()->create([
            'buyer_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->assertSame($buyer->id, $cart->buyer->id);
        $this->assertSame($product->id, $cart->product->id);
        $this->assertSame(1, $buyer->cartItems()->count());
    }

    /**
     * Test complete order, order_items snapshot, and payment lifecycle.
     */
    public function test_order_with_items_and_payment(): void
    {
        $buyer = User::factory()->buyer()->create();
        $courier = User::factory()->courier()->create();
        $seller = User::factory()->seller()->create();
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $order = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'courier_id' => $courier->id,
            'order_status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'subtotal_products' => 100000,
            'delivery_fee' => 15000,
            'total_amount' => 115000,
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'price_at_purchase' => 50000,
            'quantity' => 2,
            'subtotal' => 100000,
            'pickup_status' => PickupStatus::Waiting,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'payment_method' => PaymentMethod::Cod,
            'amount_paid' => 115000,
            'verified_by' => $admin->id,
        ]);

        $this->assertInstanceOf(OrderStatus::class, $order->order_status);
        $this->assertInstanceOf(PaymentStatus::class, $order->payment_status);
        $this->assertInstanceOf(PickupStatus::class, $item->pickup_status);
        $this->assertInstanceOf(PaymentMethod::class, $payment->payment_method);

        $this->assertSame($buyer->id, $order->buyer->id);
        $this->assertSame($courier->id, $order->courier->id);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame($payment->id, $order->payment->id);
        $this->assertSame($admin->id, $payment->verifier->id);
    }

    /**
     * Test authenticated Sanctum API endpoint /api/user.
     */
    public function test_authenticated_user_can_access_api_user_endpoint(): void
    {
        $user = User::factory()->seller()->create();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'store_name' => 'Kios Cabai Makmur',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'seller')
            ->assertJsonPath('data.profile.store_name', 'Kios Cabai Makmur');
    }
}
