# Integrasi Marketplace: GoFood, ShopeeFood, GrabFood

Fitur ini memungkinkan sistem POS untuk sinkron produk ke marketplace (GoFood, ShopeeFood, GrabFood) serta menerima order masuk dari platform tersebut. Setiap order ter-track di outlet dan pusat (Pusat).

## Tujuan

1. **Produk bisa dipush/sinkron ke GoFood, ShopeeFood, dan GrabFood**
2. **Orderan masuk bisa ketahui di masing-masing cabang dan Pusat**
3. **Bisa di-set per-outlet**: aktifkan/tidak aktifkan orderan dari masing-masing platform

## Konfigurasi Global

Ditambahkan di `config/services.php`:

```php
'marketplaces' => [
    'gofood' => [
        'client_id' => env('GOFOOD_CLIENT_ID'),
        'client_secret' => env('GOFOOD_CLIENT_SECRET'),
        'production' => env('GOFOOD_PRODUCTION', false),
    ],
    'shopee_food' => [
        'client_id' => env('SHOPEE_FOOD_CLIENT_ID'),
        'client_secret' => env('SHOPEE_FOOD_CLIENT_SECRET'),
        'production' => env('SHOPEE_FOOD_PRODUCTION', false),
    ],
    'grab_food' => [
        'client_id' => env('GRAB_FOOD_CLIENT_ID'),
        'client_secret' => env('GRAB_FOOD_CLIENT_SECRET'),
        'production' => env('GRAB_FOOD_PRODUCTION', false),
    ],
],
```

Variabel environment `.env`:
```
GOFOOD_CLIENT_ID=
GOFOOD_CLIENT_SECRET=
GOFOOD_PRODUCTION=false

SHOPEE_FOOD_CLIENT_ID=
SHOPEE_FOOD_CLIENT_SECRET=
SHOPEE_FOOD_PRODUCTION=false

GRAB_FOOD_CLIENT_ID=
GRAB_FOOD_CLIENT_SECRET=
GRAB_FOOD_PRODUCTION=false
```

## Setting Per-Outlet

Di halaman setting outlet, terdapat checklist:

- ✅ GoFood - Aktifkan sinkron produk dan terima order dari GoFood
- ✅ ShopeeFood - Aktifkan sinkron produk dan terima order dari ShopeeFood
- ✅ GrabFood - Aktifkan sinkron produk dan terima order dari GrabFood

Setiap checklist memiliki setting tambahan:

- `gofood_sync_products` - Otomatis push produk ke GoFood
- `shopee_food_sync_products` - Otomatis push produk ke ShopeeFood
- `grab_food_sync_products` - Otomatis push produk ke GrabFood

API key bisa di-set global (di `.env`) atau per-outlet (override di database).

## Arsitektur

```
1. Setup Global
   → Tambah config di services.php (client_id/secret per platform)
   → Tambah env variables di .env

2. Setup Per-Outlet
   → Di halaman setting outlet: checklist 3 platform
   → Masukkan API key (bisa override global atau pakai global)
   → Aktifkan fitur sync produk dan/atau terima order

3. Sinkron Produk (harian/terjadwal)
   → Artisan command: `php artisan marketplace:sync-products`
   → Baca setting outlet yang aktif
   → Ambil data produk + stok dari POS
   → Format per platform API
   → POST ke API GoFood/ShopeeFood/GrabFood
   → Catat log sukses/gagal

4. Terima Order (via Webhook)
   → Tambah route di routes/api.php: /gofood, /shopee-food, /grab-food
   → Webhook verifikasi signature per platform
   → Service parse payload, kurangi stok product_warehouse
   → Buat Transaction dengan order_type sesuai platform
   → Status order ter-track di POS + Laporan

5. Laporan
   → Filter order berdasarkan order_type (gofood/shopeefood/grabfood)
   → Filter per warehouse/outlet
   → Grafik revenue per platform
   → Export ke CSV jika perlu
```

## Alur Data

```
Produk di POS
    ↓
MarketplaceSyncService push ke API platform
    ↓
Katalog muncul di GoFood/ShopeeFood/GrabFood
    ↓
Pelanggan order via platform
    ↓
Webhook → MarketplaceOrderService → Transaction + kurangi stok
    ↓
Ter-track di outlet + Pusat
```

## Command Artisan

```bash
# Sinkron produk ke marketplace yang aktif
php artisan marketplace:sync-products
```

Command ini akan:
- Baca outlet yang aktif
- Untuk setiap outlet yang ada setting marketplace yang aktif
- Ambil produk + stok dari warehouse outlet
- Format data per platform (GoFood / ShopeeFood / GrabFood)
- Kirim ke API masing-masing platform
- Log hasil sukses/gagal ke dalam sistem

## Webhook Endpoints

Setiap platform memiliki endpoint webhook sendiri:

