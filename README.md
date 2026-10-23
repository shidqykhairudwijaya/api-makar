# api-makar — B2B Marketplace API

Backend RESTful API untuk platform **B2B Marketplace Pedagang Pasar**, yang menjembatani **Pedagang Pasar Induk (Seller)** dengan **Pedagang Pasar Kecil (Buyer)**. Pengiriman dan penjemputan barang dikelola oleh **Tim Operasional (Admin/Courier)**.

---

## Tech Stack

| Layer | Teknologi |
|---|---|
| Backend Framework | Laravel 11 (PHP 8.3) |
| Database | MySQL |
| Autentikasi | Laravel Sanctum (Bearer Token) |
| Frontend (terpisah) | Next.js |

---

## Status Test Suite

```
✅  35 Tests Passed — 182 Assertions
```

---

## Persyaratan

- PHP 8.3+
- Composer
- MySQL 8+

---

## Cara Menjalankan Proyek

**1. Clone & Install Dependensi**
```bash
git clone <repo-url>
cd api-makar
composer install
```

**2. Setup Environment**
```bash
cp .env.example .env
php artisan key:generate
```

Edit file `.env` dan isi konfigurasi database:
```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=api_makar
DB_USERNAME=root
DB_PASSWORD=
```

**3. Migrasi & Seed Database**
```bash
php artisan migrate
# Opsional: isi data contoh
php artisan db:seed
```

**4. Jalankan Server**
```bash
php artisan serve
```

Server berjalan di: `http://localhost:8000`

---

## Struktur Database

Aplikasi ini memiliki 8 tabel utama:

```
users                → Akun semua pengguna (admin, seller, buyer, courier)
user_profiles        → Profil toko/kios pedagang
categories           → Kategori komoditas (Sayuran, Bumbu, Daging, dll.)
products             → Produk/komoditas yang dijual seller
carts                → Keranjang belanja buyer (belum di-checkout)
orders               → Pesanan yang sudah diproses
order_items          → Rincian item per pesanan
payments             → Catatan pembayaran manual / COD
```

---

## Panduan Integrasi Frontend (Next.js)

### Base URL & Header Wajib

```
Base URL : http://localhost:8000/api
```

Setiap request **wajib** menyertakan header berikut:
```http
Accept: application/json
Content-Type: application/json
```

> **Penting:** Header `Accept: application/json` memastikan semua respons error (validasi, unauthenticated, dsb.) dikembalikan dalam format JSON, bukan HTML.

### Menyimpan & Mengirim Token

Setelah Login atau Register berhasil, simpan token yang diterima:
```typescript
// Simpan token
localStorage.setItem('token', response.data.token);

// Kirim di setiap request protected
headers: {
  'Authorization': `Bearer ${localStorage.getItem('token')}`
}
```

### Helper `apiFetch` (Contoh Next.js)

Buat file `lib/api.ts`:
```typescript
const BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost:8000/api';

export async function apiFetch(endpoint: string, options: RequestInit = {}) {
  const token = typeof window !== 'undefined' ? localStorage.getItem('token') : null;

  const headers: Record<string, string> = {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string>),
  };

  if (token) headers['Authorization'] = `Bearer ${token}`;

  const response = await fetch(`${BASE_URL}${endpoint}`, { ...options, headers });
  const data = await response.json().catch(() => null);

  if (!response.ok) {
    throw {
      status: response.status,
      message: data?.message || 'Terjadi kesalahan.',
      errors: data?.errors || null, // Error validasi per-field
    };
  }

  return data;
}
```

---

## Referensi API Endpoint

### Autentikasi — `/api/auth`

#### `POST /api/auth/register`
Mendaftarkan akun baru. Hanya role `seller` dan `buyer` yang bisa mendaftar mandiri.

**Request Body:**
```json
{
  "name": "Bu Dewi",
  "phone": "081234567890",
  "email": "dewi@email.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "buyer",
  "store_name": "Warung Sayur Bu Dewi",
  "market_name": "Pasar Minggu",
  "block_number": "Kios B-12",
  "full_address": "Jl. Pasar Minggu Raya No. 5"
}
```

> `email`, `store_name`, `market_name`, `block_number`, `full_address` bersifat **opsional**.

**Response `201 Created`:**
```json
{
  "message": "Registrasi berhasil.",
  "data": {
    "user": {
      "id": 1,
      "name": "Bu Dewi",
      "email": null,
      "phone": "081234567890",
      "role": "buyer",
      "is_active": true,
      "profile": {
        "store_name": "Warung Sayur Bu Dewi",
        "market_name": "Pasar Minggu",
        "block_number": "Kios B-12",
        "full_address": "Jl. Pasar Minggu Raya No. 5",
        "latitude": null,
        "longitude": null
      },
      "created_at": "2026-10-23T03:09:00.000000Z"
    },
    "token": "1|abc123XYZ..."
  }
}
```

---

#### `POST /api/auth/login`
Login menggunakan **Nomor HP + Password**.

**Request Body:**
```json
{
  "phone": "081234567890",
  "password": "password123"
}
```

**Response `200 OK`:** Sama strukturnya dengan Register.

**Response Error `401`:**
```json
{ "message": "Nomor HP atau password salah." }
```

**Response Error `403`:**
```json
{ "message": "Akun Anda tidak aktif. Hubungi administrator." }
```

---

#### `GET /api/auth/me` 🔒
Mengembalikan data user yang sedang login beserta profil tokonya.

