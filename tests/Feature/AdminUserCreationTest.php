<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_new_seller_with_profile(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $payload = [
            'name' => 'Juragan Tempe Karawang',
            'phone' => '081298765432',
            'email' => 'tempe.berkah@makar.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'seller',
            'store_name' => 'Kios Tempe Mendoan Berkah',
            'market_name' => 'Pasar Johar Karawang',
            'block_number' => 'Blok B No. 12',
            'full_address' => 'Jl. Tuparev, Nagasari, Karawang Barat',
            'latitude' => -6.304000,
            'longitude' => 107.306000,
        ];

        $response = $this->postJson('/api/admin/users', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Akun pengguna baru berhasil didaftarkan.')
            ->assertJsonPath('data.name', 'Juragan Tempe Karawang')
            ->assertJsonPath('data.role', 'seller')
            ->assertJsonPath('data.phone', '081298765432')
            ->assertJsonPath('data.profile.store_name', 'Kios Tempe Mendoan Berkah');

        $this->assertDatabaseHas('users', [
            'phone' => '081298765432',
            'role' => 'seller',
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'store_name' => 'Kios Tempe Mendoan Berkah',
            'market_name' => 'Pasar Johar Karawang',
        ]);
    }

    public function test_admin_can_create_new_courier_user(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $payload = [
            'name' => 'Agus Kurir Cepat',
            'phone' => '081399887766',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'courier',
        ];

        $response = $this->postJson('/api/admin/users', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Agus Kurir Cepat')
            ->assertJsonPath('data.role', 'courier');

        $this->assertDatabaseHas('users', [
            'phone' => '081399887766',
            'role' => 'courier',
        ]);
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $seller = User::factory()->seller()->create();
        Sanctum::actingAs($seller);

        $this->postJson('/api/admin/users', [
            'name' => 'Hacker',
            'phone' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])->assertStatus(403);
    }

    public function test_admin_create_user_validates_phone_uniqueness_and_password_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        User::factory()->create(['phone' => '081200000001']);

        // Duplicate phone
        $this->postJson('/api/admin/users', [
            'name' => 'Test User',
            'phone' => '081200000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'buyer',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        // Password mismatch
        $this->postJson('/api/admin/users', [
            'name' => 'Test User',
            'phone' => '081200000002',
            'password' => 'password123',
            'password_confirmation' => 'different_password',
            'role' => 'buyer',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
