# 🛒 Makar API — B2B Wholesale Market Platform

[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php)](https://php.net)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-336791?style=for-the-badge&logo=postgresql)](https://www.postgresql.org/)
[![Sanctum](https://img.shields.io/badge/Auth-Sanctum-F55247?style=for-the-badge)](https://laravel.com/docs/sanctum)
[![Tests](https://img.shields.io/badge/Tests-110%20Passed-success?style=for-the-badge&logo=phpunit)](https://phpunit.de)
[![Code Style](https://img.shields.io/badge/Code%20Style-Pint-blue?style=for-the-badge)](https://laravel.com/docs/pint)

RESTful API Backend untuk platform **B2B Marketplace Grosir Pasar**, yang menjembatani **Pedagang Pasar Induk (Seller)** dengan **Pedagang Pasar Kecil / Warung Sayur (Buyer)**. Seluruh penjemputan barang fisik dari kios pasar induk dan pengantaran ke warung dikelola oleh **Tim Operasional Lapangan (Admin & Courier)**.

---

## 📑 Daftar Isi
- [Arsitektur & Aktor Sistem](#-arsitektur--aktor-sistem)
- [Fitur Utama & Alur Bisnis](#-fitur-utama--alur-bisnis)
- [Tech Stack & Dependensi](#-tech-stack--dependensi)
- [Instalasi & Panduan Menjalankan](#-instalasi--panduan-menjalankan)
- [Akun Demo Uji Coba](#-akun-demo-uji-coba)
- [Dokumentasi Postman](#-dokumentasi-postman)
- [Katalog Endpoint API (43 Endpoints)](#-katalog-endpoint-api-43-endpoints)
- [Lifecycle & State Machine Pesanan](#-lifecycle--state-machine-pesanan)
- [Pengujian & Jaminan Mutu](#-pengujian--jaminan-mutu)

---

## 👥 Arsitektur & Aktor Sistem

Sistem membagi operasional menjadi 4 peran (*Role*):

| Role | Nama Peran | Tugas & Hak Akses Utama |
|---|---|---|
| **`seller`** | Pedagang Pasar Induk | Mengelola kios/blok pasar, menerbitkan katalog grosir (`kg`, `ikat`, `karung`, `peti`), mengelola batas `min_order` dan stok, memantau antrean persiapan barang (`incoming-items`). |
| **`buyer`** | Pedagang Warung / Pasar Kecil | Menjelajah katalog, mengelola keranjang grosir, checkout multi-vendor pasar induk, membatalkan order pending, membayar via transfer atau COD. |
| **`courier`** | Kurir Lapangan Pasar | Mengklaim penugasan order, melihat rute nomor blok/kios Pasar Induk, memvalidasi fisik barang per kios (`picked_up` / `out_of_stock`), mengantar hingga tuntas (`in_transit` $\rightarrow$ `completed`). |
| **`admin`** | Tim Operasional Pasar | Memverifikasi transfer bank, mengawasi seluruh transaksi pasar, menugaskan kurir manual (*dispatching*), pembatalan darurat, mengaktifkan/menonaktifkan akun pedagang & kurir. |

---

## 🚀 Fitur Utama & Alur Bisnis

1. **Multi-Vendor Atomic Checkout**:
   - Pembeli dapat memesan barang dari aneka kios Pasar Induk sekaligus dalam satu transaksi.
   - Menggunakan `DB::transaction` untuk menjamin konsistensi ACID: pemotongan stok otomatis, pembersihan keranjang, dan penguncian harga beli (*snapshot* harga, nama, dan satuan komoditas).
2. **Snapshot Alamat & Koordinat GPS**:
   - Alamat pengiriman dikunci ke dalam `delivery_address_snapshot` pada saat order dibuat, baik mengambil dari profil toko pembeli maupun input override.
3. **Penyelesaian Otomatis COD**:
   - Jika order menggunakan metode COD (*Cash on Delivery*), saat kurir menyelesaikan pengantaran ke pembeli (`status = completed`), sistem otomatis menandai status pembayaran menjadi `paid`.
4. **Restorasi Stok Otomatis**:
   - Pembatalan pesanan (oleh Buyer pada status `pending` atau oleh Admin) otomatis mengembalikan kuantitas stok komoditas ke lapak masing-masing penjual.
5. **Auto-Revoke Session Security**:
   - Saat Admin menonaktifkan akun (`is_active = false`), seluruh token aktif Laravel Sanctum milik akun tersebut langsung dicabut seketika.

---

## 🛠 Tech Stack & Dependensi

- **Framework**: Laravel 13.x (PHP 8.3+)
- **Database**: PostgreSQL 14+ (Produksi & Lokal) / SQLite In-Memory (Automated Testing)
- **Autentikasi**: Laravel Sanctum (Token-based SPA & Mobile API)
- **Formatting**: Laravel Pint (PSR-12 Standard)
- **Testing**: PHPUnit 12

---

## 💻 Instalasi & Panduan Menjalankan

### 1. Persyaratan Sistem
- PHP 8.3 atau lebih baru dengan ekstensi aktif:
  `pdo_pgsql`, `pgsql`, `fileinfo`, `zip`, `mbstring`, `openssl`, `curl`, `intl`, `gd`
- Composer 2.x
- PostgreSQL server

### 2. Setup Proyek
```bash
# Clone repository
git clone <repository-url>
cd api-makar

# Pasang dependensi
composer install

# Siapkan file environment
cp .env.example .env

# Generate Application Encryption Key
php artisan key:generate
```

### 3. Konfigurasi Database PostgreSQL
Sesuaikan koneksi PostgreSQL pada file `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=api_makar
DB_USERNAME=postgres
DB_PASSWORD=secret
```

### 4. Migrasi, Seeding & Storage Link
```bash
# Jalankan migrasi tabel
php artisan migrate

# Isi data awal kategori, kios pasar induk, warung, dan kurir
php artisan db:seed

# Hubungkan symlink storage publik (untuk foto komoditas & bukti transfer)
php artisan storage:link
```

### 5. Jalankan Server Lokal
```bash
php artisan serve
```
Server berjalan di: `http://localhost:8000` (Base API: `http://localhost:8000/api`)

---

## 🔑 Akun Demo Uji Coba

Data uji coba telah terpasang otomatis melalui database seeder:

| Role | Nomor HP | Password | Identitas Toko / Lokasi Pasar |
|---|---|---|---|
| **`buyer`** | `081300000001` | `password123` | Kios Sayur Bu Dewi (Pasar Minggu, Lantai Dasar No. 12) |
| **`seller`** | `081200000001` | `password123` | UD Berkah Cabai Segar (Pasar Induk Kramat Jati, Blok B-1 No. 45-48) |
| **`courier`** | `081400000001` | `password123` | Bambang Kurir Lapangan Pasar |
| **`admin`** | `081100000001` | `password123` | Tim Operasional Pasar Makar |

---

## 📬 Dokumentasi Postman

Telah disediakan file koleksi Postman siap pakai di root proyek:
📁 **[`postman_collection.json`](./postman_collection.json)**

### Cara Import:
1. Buka aplikasi **Postman** (atau ekstensi *Thunder Client* di VS Code).
2. Klik **Import** $\rightarrow$ pilih file `postman_collection.json`.
3. Jalankan request **`POST Login (Auto-Save Token)`** $\rightarrow$ token otomatis disimpan ke variabel `{{token}}`. Seluruh request berikutnya otomatis terautentikasi!

---

## 📖 Katalog Endpoint API (43 Endpoints)

Header wajib pada setiap request:
```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer {{token}}  <-- untuk endpoint protected
```

### 1. Autentikasi & Profil (`/api/auth`, `/api/profile`)
| Method | Endpoint | Hak Akses | Deskripsi |
|:---:|---|:---:|---|
| `POST` | `/api/auth/register` | Publik | Registrasi akun baru (Seller / Buyer) beserta profil lapak |
| `POST` | `/api/auth/login` | Publik | Login via nomor HP & password (mengembalikan token Sanctum) |
| `POST` | `/api/auth/logout` | 🔒 Token | Logout dan mencabut token aktif |
| `GET` | `/api/auth/me` | 🔒 Token | Mengambil data akun dan profil user yang sedang aktif |
| `GET` | `/api/profile` | 🔒 Token | Mengambil profil lapak, nomor HP, alamat, dan titik koordinat |
| `PUT` | `/api/profile` | 🔒 Token | Memperbarui profil lapak/warung, blok pasar, dan alamat |
| `PUT` | `/api/profile/password` | 🔒 Token | Memperbarui password akun |

### 2. Katalog Publik (`/api/categories`, `/api/products`)
| Method | Endpoint | Hak Akses | Deskripsi |
|:---:|---|:---:|---|
| `GET` | `/api/categories` | Publik | Daftar kategori komoditas grosir |
| `GET` | `/api/products` | Publik | Etalase komoditas (filter: `search`, `category`, `min_price`, `max_price`, paginasi) |
| `GET` | `/api/products/{slug}` | Publik | Detail lengkap komoditas berdasarkan slug |

### 3. Modul Buyer — Keranjang & Pesanan
| Method | Endpoint | Hak Akses | Deskripsi |
|:---:|---|:---:|---|
| `GET` | `/api/cart` | 🔒 Buyer/Admin | Melihat isi keranjang belanja grosir & ringkasan harga |
| `POST` | `/api/cart` | 🔒 Buyer/Admin | Memasukkan barang ke keranjang (validasi `min_order` & stok) |
| `PUT` | `/api/cart/{id}` | 🔒 Buyer/Admin | Mengubah kuantitas item keranjang |
| `DELETE`| `/api/cart/{id}` | 🔒 Buyer/Admin | Menghapus satu item dari keranjang |
| `DELETE`| `/api/cart` | 🔒 Buyer/Admin | Mengosongkan keranjang belanja |
| `POST` | `/api/orders/checkout` | 🔒 Buyer/Admin | **Checkout pesanan grosir multi-kios** (potong stok & snapshot harga) |
| `GET` | `/api/orders` | 🔒 Buyer/Admin | Riwayat pesanan pembeli (filter: `order_status`, `payment_status`) |
| `GET` | `/api/orders/{order_number}` | 🔒 Semua Role | Detail pesanan, kurir, komoditas per kios lapak |
| `POST` | `/api/orders/{order_number}/cancel` | 🔒 Buyer/Admin | Membatalkan pesanan (hanya jika `pending`, stok pulih) |
| `POST` | `/api/orders/{order_number}/payment` | 🔒 Buyer/Admin | Bayar pesanan: unggah bukti transfer atau pilih `cod` |
| `GET` | `/api/orders/{order_number}/payment` | 🔒 Buyer/Admin | Melihat rincian status pembayaran |

### 4. Modul Seller — Kios Pasar Induk
| Method | Endpoint | Hak Akses | Deskripsi |
|:---:|---|:---:|---|
| `GET` | `/api/seller/products` | 🔒 Seller | Daftar produk komoditas milik kios sendiri |
| `POST` | `/api/products` | 🔒 Seller | Tambah komoditas baru (satuan: `kg`, `ikat`, `karung`, `peti`) |
| `PUT` | `/api/products/{slug}` | 🔒 Seller | Edit harga, stok, atau deskripsi komoditas |
| `DELETE`| `/api/products/{slug}` | 🔒 Seller | Hapus komoditas (Soft Delete) |
| `GET` | `/api/seller/orders` | 🔒 Seller | Daftar pesanan masuk yang memuat barang dari kios ini |
| `GET` | `/api/seller/incoming-items` | 🔒 Seller | **Antrean komoditas yang harus dikemas** di kios sebelum kurir tiba |
| `PATCH`| `/api/seller/order-items/{id}/status`| 🔒 Seller | Tandai kesiapan barang lapak atau laporkan stok darurat kosong |

### 5. Modul Courier — Kurir Lapangan
| Method | Endpoint | Hak Akses | Deskripsi |
|:---:|---|:---:|---|
| `GET` | `/api/courier/deliveries` | 🔒 Courier/Admin | Antrean pengantaran (`scope=available` untuk ambil tugas baru) |
| `GET` | `/api/courier/deliveries/{order_number}`| 🔒 Courier/Admin | **Rute tugas**: daftar blok kios pasar induk & alamat warung buyer |
| `POST` | `/api/courier/deliveries/{order_number}/claim` | 🔒 Courier/Admin | Kurir mengklaim pesanan siap jemput (status $\rightarrow$ `picking_up`) |
| `PATCH`| `/api/courier/order-items/{id}/pickup` | 🔒 Courier/Admin | Validasi fisik di lapak pasar induk (`picked_up` / `out_of_stock`) |
| `PATCH`| `/api/courier/deliveries/{order_number}/status` | 🔒 Courier/Admin | Update rute pengantaran: `in_transit` $\rightarrow$ `completed` |

### 6. Modul Admin — Pengawasan & Operasional Pasar
| Method | Endpoint | Hak Akses | Deskripsi |
|:---:|---|:---:|---|
| `GET` | `/api/admin/payments` | 🔒 Admin | Antrean verifikasi bukti transfer pembayaran |
| `POST` | `/api/admin/payments/{id}/verify` | 🔒 Admin | Verifikasi transfer bank: `approve` (status jadi `confirmed`) atau `reject` |
| `GET` | `/api/admin/orders` | 🔒 Admin | Audit seluruh transaksi pasar (filter tanggal, kurir, kios, search) |
| `GET` | `/api/admin/orders/{order_number}` | 🔒 Admin | Detail lengkap transaksi pasar |
| `POST` | `/api/admin/orders/{order_number}/assign-courier` | 🔒 Admin | Penugasan kurir secara manual (*dispatching*) |
| `POST` | `/api/admin/orders/{order_number}/cancel` | 🔒 Admin | Pembatalan darurat pasar & pemulihan stok otomatis |
| `GET` | `/api/admin/users` | 🔒 Admin | Manajemen akun pengguna pasar (filter: role, status aktif) |
| `GET` | `/api/admin/users/{user}` | 🔒 Admin | Detail data user beserta profil kios/warung |
| `PATCH`| `/api/admin/users/{user}/status` | 🔒 Admin | Aktivasi / Deaktivasi akun (auto-revoke Sanctum tokens) |

---

## 🔄 Lifecycle & State Machine Pesanan

```text
[Checkout Buyer]
       │
       ▼
  (pending)  ───> [Pembayaran: Transfer] ───> [Admin Approve]
       │                                             │
       │ (Pembayaran: COD)                           │
       ▼                                             ▼
  (confirmed) <──────────────────────────────────────┘
       │
       ▼ [Kurir Claim / Admin Assign]
 (picking_up) ──> [Kurir validasi fisik tiap kios: picked_up / out_of_stock]
       │
       ▼ [Kurir berangkat dari Pasar Induk]
  (in_transit)
       │
       ▼ [Kurir tiba di warung & serah terima]
  (completed) ──> [Jika COD: otomatis payment_status = paid]
```

---

## 🧪 Pengujian & Jaminan Mutu

Seluruh endpoint dan logika bisnis telah diverifikasi menggunakan pengujian terisolasi **PHPUnit 12**:

```bash
# Menjalankan seluruh test suite
php artisan test --compact
```

### Hasil Pengujian Saat Ini:
```text
Tests:    110 passed (469 assertions)
Duration: 2.17s
Status:   100% OK
```

### Pengecekan Gaya Kode (Linter & Formatter):
```bash
# Memformat kode sesuai standar Laravel Pint
vendor/bin/pint --format agent
```

---

## 📄 Lisensi
Hak Cipta © 2026 Makar B2B Platform. Dilisensikan di bawah [MIT License](LICENSE).
