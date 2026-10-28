<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_payment_endpoints(): void
    {
        $order = Order::factory()->create();

        $this->postJson("/api/orders/{$order->order_number}/payment")->assertStatus(401);
        $this->getJson("/api/orders/{$order->order_number}/payment")->assertStatus(401);
        $this->getJson('/api/admin/payments')->assertStatus(401);
    }

    public function test_buyer_cannot_submit_payment_for_another_buyers_order(): void
    {
        $buyer1 = User::factory()->buyer()->create();
        $buyer2 = User::factory()->buyer()->create();

        $order = Order::factory()->create(['buyer_id' => $buyer1->id]);

        Sanctum::actingAs($buyer2);

        $response = $this->postJson("/api/orders/{$order->order_number}/payment", [
            'payment_method' => 'cod',
        ]);

        $response->assertStatus(403);
    }

    public function test_manual_transfer_requires_payment_proof_image(): void
    {
        $buyer = User::factory()->buyer()->create();
        $order = Order::factory()->create(['buyer_id' => $buyer->id]);

        Sanctum::actingAs($buyer);

        $response = $this->postJson("/api/orders/{$order->order_number}/payment", [
            'payment_method' => 'manual_transfer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_proof']);
    }

    public function test_buyer_can_submit_manual_transfer_with_image_upload(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->buyer()->create();
        $order = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'total_amount' => 500000,
            'order_status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        Sanctum::actingAs($buyer);

        $file = UploadedFile::fake()->image('struk_transfer.jpg', 600, 800);

        $response = $this->postJson("/api/orders/{$order->order_number}/payment", [
            'payment_method' => 'manual_transfer',
            'payment_proof' => $file,
            'external_reference_id' => 'BCA-TRX-998822',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.payment_method', 'manual_transfer')
            ->assertJsonPath('data.amount_paid', 500000)
            ->assertJsonPath('data.external_reference_id', 'BCA-TRX-998822')
            ->assertJsonPath('data.verified_by', null);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => PaymentMethod::ManualTransfer->value,
            'amount_paid' => 500000,
            'external_reference_id' => 'BCA-TRX-998822',
        ]);

        $order->refresh();
        $this->assertSame(OrderStatus::Pending, $order->order_status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
    }

    public function test_buyer_can_submit_cod_payment_and_order_becomes_confirmed(): void
    {
        $buyer = User::factory()->buyer()->create();
        $order = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'total_amount' => 350000,
            'order_status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        Sanctum::actingAs($buyer);

        $response = $this->postJson("/api/orders/{$order->order_number}/payment", [
            'payment_method' => 'cod',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.payment_method', 'cod')
            ->assertJsonPath('data.amount_paid', 350000);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => PaymentMethod::Cod->value,
            'amount_paid' => 350000,
        ]);

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->order_status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
    }

    public function test_buyer_cannot_submit_payment_for_cancelled_or_paid_order(): void
    {
        $buyer = User::factory()->buyer()->create();

        $cancelledOrder = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'order_status' => OrderStatus::Cancelled,
        ]);

        $paidOrder = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'payment_status' => PaymentStatus::Paid,
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson("/api/orders/{$cancelledOrder->order_number}/payment", [
            'payment_method' => 'cod',
        ])->assertStatus(422);

        $this->postJson("/api/orders/{$paidOrder->order_number}/payment", [
            'payment_method' => 'cod',
        ])->assertStatus(422);
    }

    public function test_buyer_can_view_payment_detail(): void
    {
        $buyer = User::factory()->buyer()->create();
        $order = Order::factory()->create(['buyer_id' => $buyer->id]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount_paid' => $order->total_amount,
        ]);

        Sanctum::actingAs($buyer);

        $response = $this->getJson("/api/orders/{$order->order_number}/payment");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $payment->id)
            ->assertJsonPath('data.amount_paid', (int) $order->total_amount);
    }

    public function test_non_admin_cannot_access_admin_payment_endpoints(): void
    {
        $buyer = User::factory()->buyer()->create();
        $payment = Payment::factory()->create();

        Sanctum::actingAs($buyer);

        $this->getJson('/api/admin/payments')->assertStatus(403);
        $this->postJson("/api/admin/payments/{$payment->id}/verify", ['action' => 'approve'])->assertStatus(403);
    }

    public function test_admin_can_list_payments_with_filter(): void
    {
        $admin = User::factory()->admin()->create();

        $verifiedPayment = Payment::factory()->verified($admin)->create();
        $unverifiedPayment = Payment::factory()->create(['verified_by' => null]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/payments?verified=false');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unverifiedPayment->id);
    }

    public function test_admin_can_approve_payment_and_order_becomes_paid_and_confirmed(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create([
            'order_status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'verified_by' => null,
            'paid_at' => null,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/payments/{$payment->id}/verify", [
            'action' => 'approve',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Pembayaran berhasil diverifikasi dan disetujui.')
            ->assertJsonPath('data.verified_by', $admin->id);

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->order_status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);

        $payment->refresh();
        $this->assertSame($admin->id, $payment->verified_by);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_admin_can_reject_payment_with_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create([
            'order_status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'verified_by' => null,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/payments/{$payment->id}/verify", [
            'action' => 'reject',
            'rejection_reason' => 'Nominal transfer kurang Rp 25.000.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Pembayaran telah ditolak.')
            ->assertJsonPath('data.verified_by', null);

        $order->refresh();
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertStringContainsString('Nominal transfer kurang Rp 25.000', (string) $order->notes);
    }

    public function test_admin_rejection_requires_rejection_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = Payment::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/payments/{$payment->id}/verify", [
            'action' => 'reject',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason']);
    }
}
