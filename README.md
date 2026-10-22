# HabitFlow - Sistem Informasi Pemantauan dan Evaluasi Kebiasaan Harian Berbasis Website

> **Tagline:** *"Build Better Habits, One Day at a Time."*

Aplikasi web produktivitas modern yang dirancang untuk membantu mahasiswa dan pengguna umum dalam membangun kebiasaan positif, mencatat rutinitas harian, memantau konsistensi secara terstruktur, dan mengevaluasi perkembangan kebiasaan melalui visualisasi data statistik serta grafik interaktif.

---

## 1. Teknologi yang Digunakan

* **Frontend:**
  * HTML5 (Semantik dan SEO-friendly).
  * CSS3 Kustom (Sistem desain palet warna *Sage Green* `#4A7C59` & *Forest Green* `#1E3A2B`, tipografi *Plus Jakarta Sans*, tata letak kartu bersih, elevasi lembut, dan responsif).
  * JavaScript Vanilla (Interaksi asinkron AJAX untuk checklist tanpa reload halaman, manipulasi DOM dinamis, *password visibility toggle*).
  * Bootstrap 5.3 & Bootstrap Icons 1.11 (Grid responsif, offcanvas mobile, modal konfirmasi, dropdown).
  * Chart.js 4.4 (Visualisasi tren konsistensi 7 hari, distribusi kategori berbentuk doughnut, dan analisis performa kebiasaan).
* **Backend:**
  * PHP 8.x Native dengan arsitektur modular yang rapi dan terstruktur.
  * PDO (PHP Data Objects) dengan *Prepared Statements* penuh untuk mencegah SQL Injection.
  * Autentikasi sesi PHP aman (`HttpOnly`, `session_regenerate_id()`, dan proteksi CSRF berbasis token).
* **Database:**
  * MySQL / MariaDB dengan skema relasional, foreign keys, cascade deletes, dan unique constraints.

---

## 2. Struktur Direktori Proyek

```
HabitFlowPPL/
├── api/
│   └── toggle_habit.php       # Endpoint AJAX penyelesaian checklist harian
├── assets/
│   ├── css/
│   │   └── style.css          # Desain sistem HabitFlow (Sage Green Theme, micro-animations)
│   └── js/
│       └── app.js             # Skrip interaksi checklist AJAX, Toast notification, password toggle
├── auth/
│   ├── login.php              # Form login dan autentikasi kredensial
│   ├── register.php           # Form registrasi akun baru dengan validasi server-side
│   └── logout.php             # Terminasi sesi dan logout
├── config/
│   ├── app.php                # Inisialisasi aplikasi, timezone Jakarta, session, konstanta global
│   ├── database.php           # Koneksi PDO MySQL & penanganan error koneksi
│   └── database.example.php   # Template konfigurasi database tanpa kredensial rahasia
├── database/
│   └── habitflow.sql          # File SQL DDL skema database, tabel, relasi & seed data awal
├── habits/
│   ├── index.php              # Halaman manajemen kebiasaan (pencarian, filter kategori & status)
│   ├── create.php             # Form pembuatan kebiasaan baru (pemilihan ikon, warna, jadwal)
│   ├── edit.php               # Form pembaruan kebiasaan dan jadwal hari aktif
│   ├── detail.php             # Detail kebiasaan, matriks konsistensi 30 hari & riwayat log
│   └── delete.php             # Handler penghapusan kebiasaan dengan proteksi CSRF
├── history/
│   └── index.php              # Riwayat aktivitas & kalender bulanan interaktif
├── includes/
│   ├── auth.php               # Middleware autentikasi (require_login, require_guest, current_user)
│   ├── csrf.php               # Helper token CSRF (generate, field, verify, protect)
│   ├── footer.php             # Layout penutup, CDN script & inisialisasi
│   ├── functions.php          # Algoritma streak, format tanggal Indonesia, kalkulasi metrik
│   ├── header.php             # Layout head, CSS, meta tags, CSRF meta
│   ├── sidebar.php            # Navigasi sidebar responsif (desktop & mobile)
│   └── topbar.php             # Header atas dengan salam personal, tanggal & profil dropdown
├── profile/
│   └── index.php              # Manajemen profil pengguna & ganti kata sandi dengan verifikasi lama
├── reports/
│   ├── index.php              # Halaman laporan evaluasi dengan 3 visualisasi grafik Chart.js
│   └── export_csv.php         # Generator ekspor data aktivitas ke format CSV (Excel UTF-8)
├── dashboard.php              # Dashboard utama statistik, progres harian, checklist, & grafik
├── index.php                  # Router utama (mengarahkan otomatis ke dashboard atau login)
├── test_suite.php             # Script pengujian otomatis fungsional sistem
└── README.md                  # Dokumentasi lengkap instalasi dan panduan penggunaan
```

