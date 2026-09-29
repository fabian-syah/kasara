# 🧾 Kasara

Sistem kasir dan manajemen penjualan multi-cabang untuk toko ritel/gadget — mencakup transaksi penjualan, inventaris, transfer antar cabang, tukar tambah, hingga pelaporan.

Arsitektur terpisah: **backend API** (Laravel) dan **frontend SPA** (Vue 3), dijalankan bersama lewat Docker Compose.

---

## ✨ Fitur

**Penjualan & kasir**
- **Transaksi penjualan** — alur kasir lengkap dengan keranjang dan perhitungan total.
- **Diskon & pelunasan** — penanganan uang muka (DP), pelunasan, dan riwayat pembayaran.
- **Cetak nota** — nota/invoice dapat dihasilkan dan dibagikan, termasuk halaman nota publik untuk pelanggan.
- **Pemindaian QR/barcode** — pemindaian produk langsung dari kamera perangkat.
- **OCR struk** — pembacaan teks struk memakai Tesseract.js.
- **Pembatalan & refund** — alur pembatalan transaksi dan pengembalian dana DP.

**Inventaris & stok**
- **Manajemen produk** — produk, tipe, brand, kategori, distributor, dan harga bertingkat.
- **Pengelolaan gudang** — stok per gudang dan per cabang.
- **Transfer stok** — pemindahan barang antar cabang, termasuk penanganan transfer gagal.
- **Barang keluar (stock out)** — pencatatan pengeluaran barang dengan alasan.
- **Tukar tambah & tukar unit** — alur trade-in dan unit exchange.
- **Penyeimbangan stok (balancing)** — pencocokan stok fisik dengan catatan sistem.

**Multi-cabang & operasional**
- **Manajemen cabang** — data cabang, pengguna, dan hak aksesnya.
- **Papan peringkat cabang** — liga/leaderboard performa antar cabang.
- **Toko online** — modul penjualan kanal daring.
- **Mode online/offline** — antarmuka menyesuaikan kondisi konektivitas.

**Laporan & kontrol**
- **Laporan penjualan** — rekap dan grafik performa (Chart.js).
- **Audit** — pemeriksaan transaksi dan aktivitas sistem.
- **Riwayat perubahan** — pelacakan perubahan data penting.
- **Pemeriksaan keamanan** — modul pengecekan integritas sistem.

**Integrasi**
- **Realtime** — pembaruan data langsung via Laravel Reverb + Pusher (websocket).
- **WhatsApp share** — kirim nota/ringkasan transaksi lewat WhatsApp.
- **Google Drive** — proxy integrasi penyimpanan.
- **Ekspor Excel** — laporan dapat diunduh sebagai spreadsheet.
- **Dokumentasi API** — OpenAPI dihasilkan otomatis dengan Scramble.

## 🧰 Teknologi

**Backend**

| Lapisan | Teknologi |
|---|---|
| Framework | Laravel 12, PHP 8.2+ |
| Performa | Laravel Octane |
| Realtime | Laravel Reverb (websocket) |
| Auth | Laravel Sanctum |
| Peran & izin | `spatie/laravel-permission` |
| Ekspor | Maatwebsite Excel |
| Dokumentasi API | Scramble |

**Frontend**

| Lapisan | Teknologi |
|---|---|
| Framework | Vue 3, Vite |
| State | Pinia |
| Routing | Vue Router 4 |
| Styling | Tailwind CSS v4 |
| Grafik | Chart.js, vue-chartjs |
| QR & barcode | `html5-qrcode`, `qrcode` |
| OCR | Tesseract.js |
| Nota & gambar | jsPDF, html2canvas, html-to-image |
| Realtime | Laravel Echo, Pusher JS |

## 🚀 Cara Menjalankan Lokal

### Prasyarat

- PHP 8.2+, Composer
- Node.js 20+ dan npm
- MySQL/MariaDB
- Docker (opsional, untuk menjalankan seluruh stack sekaligus)

### Menjalankan dengan Docker

```bash
git clone https://github.com/fabian-syah/kasara.git
cd kasara
docker compose up --build
```

### Menjalankan manual

**1. Backend**

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Isi `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kasara
DB_USERNAME=root
DB_PASSWORD=

# Realtime (Laravel Reverb)
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=localhost
REVERB_PORT=8080

# Frontend
FRONTEND_URL=http://localhost:5173
```

```bash
php artisan migrate --seed
php artisan serve
php artisan reverb:start     # terminal terpisah, untuk websocket
```

**2. Frontend**

```bash
cd frontend
npm install
npm install
npm run dev
```

Buka [http://localhost:5173](http://localhost:5173).

## 📁 Struktur Proyek

| Lokasi | Isi |
|---|---|
| `backend/app/Http/Controllers/` | Controller API per domain (Sales, Inventory, Branch, Report, Transfer, TradeIn, dll.) |
| `backend/app/Services/` | Logika bisnis terpisah per domain (Inventory, StockOut, Transfer, Report, Notification, Audit, Cache) |
| `backend/app/Models/` | Model Eloquent |
| `backend/app/Exports/` | Kelas ekspor Excel |
| `backend/routes/api.php` | Endpoint API |
| `frontend/src/views/` | Halaman SPA (cashier, sales, inventory, branches, monitoring, master, settings, users) |
| `frontend/src/components/` | Komponen UI per domain |
| `frontend/src/composables/` | Composable Vue yang dapat digunakan ulang |
| `frontend/src/router/` | Konfigurasi rute SPA |
| `brain/` | Catatan perencanaan & desain sistem |
| `docker-compose.yml` | Menjalankan seluruh stack |

## 🔐 Keamanan

- `.env` backend memuat kredensial basis data dan kunci Reverb — **jangan pernah** di-commit.
- Modul keuangan (refund, pembatalan, penyesuaian stok) sebaiknya dibatasi ke peran tertentu.
- Aktifkan modul **Audit** dan riwayat perubahan pada data transaksi agar setiap penyesuaian dapat ditelusuri.
- Pemindaian barcode/QR dan OCR berjalan di sisi klien — pastikan endpoint terkait tetap memvalidasi data di server.
- Jangan menaruh kunci layanan (Google Drive, WhatsApp) di kode frontend.

## 🗺️ Rencana Pengembangan

- [ ] Uji otomatis untuk alur penjualan dan transfer stok
- [ ] Mode offline penuh dengan sinkronisasi tertunda
- [ ] Dasbor analitik penjualan lintas cabang
- [ ] Dukungan pembayaran QRIS

## 📄 Lisensi

MIT
