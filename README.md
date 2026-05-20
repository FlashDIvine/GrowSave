# GrowSave Backend API

GrowSave adalah platform backend berbasis **Laravel 12** yang dirancang untuk membantu pengelolaan transparansi uang kas, tagihan, dan pengumuman pada komunitas perumahan, indekos (kost), atau rukun tetangga (RT). Platform ini mengintegrasikan **Midtrans Payment Gateway** untuk memfasilitasi pembayaran tagihan secara otomatis dan aman menggunakan Snap Token, serta dilengkapi webhook handler untuk pembaruan status transaksi secara real-time.

---

## Fitur Utama

1. **Autentikasi & Profil (Laravel Sanctum)**
   - Registrasi akun dengan opsi Role (`admin` atau `user`).
   - Login dengan sistem *Single Device* (otomatis menghapus token lama saat login di perangkat baru).
   - Pembaruan informasi profil warga.

2. **Manajemen Room & Keanggotaan**
   - **Admin Room**: Setiap admin otomatis mengelola satu Room (komunitas) yang memiliki kode unik (`ROOM-XXXXXX`).
   - **Warga (User)**: Bergabung ke dalam Room menggunakan kode unik saat registrasi.
   - **Verifikasi Otoritas**: Sistem persetujuan keanggotaan warga oleh admin (`pending`, `approved`, `rejected`).

3. **Otorisasi Berlapis (Middleware Kustom)**
   - `admin`: Membatasi endpoint khusus untuk pengelola komunitas (CRUD pengumuman/tagihan, manual transaksi, persetujuan warga).
   - `approved`: Memastikan warga yang sudah disetujui (status `approved`) yang dapat mengakses transparansi kas, tagihan, dan riwayat pembayaran.

4. **Manajemen Pengumuman (Announcement)**
   - Pembuatan pengumuman baru disertai unggah gambar oleh Admin.
   - Distribusi pengumuman real-time ke semua warga di Room yang sama.

5. **Manajemen Tagihan & Pembayaran Otomatis (Midtrans Integration)**
   - Admin membuat tagihan aktif dengan nominal dan tenggat waktu tertentu.
   - Warga mendapatkan Snap Token pembayaran secara dinamis langsung dari Midtrans.
   - Webhook Handler terverifikasi dengan enkripsi SHA-512 *Signature Key* untuk memproses notifikasi transaksi otomatis (`settlement`, `pending`, `cancel`, `expire`).

6. **Transparansi Kas & Pencatatan Transaksi**
   - **Transparansi Kas RT**: Total kas yang terhitung otomatis dari akumulasi pembayaran tagihan sukses (`settlement`) via Midtrans.
   - **Pencatatan Transaksi Manual**: Admin dapat mencatat kas masuk (`in`) atau keluar (`out`) untuk pembukuan eksternal (misal: pembayaran kebersihan, perbaikan fasilitas).
   - **Riwayat Transaksi**: Riwayat kas mutakhir yang dapat diakses transparan oleh seluruh warga.

---

## Teknologi yang Digunakan

* **Framework Utama**: Laravel 12.0
* **Bahasa Pemrograman**: PHP >= 8.2
* **Autentikasi API**: Laravel Sanctum
* **Asset Manager**: Vite & Tailwind CSS v4.0.0
* **Database**: MySQL (untuk production) / SQLite (untuk pengujian lokal)
* **SDK Integrasi**: Midtrans PHP SDK (v2.6)

---

## Struktur Folder Penting

```
GrowSave/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/        # Controller utama API (Auth, Payment, Kas, dll)
│   │   └── Middleware/             # Middleware kustom (AdminMiddleware, ApprovedUserMiddleware)
│   └── Models/                     # Model representasi tabel database
├── config/
│   └── midtrans.php                # File konfigurasi server & client key Midtrans
├── database/
│   ├── migrations/                 # Migrasi database terstruktur
│   └── seeders/                    # Seeder untuk data awal pengujian
├── routes/
│   ├── api.php                     # Route API backend utama
│   └── web.php                     # Route web default
└── .env.example                    # Template konfigurasi environment
```

---

## Environment & Konfigurasi (.env)

Salin `.env.example` menjadi `.env` dan sesuaikan parameter berikut:

```env
APP_NAME="Grow Save"
APP_ENV=local
APP_URL=http://127.0.0.1:8000

# Pengaturan Database (Contoh menggunakan MySQL)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=growsave
DB_USERNAME=root
DB_PASSWORD=

# Pengaturan Midtrans Payment Gateway
MIDTRANS_SERVER_KEY=your-midtrans-server-key-here
MIDTRANS_CLIENT_KEY=your-midtrans-client-key-here
MIDTRANS_IS_PRODUCTION=false
```

---

## Cara Instalasi

Ikuti langkah-langkah berikut untuk menyiapkan proyek di lingkungan lokal Anda:

1. **Clone Repositori**
   ```bash
   git clone https://github.com/FlashDIvine/GrowSave.git
   cd GrowSave
   ```

2. **Instalasi Dependensi PHP**
   ```bash
   composer install
   ```

3. **Salin & Konfigurasi `.env`**
   ```bash
   copy .env.example .env
   # Silakan buka file .env dan sesuaikan dengan konfigurasi database serta Midtrans Anda
   ```

4. **Generate Application Key**
   ```bash
   php artisan key:generate
   ```

5. **Jalankan Migrasi & Database Seeder**
   Pastikan Anda telah membuat database kosong di MySQL dengan nama sesuai isi `DB_DATABASE` di `.env`, lalu jalankan:
   ```bash
   php artisan migrate --seed
   ```