---

## 3. Skema Basis Data (MySQL)

Database yang digunakan bernama `habitflow_db` dengan tabel-tabel berikut:

1. **`users`**: Menyimpan identitas pengguna (nama, email unik, password bcrypt, bio, waktu registrasi).
2. **`categories`**: Master kategori kebiasaan (Kesehatan, Pendidikan, Produktivitas, Spiritual, Keuangan, Pengembangan Diri, Lainnya) dilengkapi ikon dan kode warna.
3. **`habits`**: Menyimpan kebiasaan milik masing-masing pengguna (nama, deskripsi, relasi user, relasi kategori, warna penanda, tipe frekuensi `daily` atau `weekly_custom`, tanggal mulai, status aktif).
4. **`habit_schedules`**: Menyimpan hari pelaksanaan (0=Minggu, 1=Senin, ..., 6=Sabtu) bagi kebiasaan bertipe `weekly_custom`.
5. **`habit_logs`**: Mencatat log aktivitas harian dengan *unique key* `(habit_id, activity_date)` untuk mencegah duplikasi data penyelesaian pada hari yang sama.

---

## 4. Panduan Instalasi & Menjalankan Aplikasi

### Persyaratan Sistem:
* XAMPP (Apache dan MySQL) dengan PHP versi 8.0 atau lebih baru.
* Web Browser modern (Google Chrome, Microsoft Edge, Mozilla Firefox).

### Langkah-langkah:

1. **Letakkan Proyek di Folder `htdocs`:**
   Pastikan folder proyek berada di:
   ```
   C:\xampp\htdocs\HabitFlowPPL
   ```

2. **Jalankan XAMPP Control Panel:**
   * Klik tombol **Start** pada modul **Apache**.
   * Klik tombol **Start** pada modul **MySQL**.

3. **Impor Database:**
   * Buka browser dan akses **phpMyAdmin** di: `http://localhost/phpmyadmin/`
   * Buat database baru bernama: `habitflow_db` (atau langsung gunakan menu Import).
   * Klik tab **Import**, pilih file:
     ```
     C:\xampp\htdocs\HabitFlowPPL\database\habitflow.sql
     ```
   * Klik tombol **Import** di bagian bawah.
   *(Atau impor via Terminal/CMD: `mysql -u root < C:\xampp\htdocs\HabitFlowPPL\database\habitflow.sql`)*

4. **Konfigurasi Database (Jika Diperlukan):**
   File konfigurasi default berada di `config/database.php`. Secara bawaan, pengaturan XAMPP adalah:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'habitflow_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
   Ubah jika password MySQL root Anda berbeda.

5. **Akses Aplikasi:**
   Buka peramban (browser) dan ketik alamat URL:
   ```
   http://localhost/HabitFlowPPL/
   ```

---

## 5. Kredensial Akun Bawaan (Demo)

Aplikasi telah dilengkapi data seed akun awal untuk keperluan pengujian dan demonstrasi:

* **Email:** `syafira@habitflow.local`
* **Kata Sandi:** `password123`

> Anda juga dapat langsung membuat akun baru melalui halaman **Registrasi** (`/auth/register.php`). Setiap akun baru secara otomatis akan dibekali 2 kebiasaan awal untuk memudahkan pengenalan sistem.

---

## 6. Daftar Fitur yang Telah Diimplementasikan