| Platform | Route | Deskripsi |
|----------|-------|-----------|
| GoFood | `POST /api/v1/webhooks/gofood` | Terima order dari GoFood |
| ShopeeFood | `POST /api/v1/webhooks/shopee-food` | Terima order dari ShopeeFood |
| GrabFood | `POST /api/v1/webhooks/grab-food` | Terima order dari GrabFood |

Setiap webhook akan:
- Verifikasi signature per platform
- Parse payload order
- Cari product di POS berdasarkan nama/sku
- Kurangi stok product_warehouse (dengan lockForUpdate)
- Buat Transaction baru dengan order_type sesuai platform
- Kembalikan respons ke platform

## Laporan & Report

Filter yang tersedia di raport sales:

- `order_type`: `in_store`, `takeaway`, `delivery`, `gofood`, `shopeefood`, `grabfood`
- `warehouse_id`: filter per outlet/cabang
- `created_at`: filter per tanggal

Contoh query filter:
```
GET /reports/sales?order_type=gofood&warehouse_id=1
```

Akan menampilkan semua order dari GoFood di outlet tertentu.

## Usecase

### 1. Admin mengaktifkan GoFood di outlet MAL
- Ceklis GoFood di setting outlet MAL
- Masukkan GOFOOD_CLIENT_ID dan GOFOOD_CLIENT_SECRET (atau pakai dari .env global)
- Aktifkan GoFood sync products jika mau produk otomatis ter-push

### 2. Setiap hari menjalankan sync produk
```bash
php artisan marketplace:sync-products
```
- Sistem akan baca setting outlet MAL
- Ambil semua produk yang ada di warehouse MAL
- Format sesuai API GoFood
- POST ke API GoFood
- Katalog produk akan muncul di aplikasi GoFood di awalnya

### 3. Pelanggan order via GoFood
- Pelanggan membuka aplikasi GoFood
- Melihat katalog produk (yang sudah disinkron)
- Memesan produk
- Order masuk ke webhook GoFood POS
- Sistem membuat transaksi internal, kurangi stok
- Order ter-track di dashboard POS di outlet MAL

### 4. Admin lihat laporan
- Filter order_type = gofood di halaman sales report
- Lihat total revenue dari order GoFood
- Filter per warehouse untuk melihat performance per outlet
- Export ke CSV untuk laporan ke atasan
```

## Teknis Implementasi

### Migrasi Database

- Perluas enum `order_type` di transactions: `in_store`, `takeaway`, `delivery`, `gofood`, `shopeefood`, `grabfood`
- Tambah field ke settings table: `*_enabled`, `*_sync_products`, `*_client_id` per platform

### Service Baru

- `app/Services/MarketplaceSyncService.php` - Fitur push produk ke platform
- `app/Services/MarketplaceOrderService.php` - Fitur terima order via webhook

### Controller Baru

- `app/Http/Controllers/Api/MarketplaceWebhookController.php` - Handler webhook per platform

### Model Setting (Ekstensi)

- `PaymentSetting` atau model terpisah `MarketplaceSetting` dengan field:
  - `gofood_enabled`, `shopee_food_enabled`, `grab_food_enabled`
  - `gofood_sync_products`, `shopee_food_sync_products`, `grab_food_sync_products`
  - `gofood_client_id`, `shopee_food_client_id`, `grab_food_client_id`

### Fitur yang Sudah ada yang Di-modifikasi

- `routes/api.php` - Tambah webhook routes
- `PosApiController.php` - Perluas validasi order_type enum
- `Transaction.php` - order_type sudah support baru (migrasi)
- `CheckoutService.php` - Handling order type di checkout
- `ThermalPrintService.php` - Label order type di struk

## Troubleshooting

1. **Produk tidak muncul di marketplace** - Cek setting outlet: apakah GoFood/ShopeeFood/GrabFood sudah diaktifkan? Cek API key sudah benar.
2. **Order dari platform gagal diterima** - Cek webhook signature verification. Pastikan secret key cocok dengan yang didapat dari platform.
3. **Stok tidak kurang** - Pastikan `DB::transaction` + `lockForUpdate()` jalan dengan benar. Cek error log di `storage/logs/`.
4. **Sinkron produk error** - Cek koneksi internet, API key expired, atau platform sedang maintenance. Log error akan tampil di command output.
5. **Double order/stok** - Cek idempotency di webhook handler. Gunakan invoice_number unik atau kombinasi order_type + client_uuid.
6. **Setting reset setelah seed** - Lakukan logout + login lagi untuk merefresh permission cache.

## Kontribusi

Fitur ini masih dalam pengembangan. Untuk menambahkan perubahan:
1. Buat branch dari `development`: `git checkout -b feature/marketplace-integration development`
2. Implementasi fitur sesuai desain di atas
3. Tambah test case untuk webhook handler dan sync products
4. Jalankan `php artisan test` sebelum PR
5. Buat PR ke `development`