6. **Instalasi & Build Asset Frontend**
   ```bash
   npm install
   npm run build
   ```

---

## Cara Menjalankan Project

1. **Jalankan Development Server Laravel**
   ```bash
   php artisan serve
   ```
   Aplikasi akan berjalan secara default di `http://127.0.0.1:8000`.

2. **Jalankan Queue Listener (Opsional)**
   Jika Anda memindahkan driver queue ke database untuk pemrosesan asinkron:
   ```bash
   php artisan queue:listen
   ```

---

## API Endpoints

### 1. Autentikasi (Public & Auth)

| Metode | Endpoint | Fungsi | Middleware |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/register` | Pendaftaran Akun Admin/User | Public |
| `POST` | `/api/login` | Login dan perolehan token Sanctum | Public |
| `POST` | `/api/logout` | Logout dan penghapusan token | `auth:sanctum` |
| `POST` | `/api/profile` | Pembaruan data profil warga | `auth:sanctum` |

### 2. Manajemen Room & Request (Khusus Admin)

| Metode | Endpoint | Fungsi | Middleware |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/room/requests` | List permintaan warga yang ingin masuk Room | `auth:sanctum`, `admin` |
| `POST` | `/api/room/approve/{id}` | Menyetujui permintaan gabung warga | `auth:sanctum`, `admin` |
| `POST` | `/api/room/reject/{id}` | Menolak permintaan gabung warga | `auth:sanctum`, `admin` |

### 3. Pengumuman (Announcements)

| Metode | Endpoint | Fungsi | Middleware |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/announcements` | Membuat pengumuman baru | `auth:sanctum`, `admin` |
| `PUT` | `/api/announcements/{id}` | Mengubah data pengumuman | `auth:sanctum`, `admin` |
| `DELETE` | `/api/announcements/{id}` | Menghapus pengumuman | `auth:sanctum`, `admin` |
| `GET` | `/api/announcements` | Melihat semua list pengumuman Room | `auth:sanctum`, `approved` |
| `GET` | `/api/announcements/{id}`| Detail spesifik pengumuman | `auth:sanctum`, `approved` |

### 4. Tagihan (Bills)

| Metode | Endpoint | Fungsi | Middleware |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/bills` | Membuat tagihan baru warga | `auth:sanctum`, `admin` |
| `PUT` | `/api/bills/{id}` | Mengubah nominal/tenggat tagihan | `auth:sanctum`, `admin` |
| `DELETE` | `/api/bills/{id}` | Menghapus data tagihan | `auth:sanctum`, `admin` |
| `GET` | `/api/bills` | List seluruh tagihan di Room | `auth:sanctum`, `approved` |
| `GET` | `/api/bills/{id}` | Detail info tagihan | `auth:sanctum`, `approved` |

### 5. Pembayaran & Webhook (Payments)

| Metode | Endpoint | Fungsi | Middleware |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/payments` | Generate Snap Token Midtrans untuk bayar | `auth:sanctum`, `approved` |
| `POST` | `/api/webhooks/midtrans`| Webhook callback dari Midtrans | Public (Signature Verified) |

### 6. Transparansi & Keuangan (Cash & Transactions)

| Metode | Endpoint | Fungsi | Middleware |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/dashboard` | Dashboard statistik performa kas | `auth:sanctum`, `admin` |
| `POST` | `/api/transactions` | Catat transaksi kas masuk/keluar manual | `auth:sanctum`, `admin` |
| `GET` | `/api/cash-balance` | Transparansi total saldo kas (Midtrans settled) | `auth:sanctum`, `approved` |
| `GET` | `/api/transactions` | Riwayat 10 transaksi kas (manual + digital) | `auth:sanctum`, `approved` |

---

## Catatan Pengembangan & Arsitektur Alur Kerja

1. **Alur Validasi Warga Baru**:
   ```
   Registrasi User (Wajib Kode Room) ──> Status Membership: PENDING (Akses Terbatas)
                                                               │
   Warga dapat Akses Fitur Kas <── Membership: APPROVED <── Persetujuan Admin
   ```

2. **Proses Pembayaran Digital**:
   - Warga memilih tagihan aktif melalui endpoint `/api/payments`.
   - Backend memproses transaksi ke API Midtrans Snap dan mengembalikan `snap_token`.
   - Aplikasi frontend (web/mobile) merender pop-up pembayaran Midtrans menggunakan Snap.js dengan token tersebut.
   - Setelah warga menyelesaikan pembayaran, Midtrans mengirim callback notifikasi HTTP ke endpoint Webhook `/api/webhooks/midtrans`.
   - Webhook memverifikasi kecocokan signature key (SHA-512) dan mengubah status database lokal menjadi `settlement`.

3. **Perbedaan Kas & Transaksi**:
   - **Kas (Cash Balance)**: Khusus menghitung nominal uang yang masuk secara digital melalui pembayaran tagihan via Midtrans yang berstatus `settlement`.
   - **Transaksi (Transactions)**: Digunakan untuk pembukuan manual oleh admin untuk mencatat pengeluaran rill di lapangan (misalnya membeli bohlam jalan, bayar satpam) maupun pemasukan fisik nontunai.

---

## Screenshots

*(Bagian ini dapat diisi dengan screenshot aplikasi web/mobile yang terhubung dengan API backend ini)*

![Dashboard Screenshot](https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg)

---

## Author

* **Developer** - [FlashDIvine](https://github.com/FlashDIvine)
* **Project Name** - GrowSave Backend API
* **Repository** - [https://github.com/FlashDIvine/GrowSave](https://github.com/FlashDIvine/GrowSave)
