<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PickupStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_seller_order_endpoints(): void
    {
        $this->getJson('/api/seller/orders')->assertStatus(401);
        $this->getJson('/api/seller/incoming-items')->assertStatus(401);
    }

    public function test_non_seller_cannot_access_seller_order_endpoints(): void
    {
        $buyer = User::factory()->buyer()->create();
        Sanctum::actingAs($buyer);

        $this->getJson('/api/seller/orders')->assertStatus(403);
    }

    public function test_seller_can_list_orders_containing_their_commodities(): void
    {
        $seller1 = User::factory()->seller()->create();
        $seller2 = User::factory()->seller()->create();

        // Order 1 has items from seller 1
        $order1 = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order1->id,
            'seller_id' => $seller1->id,
        ]);

        // Order 2 only has items from seller 2
        $order2 = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order2->id,
            'seller_id' => $seller2->id,
        ]);

        Sanctum::actingAs($seller1);

        $response = $this->getJson('/api/seller/orders');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $order1->id);
    }

    public function test_seller_can_view_incoming_items_to_be_packed_and_prepared(): void
    {
        $seller = User::factory()->seller()->create();

        // Order 1 is confirmed and awaiting pickup
        $order1 = Order::factory()->create(['order_status' => OrderStatus::Confirmed]);
        $itemWaiting = OrderItem::factory()->create([
            'order_id' => $order1->id,
            'seller_id' => $seller->id,
            'pickup_status' => PickupStatus::Waiting,
        ]);

        // Item already picked up should not show in incoming items
        OrderItem::factory()->create([
            'order_id' => $order1->id,
            'seller_id' => $seller->id,
            'pickup_status' => PickupStatus::PickedUp,
        ]);

        // Order 2 is pending (not yet confirmed) so item should not show yet
        $order2 = Order::factory()->create(['order_status' => OrderStatus::Pending]);
        OrderItem::factory()->create([
            'order_id' => $order2->id,
            'seller_id' => $seller->id,
            'pickup_status' => PickupStatus::Waiting,
        ]);

        Sanctum::actingAs($seller);

        $response = $this->getJson('/api/seller/incoming-items');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $itemWaiting->id);
    }

    public function test_seller_can_update_their_own_item_status(): void
    {
        $seller = User::factory()->seller()->create();
        $item = OrderItem::factory()->create([
            'seller_id' => $seller->id,
            'pickup_status' => PickupStatus::Waiting,
        ]);

        Sanctum::actingAs($seller);

        $response = $this->patchJson("/api/seller/order-items/{$item->id}/status", [
            'pickup_status' => 'out_of_stock',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Status komoditas berhasil diperbarui.')
            ->assertJsonPath('data.pickup_status', 'out_of_stock');

        $item->refresh();
        $this->assertSame(PickupStatus::OutOfStock, $item->pickup_status);
    }

    public function test_seller_cannot_update_item_of_another_seller(): void
    {
        $seller1 = User::factory()->seller()->create();
        $seller2 = User::factory()->seller()->create();

        $item = OrderItem::factory()->create(['seller_id' => $seller1->id]);

        Sanctum::actingAs($seller2);

        $response = $this->patchJson("/api/seller/order-items/{$item->id}/status", [
            'pickup_status' => 'out_of_stock',
        ]);

        $response->assertStatus(403);
    }
}
