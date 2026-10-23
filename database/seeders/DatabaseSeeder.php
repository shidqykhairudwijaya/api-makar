<?php

namespace Database\Seeders;

use App\Enums\ProductUnit;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Categories
        $this->call(CategorySeeder::class);

        // 2. Admin User
        $admin = User::firstOrCreate(
            ['phone' => '081100000001'],
            [
                'name' => 'Admin Operasional Kampus',
                'email' => 'admin@makar.id',
                'password' => Hash::make('password123'),
                'role' => UserRole::Admin,
                'is_active' => true,
            ]
        );

        // 3. Seller (Pedagang Pasar Induk)
        $seller = User::firstOrCreate(
            ['phone' => '081200000001'],
            [
                'name' => 'Pak Haji Syahril',
                'email' => 'syahril.cabai@makar.id',
                'password' => Hash::make('password123'),
                'role' => UserRole::Seller,
                'is_active' => true,
            ]
        );

        UserProfile::firstOrCreate(
            ['user_id' => $seller->id],
            [
                'store_name' => 'UD Berkah Cabai Segar',
                'market_name' => 'Pasar Induk Kramat Jati',
                'block_number' => 'Blok B-1 No. 45-48',
                'full_address' => 'Jl. Raya Bogor No.KM. 19, RW.4, Kramat Jati, Jakarta Timur',
                'latitude' => -6.27510000,
                'longitude' => 106.86820000,
            ]
        );

        // 4. Buyer (Pedagang Pasar Kecil)
        $buyer = User::firstOrCreate(
            ['phone' => '081300000001'],
            [
                'name' => 'Bu Dewi Sri',
                'email' => 'dewi.warung@makar.id',
                'password' => Hash::make('password123'),
                'role' => UserRole::Buyer,
                'is_active' => true,
            ]
        );

        UserProfile::firstOrCreate(
            ['user_id' => $buyer->id],
            [
                'store_name' => 'Kios Sayur Bu Dewi',
                'market_name' => 'Pasar Minggu',
                'block_number' => 'Lantai Dasar Kios No. 12',
                'full_address' => 'Jl. Pasar Minggu Raya No. 5, Jakarta Selatan',
                'latitude' => -6.28450000,
                'longitude' => 106.84410000,
            ]
        );

        // 5. Courier
        User::firstOrCreate(
            ['phone' => '081400000001'],
            [
                'name' => 'Bambang Kurir Kampus',
                'email' => 'bambang.kurir@makar.id',
                'password' => Hash::make('password123'),
                'role' => UserRole::Courier,
                'is_active' => true,
            ]
        );

        // 6. Sample Products for Seller
        $categoryBumbu = Category::where('slug', 'bawang-cabai-bumbu-dapur')->first();
        if ($categoryBumbu !== null) {
            Product::firstOrCreate(
                ['slug' => 'cabai-merah-keriting-super-grosir'],
                [
                    'seller_id' => $seller->id,
                    'category_id' => $categoryBumbu->id,
                    'name' => 'Cabai Merah Keriting Super Grosir',
                    'description' => 'Cabai merah keriting kualitas nomor 1 dari petani Magelang langsung ke Pasar Induk. Segar, pedas tahan simpan.',
                    'price' => 38000,
                    'unit' => ProductUnit::Kg,
                    'min_order' => 5,
                    'stock' => 200,
                    'image_url' => 'https://images.unsplash.com/photo-1588252303782-cb80119abd6d?auto=format&fit=crop&w=600&q=80',
                    'is_available' => true,
                ]
            );

            Product::firstOrCreate(
                ['slug' => 'bawang-merah-brebes-karung-25kg'],
                [
                    'seller_id' => $seller->id,
                    'category_id' => $categoryBumbu->id,
                    'name' => 'Bawang Merah Brebes Karung 25kg',
                    'description' => 'Bawang merah Brebes kering panen, umbi padat ukuran sedang-besar per karung 25 kg.',
                    'price' => 750000,
                    'unit' => ProductUnit::Karung,
                    'min_order' => 1,
                    'stock' => 50,
                    'image_url' => 'https://images.unsplash.com/photo-1618512496248-a07fe83aa8cb?auto=format&fit=crop&w=600&q=80',
                    'is_available' => true,
                ]
            );
        }
    }
}
