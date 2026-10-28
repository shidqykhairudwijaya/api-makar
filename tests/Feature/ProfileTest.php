<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_profile_endpoints(): void
    {
        $this->getJson('/api/profile')->assertStatus(401);
        $this->putJson('/api/profile', ['name' => 'Baru'])->assertStatus(401);
        $this->putJson('/api/profile/password', ['current_password' => 'secret1234'])->assertStatus(401);
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Pak Joko',
            'phone' => '081234567890',
            'role' => UserRole::Seller,
        ]);

        UserProfile::factory()->create([
            'user_id' => $user->id,
            'store_name' => 'Kios Sayur Segar',
            'market_name' => 'Pasar Induk Kramat Jati',
            'block_number' => 'Blok B No. 05',
            'full_address' => 'Jl. Pasar Kramat Jati Blok B',
            'latitude' => -6.28450000,
            'longitude' => 106.84410000,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Pak Joko')
            ->assertJsonPath('data.phone', '081234567890')
            ->assertJsonPath('data.role', 'seller')
            ->assertJsonPath('data.profile.store_name', 'Kios Sayur Segar')
            ->assertJsonPath('data.profile.market_name', 'Pasar Induk Kramat Jati')
            ->assertJsonPath('data.profile.block_number', 'Blok B No. 05');
    }

    public function test_seller_can_update_profile_and_stall_information(): void
    {
        $seller = User::factory()->create([
            'name' => 'Pak Ahmad',
            'phone' => '081211112222',
            'role' => UserRole::Seller,
        ]);

        UserProfile::factory()->create([
            'user_id' => $seller->id,
            'store_name' => 'Kios Cabai Lama',
            'market_name' => 'Pasar Induk Kramat Jati',
            'block_number' => 'Blok A No. 1',
        ]);

        Sanctum::actingAs($seller);

        $payload = [
            'name' => 'Haji Ahmad',
            'phone' => '081233334444',
            'email' => 'haji.ahmad@pasarinduk.test',
            'store_name' => 'Kios Cabai Barokah Jaya',
            'market_name' => 'Pasar Induk Cibitung',
            'block_number' => 'Blok D No. 22',
            'full_address' => 'Kawasan Pasar Induk Cibitung No. 22',
            'latitude' => -6.25000000,
            'longitude' => 107.10000000,
        ];

        $response = $this->putJson('/api/profile', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Profil berhasil diperbarui.')
            ->assertJsonPath('data.name', 'Haji Ahmad')
            ->assertJsonPath('data.phone', '081233334444')
            ->assertJsonPath('data.profile.store_name', 'Kios Cabai Barokah Jaya')
            ->assertJsonPath('data.profile.market_name', 'Pasar Induk Cibitung')
            ->assertJsonPath('data.profile.block_number', 'Blok D No. 22');

        $this->assertDatabaseHas('users', [
            'id' => $seller->id,
            'name' => 'Haji Ahmad',
            'phone' => '081233334444',
            'email' => 'haji.ahmad@pasarinduk.test',
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $seller->id,
            'store_name' => 'Kios Cabai Barokah Jaya',
            'market_name' => 'Pasar Induk Cibitung',
            'block_number' => 'Blok D No. 22',
        ]);
    }

    public function test_buyer_can_update_profile_and_delivery_address(): void
    {
        $buyer = User::factory()->create([
            'name' => 'Bu Siti',
            'phone' => '081399998888',
            'role' => UserRole::Buyer,
        ]);

        UserProfile::factory()->create([
            'user_id' => $buyer->id,
            'store_name' => 'Warung Sayur Bu Siti',
            'full_address' => 'Jl. Melati No. 10',
        ]);

        Sanctum::actingAs($buyer);

        $payload = [
            'name' => 'Ibu Siti Khadijah',
            'store_name' => 'Warung Berkah Siti',
            'full_address' => 'Jl. Mawar No. 45 RT 02/05',
            'latitude' => -6.21000000,
            'longitude' => 106.85000000,
        ];

        $response = $this->putJson('/api/profile', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Ibu Siti Khadijah')
            ->assertJsonPath('data.profile.store_name', 'Warung Berkah Siti')
            ->assertJsonPath('data.profile.full_address', 'Jl. Mawar No. 45 RT 02/05');

        $this->assertDatabaseHas('users', [
            'id' => $buyer->id,
            'name' => 'Ibu Siti Khadijah',
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $buyer->id,
            'store_name' => 'Warung Berkah Siti',
            'full_address' => 'Jl. Mawar No. 45 RT 02/05',
        ]);
    }

    public function test_user_cannot_update_phone_to_existing_phone_number_of_another_user(): void
    {
        User::factory()->create(['phone' => '081234567890']);

        $user = User::factory()->create(['phone' => '081299999999']);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile', [
            'phone' => '081234567890',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_user_can_keep_their_own_phone_when_updating_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Nama Lama',
            'phone' => '081234567890',
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile', [
            'name' => 'Nama Baru',
            'phone' => '081234567890',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.phone', '081234567890');
    }

    public function test_user_without_profile_record_has_one_created_on_profile_update(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Courier,
        ]);
        // Profile intentionally not created

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile', [
            'store_name' => 'Kurir Express Pos 1',
            'full_address' => 'Jl. Logistik Pasar No. 8',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.profile.store_name', 'Kurir Express Pos 1');

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'store_name' => 'Kurir Express Pos 1',
        ]);
    }

    public function test_user_can_update_password_with_valid_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password_lama123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'password_lama123',
            'password' => 'password_baru123',
            'password_confirmation' => 'password_baru123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Password berhasil diperbarui.');

        $user->refresh();
        $this->assertTrue(Hash::check('password_baru123', $user->password));
    }

    public function test_user_cannot_update_password_with_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password_lama123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'salah_password',
            'password' => 'password_baru123',
            'password_confirmation' => 'password_baru123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }
}