| Modul | Fitur | Keterangan |
|---|---|---|
| **Autentikasi** | Registrasi Akun | Validasi format email, keunikan email, minimal password, konfirmasi cocok. |
| | Login & Remember Session | Autentikasi aman via `password_verify` dan regenerasi Session ID. |
| | Password Visibility Toggle | Tombol ikon mata untuk menampilkan/menyembunyikan kata sandi. |
| | Logout | Pembersihan sesi pengguna secara menyeluruh. |
| **Dashboard** | Sapaan Personal Dinamis | "Selamat pagi/siang/sore/malam, [Nama]!" disesuaikan waktu lokal. |
| | 4 Kartu Statistik Nyata | Total Kebiasaan, Selesai Hari Ini, Belum Selesai, Streak Terbaik. |
| | Progress Bar Harian | Persentase penyelesaian kebiasaan hari ini terhitung otomatis. |
| | Pesan Motivasi Dinamis | Kutipan reflektif berganti sesuai tingkat pencapaian (0%, <50%, <100%, 100%). |
| | Checklist Harian AJAX | Tandai selesai/batal selesai langsung tanpa reload halaman + Toast feedback. |
| | Tren 7 Hari Terakhir (Chart.js)| Grafik garis interaktif menghitung persentase tuntas harian. |
| | Pintasan Riwayat 7 Hari | Widget kartu mini menampilkan aktivitas selesai dalam seminggu. |
| **Kebiasaan Saya** | CRUD Lengkap | Tambah, Lihat, Edit, dan Hapus Kebiasaan dengan modal konfirmasi. |
| | Penjadwalan Fleksibel | Pilihan Setiap Hari (*Daily*) atau Hari Tertentu (*Weekly Custom*). |
| | Pemilihan Ikon & Warna | Personalisasi kebiasaan dengan palet warna dan ikon tematik. |
| | Pencarian & Filter | Filter berdasarkan kategori, status aktif/nonaktif, dan kata kunci nama. |
| | Detail Kebiasaan | Matriks konsistensi 30 hari interaktif dan tabel log riwayat. |
| **Sistem Streak** | Algoritma Presisi | Streak berjalan berdasarkan jadwal kebiasaan; hari yang tidak dijadwalkan tidak memutus streak. Menghitung *Current Streak* dan *Longest Streak*. |
| **Riwayat & Kalender** | Kalender Bulanan | Grid kalender dengan penanda kebiasaan tuntas dan navigasi bulan/tahun. |
| | Detail Tanggal Terpilih | Menampilkan daftar aktivitas dan checklist pada tanggal tertentu di masa lalu. |
| | Filter Tanggal | Filter riwayat berdasarkan kategori dan kebiasaan spesifik. |
| **Laporan & Evaluasi** | Filter Periode | Pilihan periode: Harian, 7 Hari Terakhir, atau 30 Hari Terakhir. |
| | Analisis Konsistensi | Menampilkan kebiasaan paling konsisten dan kebiasaan yang butuh perhatian. |
| | 3 Grafik Chart.js | 1) Tren Sukses Periode, 2) Distribusi Kategori (Doughnut), 3) Komparasi Kebiasaan. |
| | Ekspor Laporan CSV | Download laporan aktivitas ke file CSV dengan format UTF-8 BOM untuk Excel. |
| **Profil & Keamanan** | Edit Profil | Mengubah nama lengkap dan catatan bio. |
| | Proteksi Email | Email ditampilkan readonly demi keamanan integritas akun. |
| | Ganti Password | Mengubah kata sandi dengan validasi kata sandi lama terlebih dahulu. |
| | Proteksi CSRF & XSS | Token CSRF pada semua request POST dan sanitasi output HTML (`htmlspecialchars`). |

---

## 7. Skenario & Hasil Pengujian

Pengujian dilakukan melalui script pengujian fungsional terpadu (`test_suite.php`) dan pengujian HTTP secara langsung:

```
========================================
   HABITFLOW - AUTOMATED TEST SUITE    
========================================
[ PASS ] Koneksi Database & Master Kategori
[ PASS ] User Demo Syafira Terdaftar
[ PASS ] Verifikasi Hash Kata Sandi (password_verify)
[ PASS ] Kebiasaan Pengguna Tersedia
[ PASS ] Kalkulasi Streak Kebiasaan (Algoritma Jadwal)
[ PASS ] Kalkulasi Statistik Dashboard Hari Ini
[ PASS ] Toggle Status Checklist Aktivitas (Check)
[ PASS ] Toggle Pembatalan Checklist Aktivitas (Uncheck)
[ PASS ] Query Riwayat & Kalender Bulanan
[ PASS ] Agregasi Laporan 7 Hari Terakhir
[ PASS ] Pembaruan Profil Pengguna
[ PASS ] CSRF Token Generation
[ PASS ] CSRF Token Verification
========================================
HASIL PENGUJIAN: 13 LULUS, 0 GAGAL
========================================
```

* **Pengujian HTTP & Session:**
  * Login POST dengan CSRF Token -> `HTTP 302 Found` diarahkan ke `/dashboard.php`.
  * Akses Dashboard dengan sesi -> `HTTP 200 OK`, seluruh data Syafira Putri ditampilkan.
  * Ekspor CSV -> `HTTP 200 OK`, header `Content-Type: text/csv` terunduh dengan data berintegritas tinggi.
  * Isolasi Data Antar Pengguna -> Pengguna hanya dapat mengakses dan mengelola kebiasaan miliknya sendiri (`WHERE user_id = ?`).

---

## 8. Catatan Pengembangan & Pemeliharaan

Seluruh kebutuhan dan spesifikasi fungsional telah **100% selesai diimplementasikan**. Tidak ada fitur yang tertunda atau menggunakan data dummy statis. Semua data statistik, progress bar, matriks, kalender, dan grafik ditarik secara dinamis dari database MySQL.
