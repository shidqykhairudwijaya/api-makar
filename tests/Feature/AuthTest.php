<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_as_seller_with_profile(): void
    {
        $payload = [
            'name' => 'Pak Basri',
            'phone' => '081234567890',
            'email' => 'basri@pasarinduk.test',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'role' => 'seller',
            'store_name' => 'Toko Cabai Berkah',
            'market_name' => 'Pasar Induk Kramat Jati',
            'block_number' => 'Blok C No. 12',
            'full_address' => 'Jl. Raya Bogor No. 1',
            'latitude' => -6.28450000,
            'longitude' => 106.84410000,
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'role',
                        'is_active',
                        'profile' => [
                            'id',
                            'user_id',
                            'store_name',
                            'market_name',
                            'block_number',
                            'full_address',
                            'latitude',
                            'longitude',
                        ],
                    ],
                    'token',
                ],
            ])
            ->assertJsonPath('data.user.phone', '081234567890')
            ->assertJsonPath('data.user.role', 'seller')
            ->assertJsonPath('data.user.profile.store_name', 'Toko Cabai Berkah');

        $this->assertDatabaseHas('users', [
            'phone' => '081234567890',
            'role' => 'seller',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'store_name' => 'Toko Cabai Berkah',
            'market_name' => 'Pasar Induk Kramat Jati',
        ]);
    }

    public function test_user_can_register_as_buyer_without_email_and_optional_fields(): void
    {
        $payload = [
            'name' => 'Bu Sumi',
            'phone' => '089876543210',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'buyer',
            'store_name' => 'Warung Sayur Bu Sumi',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.phone', '089876543210')
            ->assertJsonPath('data.user.email', null)
            ->assertJsonPath('data.user.role', 'buyer')
            ->assertJsonPath('data.user.profile.store_name', 'Warung Sayur Bu Sumi');

        $this->assertDatabaseHas('users', [
            'phone' => '089876543210',
            'email' => null,
            'role' => 'buyer',
        ]);
    }

    public function test_registration_rejects_admin_and_courier_roles(): void
    {
        $adminPayload = [
            'name' => 'Admin Baru',
            'phone' => '081122334455',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ];

        $this->postJson('/api/auth/register', $adminPayload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        $courierPayload = [
            'name' => 'Kurir Baru',
            'phone' => '081122334456',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'courier',
        ];

        $this->postJson('/api/auth/register', $courierPayload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_registration_fails_if_phone_is_already_registered(): void
    {
        User::factory()->create(['phone' => '081234567899']);

        $payload = [
            'name' => 'User Duplikat',
            'phone' => '081234567899',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'buyer',
        ];

        $this->postJson('/api/auth/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_registration_fails_if_email_is_already_registered(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $payload = [
            'name' => 'User Duplikat Email',
            'phone' => '081234567800',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'seller',
        ];

        $this->postJson('/api/auth/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_fails_if_password_confirmation_does_not_match(): void
    {
        $payload = [
            'name' => 'User Password Beda',
            'phone' => '081234567801',
            'password' => 'password123',
            'password_confirmation' => 'passwordBerbeda',
            'role' => 'buyer',
        ];

        $this->postJson('/api/auth/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'phone' => '081299998888',
            'password' => 'password123',
            'is_active' => true,
        ]);
        UserProfile::factory()->create(['user_id' => $user->id]);

        $response = $this->postJson('/api/auth/login', [
            'phone' => '081299998888',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'phone',
                        'role',
                        'profile',
                    ],
                    'token',
                ],
            ])
            ->assertJsonPath('data.user.phone', '081299998888');
    }

    public function test_login_fails_with_incorrect_password(): void
    {
        User::factory()->create([
            'phone' => '081299997777',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'phone' => '081299997777',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Nomor HP atau password salah.',
            ]);
    }

    public function test_login_fails_with_unregistered_phone(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'phone' => '089999999999',
            'password' => 'randompassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Nomor HP atau password salah.',
            ]);
    }

    public function test_login_fails_for_inactive_account(): void
    {
        User::factory()->create([
            'phone' => '081299996666',
            'password' => 'password123',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'phone' => '081299996666',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Akun Anda tidak aktif. Hubungi administrator.',
            ]);
    }

    public function test_login_revokes_previous_tokens_for_single_active_session(): void
    {
        $user = User::factory()->create([
            'phone' => '081299995555',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $user->createToken('token-lama-1');
        $user->createToken('token-lama-2');
        $this->assertCount(2, $user->tokens);

        $response = $this->postJson('/api/auth/login', [
            'phone' => '081299995555',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        // Previous tokens must be revoked, leaving only the newly issued token
        $this->assertCount(1, $user->fresh()->tokens);
    }

    public function test_authenticated_user_can_access_me_endpoint(): void
    {
        $user = User::factory()->seller()->create();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'store_name' => 'Kios Cabai Makmur',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'phone',
                    'role',
                    'profile' => [
                        'store_name',
                    ],
                ],
            ])
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.profile.store_name', 'Kios Cabai Makmur');
    }

    public function test_unauthenticated_user_cannot_access_me_endpoint(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logout berhasil.',
            ]);

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