**Header:** `Authorization: Bearer {token}`

---

#### `POST /api/auth/logout` 🔒
Mencabut token aktif (logout).

**Header:** `Authorization: Bearer {token}`

**Response `200 OK`:**
```json
{ "message": "Logout berhasil." }
```

---

### Kategori — `/api/categories`

#### `GET /api/categories`
Mengembalikan seluruh kategori komoditas beserta jumlah produk aktif.

**Response `200 OK`:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Sayuran Hijau",
      "slug": "sayuran-hijau",
      "image_url": "https://...",
      "products_count": 12
    }
  ]
}
```

---

### Produk — `/api/products`

#### `GET /api/products`
Etalase komoditas semua seller, dengan dukungan filter dan pagination.

**Query Parameters (semua opsional):**

| Parameter | Contoh | Keterangan |
|---|---|---|
| `category` | `?category=sayuran-hijau` | Filter berdasarkan slug kategori |
| `category_id` | `?category_id=3` | Filter berdasarkan ID kategori |
| `search` | `?search=cabai` | Pencarian nama produk |
| `seller_id` | `?seller_id=5` | Produk dari seller tertentu |
| `sort` | `?sort=price_asc` | Urutan: `price_asc` \| `price_desc` \| `newest` |
| `per_page` | `?per_page=12` | Jumlah per halaman (default: 15) |
| `page` | `?page=2` | Nomor halaman |

**Response `200 OK`:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Cabai Merah Keriting Super",
      "slug": "cabai-merah-keriting-super",
      "description": "Cabai segar langsung dari petani.",
      "price": 85000,
      "formatted_price": "Rp 85.000",
      "unit": "kg",
      "min_order": 1,
      "stock": 200,
      "image_url": "https://...",
      "is_available": true,
      "category": { "id": 2, "name": "Bawang & Cabai", "slug": "bawang-cabai" },
      "seller": {
        "id": 3,
        "name": "Pak Hendra",
        "profile": {
          "store_name": "Kios Cabai Hendra",
          "market_name": "Pasar Induk Kramat Jati"
        }
      }
    }
  ],
  "meta": { "current_page": 1, "last_page": 4, "per_page": 15, "total": 56 },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

---

#### `GET /api/products/{slug}`
Mengembalikan detail satu produk berdasarkan slug-nya.

**Contoh:** `GET /api/products/cabai-merah-keriting-super`

**Response Error `404`:**
```json
{ "message": "Produk tidak ditemukan atau tidak tersedia." }
```

---

### Produk — Khusus Seller 🔒

Semua endpoint berikut membutuhkan token **role Seller**.

**Header:** `Authorization: Bearer {token}`

---

#### `GET /api/seller/products`
Daftar seluruh produk milik seller yang sedang login (termasuk yang stok habis / tidak aktif).

**Query Parameters:** `?search=cabai`, `?page=1`, `?per_page=15`

---

#### `POST /api/products`
Menambahkan komoditas baru ke kios seller.

**Request Body:**
```json
{
  "category_id": 2,
  "name": "Cabai Hijau Keriting",
  "description": "Cabai hijau segar dari petani lokal.",
  "price": 40000,
  "unit": "kg",
  "min_order": 2,
  "stock": 150,
  "image_url": "https://...",
  "is_available": true
}
```

> **Nilai `unit` yang valid:** `kg` | `ikat` | `karung` | `peti`

> `seller_id` otomatis diambil dari token — **tidak perlu dikirim dari frontend**.

**Response `201 Created`:** Mengembalikan `ProductResource` lengkap termasuk `seller` dan `category`.

---

#### `PUT /api/products/{slug}`
Mengupdate data produk milik seller. Semua field bersifat **opsional** — hanya kirim field yang ingin diubah.

**Contoh (hanya update harga dan stok):**
```json
{
  "price": 45000,
  "stock": 80
}
```

**Response Error `403`:**
```json
{ "message": "Anda tidak memiliki akses untuk mengubah produk ini." }
```

---

#### `DELETE /api/products/{slug}`
Menghapus produk (Soft Delete — data tetap ada di database, tidak muncul di katalog publik).

**Response `204 No Content`** (tanpa body)

**Response Error `403`:**
```json
{ "message": "Anda tidak memiliki akses untuk menghapus produk ini." }
```

---

## Format Error Standar

**Validation Error `422`:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "phone": ["Nomor HP sudah terdaftar."],
    "role": ["Role yang dipilih tidak diizinkan untuk registrasi mandiri."]
  }
}
```

**Unauthenticated `401`:**
```json
{ "message": "Unauthenticated." }
```

**Forbidden `403`:**
```json
{ "message": "Akses ditolak. Peran tidak sesuai." }
```

---

## Modul API yang Tersedia

| Modul | Status |
|---|:---:|
| Autentikasi (Register, Login, Logout, Me) | ✅ Selesai |
| Kategori Komoditas | ✅ Selesai |
| Katalog Produk (Publik + Seller CRUD) | ✅ Selesai |
| Keranjang Belanja (Cart) | 🔄 Dalam Rencana |
| Pesanan & Logistik (Order) | 🔄 Dalam Rencana |
| Pembayaran & Verifikasi (Payment) | 🔄 Dalam Rencana |

---

## Menjalankan Test Suite

```bash
php artisan test --compact
```

```
✅  35 Tests — 182 Assertions — All Passed
```
