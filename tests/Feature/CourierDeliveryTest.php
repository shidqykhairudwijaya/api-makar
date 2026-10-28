<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PickupStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourierDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_courier_endpoints(): void
    {
        $this->getJson('/api/courier/deliveries')->assertStatus(401);
    }

    public function test_non_courier_cannot_access_courier_endpoints(): void
    {
        $buyer = User::factory()->buyer()->create();
        Sanctum::actingAs($buyer);

        $this->getJson('/api/courier/deliveries')->assertStatus(403);
    }

    public function test_courier_can_list_my_deliveries_and_available_deliveries(): void
    {
        $courier = User::factory()->courier()->create();

        // Order assigned to this courier
        $myOrder = Order::factory()->create([
            'courier_id' => $courier->id,
            'order_status' => OrderStatus::PickingUp,
        ]);

        // Unassigned order available for claiming
        $availableOrder = Order::factory()->create([
            'courier_id' => null,
            'order_status' => OrderStatus::Confirmed,
        ]);

        Sanctum::actingAs($courier);

        // Test my deliveries
        $response = $this->getJson('/api/courier/deliveries');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $myOrder->id);

        // Test available deliveries
        $responseAvailable = $this->getJson('/api/courier/deliveries?scope=available');
        $responseAvailable->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $availableOrder->id);
    }

    public function test_courier_can_claim_confirmed_order(): void
    {
        $courier = User::factory()->courier()->create();
        $order = Order::factory()->create([
            'courier_id' => null,
            'order_status' => OrderStatus::Confirmed,
        ]);

        Sanctum::actingAs($courier);

        $response = $this->postJson("/api/courier/deliveries/{$order->order_number}/claim");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Pesanan berhasil diklaim untuk pengantaran.')
            ->assertJsonPath('data.courier_id', $courier->id)
            ->assertJsonPath('data.order_status', 'picking_up');

        $order->refresh();
        $this->assertSame($courier->id, $order->courier_id);
        $this->assertSame(OrderStatus::PickingUp, $order->order_status);
    }

    public function test_courier_cannot_claim_already_claimed_order(): void
    {
        $courier1 = User::factory()->courier()->create();
        $courier2 = User::factory()->courier()->create();

        $order = Order::factory()->create([
            'courier_id' => $courier1->id,
            'order_status' => OrderStatus::PickingUp,
        ]);

        Sanctum::actingAs($courier2);

        $response = $this->postJson("/api/courier/deliveries/{$order->order_number}/claim");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Pesanan ini sudah diambil oleh kurir lain.');
    }

    public function test_courier_can_update_item_pickup_status_at_seller_stall(): void
    {
        $courier = User::factory()->courier()->create();
        $order = Order::factory()->create([
            'courier_id' => $courier->id,
            'order_status' => OrderStatus::PickingUp,
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'pickup_status' => PickupStatus::Waiting,
        ]);

        Sanctum::actingAs($courier);

        $response = $this->patchJson("/api/courier/order-items/{$item->id}/pickup", [
            'pickup_status' => 'picked_up',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Status penjemputan barang berhasil diperbarui.')
            ->assertJsonPath('data.pickup_status', 'picked_up');

        $item->refresh();
        $this->assertSame(PickupStatus::PickedUp, $item->pickup_status);
    }

    public function test_courier_cannot_update_item_of_order_assigned_to_another_courier(): void
    {
        $courier1 = User::factory()->courier()->create();
        $courier2 = User::factory()->courier()->create();

        $order = Order::factory()->create(['courier_id' => $courier1->id]);
        $item = OrderItem::factory()->create(['order_id' => $order->id]);

        Sanctum::actingAs($courier2);

        $response = $this->patchJson("/api/courier/order-items/{$item->id}/pickup", [
            'pickup_status' => 'picked_up',
        ]);

        $response->assertStatus(403);
    }

    public function test_courier_can_update_delivery_status_to_in_transit_then_completed(): void
    {
        $courier = User::factory()->courier()->create();
        $order = Order::factory()->create([
            'courier_id' => $courier->id,
            'order_status' => OrderStatus::PickingUp,
        ]);

        Sanctum::actingAs($courier);

        // 1. Picking up -> In transit
        $resInTransit = $this->patchJson("/api/courier/deliveries/{$order->order_number}/status", [
            'status' => 'in_transit',
        ]);

        $resInTransit->assertStatus(200)
            ->assertJsonPath('data.order_status', 'in_transit');

        $order->refresh();
        $this->assertSame(OrderStatus::InTransit, $order->order_status);

        // 2. In transit -> Completed
        $resCompleted = $this->patchJson("/api/courier/deliveries/{$order->order_number}/status", [
            'status' => 'completed',
        ]);

        $resCompleted->assertStatus(200)
            ->assertJsonPath('data.order_status', 'completed');

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->order_status);
        $this->assertNotNull($order->completed_at);
    }

    public function test_completing_cod_order_automatically_marks_payment_as_paid(): void
    {
        $courier = User::factory()->courier()->create();
        $order = Order::factory()->create([
            'courier_id' => $courier->id,
            'order_status' => OrderStatus::InTransit,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'payment_method' => PaymentMethod::Cod,
            'paid_at' => null,
            'verified_by' => null,
        ]);

        Sanctum::actingAs($courier);

        $response = $this->patchJson("/api/courier/deliveries/{$order->order_number}/status", [
            'status' => 'completed',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.order_status', 'completed')
            ->assertJsonPath('data.payment_status', 'paid');

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);

        $payment = $order->payment;
        $this->assertSame($courier->id, $payment->verified_by);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_courier_cannot_skip_status_transitions(): void
    {
        $courier = User::factory()->courier()->create();
        $order = Order::factory()->create([
            'courier_id' => $courier->id,
            'order_status' => OrderStatus::PickingUp,
        ]);

        Sanctum::actingAs($courier);

        // Trying to skip in_transit directly to completed
        $response = $this->patchJson("/api/courier/deliveries/{$order->order_number}/status", [
            'status' => 'completed',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', "Pesanan hanya dapat diselesaikan ('completed') jika saat ini 'in_transit'.");
    }
}
