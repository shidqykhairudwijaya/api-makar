<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_management_endpoints(): void
    {
        $buyer = User::factory()->buyer()->create();
        Sanctum::actingAs($buyer);

        $this->getJson('/api/admin/orders')->assertStatus(403);
        $this->getJson('/api/admin/users')->assertStatus(403);
    }

    public function test_admin_can_list_and_filter_orders(): void
    {
        $admin = User::factory()->admin()->create();

        $unassignedOrder = Order::factory()->create([
            'courier_id' => null,
            'order_status' => OrderStatus::Confirmed,
        ]);

        $assignedOrder = Order::factory()->create([
            'courier_id' => User::factory()->courier()->create()->id,
            'order_status' => OrderStatus::Completed,
        ]);

        Sanctum::actingAs($admin);

        // Filter unassigned
        $response = $this->getJson('/api/admin/orders?unassigned=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unassignedOrder->id);

        // Filter status
        $resCompleted = $this->getJson('/api/admin/orders?order_status=completed');
        $resCompleted->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assignedOrder->id);
    }

    public function test_admin_can_view_order_details(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin/orders/{$order->order_number}");

        $response->assertStatus(200)
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_admin_can_assign_courier_to_confirmed_order(): void
    {
        $admin = User::factory()->admin()->create();
        $courier = User::factory()->courier()->create(['is_active' => true]);

        $order = Order::factory()->create([
            'courier_id' => null,
            'order_status' => OrderStatus::Confirmed,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/orders/{$order->order_number}/assign-courier", [
            'courier_id' => $courier->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Kurir berhasil ditugaskan.')
            ->assertJsonPath('data.courier_id', $courier->id)
            ->assertJsonPath('data.order_status', 'picking_up');

        $order->refresh();
        $this->assertSame($courier->id, $order->courier_id);
        $this->assertSame(OrderStatus::PickingUp, $order->order_status);
    }

    public function test_admin_cannot_assign_inactive_courier_or_non_courier(): void
    {
        $admin = User::factory()->admin()->create();
        $inactiveCourier = User::factory()->courier()->create(['is_active' => false]);
        $buyer = User::factory()->buyer()->create(['is_active' => true]);

        $order = Order::factory()->create(['order_status' => OrderStatus::Confirmed]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/orders/{$order->order_number}/assign-courier", [
            'courier_id' => $inactiveCourier->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['courier_id']);

        $this->postJson("/api/admin/orders/{$order->order_number}/assign-courier", [
            'courier_id' => $buyer->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['courier_id']);
    }

    public function test_admin_cannot_assign_courier_to_completed_or_cancelled_order(): void
    {
        $admin = User::factory()->admin()->create();
        $courier = User::factory()->courier()->create(['is_active' => true]);

        $completedOrder = Order::factory()->create(['order_status' => OrderStatus::Completed]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/orders/{$completedOrder->order_number}/assign-courier", [
            'courier_id' => $courier->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', "Pesanan dengan status 'completed' tidak dapat ditugaskan kurir.");
    }

    public function test_admin_can_cancel_order_and_inventory_is_restored(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['stock' => 15]);

        $order = Order::factory()->create(['order_status' => OrderStatus::Confirmed]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/orders/{$order->order_number}/cancel", [
            'reason' => 'Pasar tergenang banjir darurat.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Pesanan berhasil dibatalkan oleh admin.')
            ->assertJsonPath('data.order_status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Admin: Pasar tergenang banjir darurat.');

        $product->refresh();
        $this->assertSame(20, $product->stock); // 15 + 5

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->order_status);
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_admin_can_list_and_filter_users(): void
    {
        $admin = User::factory()->admin()->create();

        User::factory()->count(2)->seller()->create();
        User::factory()->count(1)->buyer()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users?role=seller');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_view_single_user_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $seller = User::factory()->seller()->create(['name' => 'Juragan Bawang']);
        UserProfile::factory()->create([
            'user_id' => $seller->id,
            'store_name' => 'Kios Bawang Berkah',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin/users/{$seller->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Juragan Bawang')
            ->assertJsonPath('data.profile.store_name', 'Kios Bawang Berkah');
    }

    public function test_admin_can_deactivate_user_and_tokens_are_revoked(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->seller()->create(['is_active' => true]);

        // User has an active personal access token
        $user->createToken('test-token');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/users/{$user->id}/status", [
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Status akun pengguna berhasil diperbarui.')
            ->assertJsonPath('data.is_active', false);

        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_deactivated_user_cannot_access_protected_routes(): void
    {
        $inactiveUser = User::factory()->seller()->create(['is_active' => false]);
        Sanctum::actingAs($inactiveUser);

        $this->getJson('/api/profile')->assertStatus(403)
            ->assertJsonPath('message', 'Akun Anda tidak aktif. Hubungi administrator.');
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/users/{$admin->id}/status", [
            'is_active' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Anda tidak dapat menonaktifkan akun administrator Anda sendiri.');

        $admin->refresh();
        $this->assertTrue($admin->is_active);
    }
}
