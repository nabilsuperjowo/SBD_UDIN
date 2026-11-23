# SSR Kasir Galaxy — 7 tabel

Aplikasi Laravel 13 + Blade untuk pencatatan reservasi futsal/badminton oleh kasir, mengikuti contoh form gelap dan halaman cetak putih. Satu database PostgreSQL, tepat tujuh tabel. Pelanggan tidak membutuhkan akun.

## Menjalankan proyek pada laptop ini

```powershell
Set-Location "C:\Users\user\Documents\Codex\2026-11-17\new-chat\ssr-kasir-7"
php artisan ssr:database
php artisan ssr:check
php artisan serve --host=127.0.0.1 --port=8081
```

Buka http://127.0.0.1:8081. Jika port sudah dipakai oleh server yang dijalankan agen, cukup buka URL-nya setelah database berhasil dibuat.

Database baru bernama **ssr_kasir_7**. Perintah ssr:database membuat database bila belum ada, lalu menjalankan ssr:install --demo. Tidak ada DROP DATABASE, penghapusan tabel, atau penimpaan database SSR lama. Installer menolak database berisi struktur lain, termasuk versi SSR 19 tabel dan SQL tugas tujuh tabel sebelumnya.

Pada proyek lokal ini .env sudah dibuat dengan APP_KEY baru. Kredensial koneksi mengikuti koneksi PostgreSQL lokal sebelumnya. Jika muncul password authentication failed, periksa DB_PASSWORD di .env tanpa membagikannya ke publik.

## Memasang dari ZIP di komputer lain

1. Ekstrak ZIP lalu masuk ke folder proyek. Siapkan PHP 8.3+ dengan pdo_pgsql, mbstring, intl, openssl, fileinfo, dan Composer. Runtime pengembangan yang digunakan adalah PHP 8.5 dan Laravel 13.42.
2. Jalankan composer install.
3. Salin .env.example menjadi .env. Isi DB_PASSWORD PostgreSQL setempat. Pastikan nama database baru tidak bertabrakan dengan proyek lain.
4. Jalankan php artisan key:generate, php artisan ssr:database, lalu php artisan serve --port=8081.

CSS dan JavaScript disajikan dari public/assets secara lokal. NPM, CDN, dan build frontend tidak diperlukan. Tidak ada dependensi frontend yang perlu diunduh ketika aplikasi dibuka.

## Akun demo

| Peran | Email | Password awal |
|---|---|---|
| Kasir | admin@admin | admin123 |

Password seed dapat diatur melalui DEMO_PASSWORD sebelum instalasi awal. Perubahan variabel tersebut sesudah akun terbentuk tidak mengganti password akun lama. Akun demo untuk pengembangan. Jangan publikasikan proyek menggunakan kredensial demo.

## Fungsi yang tersedia

- Login/logout kasir, pembatasan percobaan login, dan perlindungan CSRF.
- Form nama/tim, WhatsApp, email opsional, lapangan, tanggal, jam mulai, durasi, metode, status, dan catatan.
- Pratinjau slot terisi, estimasi biaya, serta validasi ulang pada server.
- Simpan, edit, arsip/hapus dari daftar, filter nama/WhatsApp/kode/tanggal/status, dan pagination.
- Tarif Futsal 1/2 Rp150.000 dan Badminton 1/2 Rp60.000 per jam.
- Slot 07.00-24.00 WIB dengan durasi kelipatan satu jam. Reservasi 23.00-24.00 valid.
- Halaman cetak putih, semua hasil filter tanpa pagination, dan cetak/PDF melalui browser.
- Navbar sticky, layout responsif, dan pilihan mode terang untuk presentasi.

Status Terkonfirmasi atau Selesai merupakan konfirmasi kasir bahwa pembayaran telah diterima penuh. Browser meminta konfirmasi sebelum menyimpan. Selesai hanya diterima setelah jam bermain berakhir. Transfer/QRIS adalah pencatatan manual kasir, bukan integrasi gateway.

Menunggu Pembayaran menguasai semua slot yang dipesan sampai diubah atau diarsipkan oleh kasir. Versi kasir ini tidak memakai batas bayar 15 menit atau scheduler kedaluwarsa dari SSR pelanggan lama. Reservasi dibatalkan tidak menguasai slot.

Tombol Hapus mengisi dihapus_pada, mengubah reservasi menjadi DIBATALKAN, dan melepas seluruh slot. Catatan transaksi tidak dihapus permanen. Pembayaran yang telah berhasil tetap tersimpan. Penghapusan atau pembatalan bukan refund otomatis. Pengembalian dana ditangani di luar versi ini. Total Nilai Reservasi Aktif menghitung nilai reservasi yang masih terlihat dan tidak dibatalkan, termasuk yang selesai, bukan pendapatan kas bersih.

Saat edit lapangan yang sama, tarif_snapshot lama tetap dipakai. Mengganti lapangan memakai tarif lapangan pengganti. Tagihan dan metode pembayaran yang sudah lunas tidak boleh diubah. Status lunas tidak dapat dikembalikan menjadi menunggu. Reservasi yang dibatalkan tidak dapat diaktifkan ulang. Buat reservasi baru jika diperlukan.

Versi ini berfokus pada dua halaman contoh. Tidak mencakup portal pelanggan, loyalty/voucher, ulasan, Midtrans, manajemen refund, laporan keuangan lengkap, atau CRUD master melalui web. Tarif/master dapat dikelola melalui pgAdmin. Aplikasi hanya mengizinkan akun kasir aktif untuk login.

## Tujuh tabel dan lokasi kode

| Tabel | Fungsi |
|---|---|
| pengguna | Akun kasir |
| pelanggan | Profil pelanggan berdasarkan WhatsApp unik |
| kategori_lapangan | Futsal dan badminton |
| lapangan | Nama, jenis permukaan, kategori, tarif |
| reservasi | Pemesanan, snapshot identitas/harga, status dan pengarsipan |
| pembayaran | Satu catatan tagihan/pelunasan manual per reservasi |
| jadwal_lapangan | Slot per jam, slot ditutup, dan reservasi pemilik slot |

reservasi.id_lapangan menunjuk lapangan yang dipesan. jadwal_lapangan.id_reservasi menghubungkan semua jam yang dikuasai reservasi tanpa tabel penghubung kedelapan. Satu reservasi dua jam akan menguasai dua baris jadwal. Jadwal yang belum dibuat ditampilkan sebagai kosong dan dibuat atomik saat booking.

Session/cache memakai file, queue sync, tanpa tabel sessions/cache/jobs/migrations. Instalasi memakai database/sql/schema.sql melalui Installer, bukan migrasi standar. Perintah migrate diblokir untuk menjaga jumlah tujuh tabel.

| Lokasi | Tanggung jawab |
|---|---|
| routes/web.php | URL halaman dan aksi |
| app/Http/Controllers/ReservationController.php | Validasi input dan respons web |
| app/Services/ReservationService.php | Transaksi, locking, slot, tarif dan pembayaran |
| app/Services/Installer.php | Instalasi schema dan seed idempoten |
| resources/views/reservasi/index.blade.php | Form dan daftar sesuai contoh pertama |
| resources/views/reservasi/print.blade.php | Daftar cetak sesuai contoh kedua |
| resources/views/auth/login.blade.php | Halaman login petugas |
| public/assets/app.css dan app.js | Tema, responsif, estimasi, dan interaksi |
| database/sql/schema.sql | Physical design PostgreSQL lengkap |
| database/sql/pemeriksaan.sql | Query pemeriksaan dan contoh JOIN |
| docs/ERD.mmd dan ERD.svg | Diagram relasi, SVG berlatar putih |

## Pengujian

```powershell
php scripts/verify.php
php scripts/verify.php --pgsql
```

Perintah pertama menggunakan SQLite in-memory, bukan PostgreSQL pengguna. Saat pembangunan, 70 pemeriksaan lulus termasuk jalur HTTP form/edit/cetak, login, idempotensi, bentrok durasi, snapshot tarif, soft delete, dan batas 24.00. Semua data uji SQLite hilang setelah proses selesai.

Perintah --pgsql memakai koneksi .env dan schema sementara ssr_test_<acak>, lalu menghapus hanya schema tersebut pada akhir pengujian. Schema public tidak dipakai untuk data tes. Membutuhkan izin CREATE SCHEMA. Pengujian PostgreSQL belum berhasil dijalankan dari sesi agen karena port 5432 ditolak dengan Permission denied 10013. SQLite tidak membuktikan perilaku row locking PostgreSQL atau concurrency lintas proses. DB::transaction(retry 5) dan lockForUpdate diterapkan pada kode produksi.

Backend telah diuji melalui HTTP kernel Laravel. Perbandingan visual pada browser langsung dan pencetakan fisik belum dilakukan dari sesi agen.

## Instalasi melalui pgAdmin sebagai alternatif

Jalankan database/sql/00_buat_database.sql dari database postgres. Pilih database ssr_kasir_7, lalu jalankan schema.sql sekali pada database kosong. Sesudahnya jalankan php artisan ssr:install --demo untuk mengisi akun/kategori/lapangan. Refresh Databases > ssr_kasir_7 > Schemas > public > Tables. Harus terlihat tepat tujuh tabel.

Slot 30 hari dapat dibuat melalui php artisan ssr:slots --hari=30. Perintah ini idempoten dan opsional karena form membuat slot yang dibutuhkan secara otomatis. Tidak membutuhkan scheduler untuk versi kasir ini.

## FD dan normalisasi

Setiap PK menentukan seluruh atribut barisnya. Candidate key lain adalah email pengguna, WhatsApp pelanggan, nama kategori/lapangan, kode dan request_key reservasi, id_reservasi pada pembayaran, serta (id_lapangan,tanggal,jam_mulai) pada jadwal. Semua nilai atomik dan tidak ada daftar slot/pembayaran di dalam JSON.

Snapshot identitas dan tarif bermakna nilai pada dokumen saat pemesanan, sehingga dapat berbeda dari profil/tarif terkini. Nama lapangan/kategori dibaca melalui JOIN, bukan diduplikasi ke reservasi. Total tagihan dan jam selesai dihitung, tidak disimpan ulang. jumlah_bayar merupakan nominal transaksi pembayaran pada saat dicatat, bukan harga master.

Nama pemesan terbaru dapat memperbarui profil pelanggan dengan WhatsApp sama, tetapi snapshot reservasi lama tetap. Pembayaran memiliki satu baris per reservasi sehingga UI dapat JOIN tanpa menggandakan jumlah reservasi atau total nilai.

## Penghapusan akun pemilik dari versi sebelumnya

Jalankan php artisan ssr:hapus-pemilik, atau jalankan database/sql/hapus_akun_pemilik.sql melalui Query Tool database ssr_kasir_7. Akun pemilik tanpa transaksi dihapus. Jika pernah dipakai mencatat transaksi, baris identitasnya dipertahankan sebagai kasir nonaktif demi menjaga foreign key dan riwayat petugas. Akun tersebut tidak dapat login. Seed berikutnya hanya membuat akun kasir.

## Pembaruan otomatis antarperangkat

Daftar reservasi, jumlah, total nilai aktif, dan ketersediaan slot diperiksa otomatis setiap 3 detik melalui GET /reservasi/live. Ini polling dengan jeda beberapa detik, bukan push WebSocket. Tidak memerlukan server tambahan, NPM, scheduler, migrasi, atau tabel baru.

Pembaruan hanya mengganti bagian tabel dan angka ringkasan. Isian form serta filter yang belum dikirim tetap dipertahankan. Filter/pagination yang sudah diterapkan tetap diikuti. Posisi gulir horizontal tabel dipertahankan. Jika fokus berada di dalam tabel, penggantian tabel menunggu agar tombol yang sedang dipakai tidak hilang.

Tab yang tersembunyi menghentikan polling dan menyambung lagi saat dibuka. Kegagalan koneksi memakai retry dengan jeda bertambah. Status sinkronisasi terlihat pada judul daftar. Sesi login kedaluwarsa tidak mengalihkan halaman secara otomatis agar form tidak hilang.

Setiap halaman edit memiliki edit_revision. Server menolak penyimpanan jika reservasi berubah sejak form dibuka. Jika terdeteksi melalui polling, tombol Simpan dinonaktifkan dan peringatan ditampilkan. Isian tetap dapat disalin sebelum memuat ulang data. Ini mencegah perubahan kasir lain tertimpa secara diam-diam.

Untuk mencoba, tekan Ctrl+F5 sekali setelah pembaruan kode, lalu buka aplikasi pada dua tab. Simpan/edit/hapus data di tab pertama. Tab kedua akan memperbarui daftar, nilai, dan slot dalam beberapa detik saat tetap terlihat. Semua perangkat harus terhubung ke instance aplikasi/database yang sama. Halaman cetak merupakan snapshot saat halaman dibuka.

Tambahan pengujian backend mencakup autentikasi endpoint, perubahan dan penghapusan antar-request, total nilai, slot terisi/kosong, filter, pagination, tidak mengirim ulang HTML yang sama, escaping HTML, serta konflik versi edit. Pengujian ini menggunakan HTTP kernel Laravel dan SQLite in-memory, bukan pembuktian visual dua browser atau concurrency PostgreSQL.

## Batas tanggal dan jam pemesanan

Reservasi baru wajib mempunyai jam mulai setelah waktu server saat transaksi diproses, memakai Asia/Jakarta. Hari sebelumnya, jam yang sudah lewat, dan jam yang sedang berjalan tidak dapat dipesan. Misalnya pada 21.15 WIB, jam 21.00 dan sebelumnya ditolak; jam berikutnya adalah 22.00.

Batas diterapkan di service dalam transaksi, diperiksa ulang setelah penguncian data, dan berlaku juga untuk POST langsung serta pemindahan jadwal. Pengiriman ulang request_key yang sudah berhasil tetap idempoten dan tidak membuat pemesanan baru.

Form memasang min pada tanggal, menandai opsi jam lewat, dan menampilkan jam server. Logika jam browser memakai waktu dari server yang disinkronkan lewat respons live, lalu dihitung maju dengan waktu monotonic. Tidak mengandalkan tanggal/jam lokal perangkat. Batas jam dan pergantian hari diperbarui tanpa reload. Opsi Selesai hanya dapat digunakan sesudah jadwal berakhir.

Reservasi lama masih dapat diselesaikan atau diperbarui identitas/status/catatannya tanpa mengubah jadwalnya. Pengecualian ini hanya berlaku jika lapangan, tanggal, jam mulai, dan durasi persis sama dengan transaksi tersimpan. Jika jadwal diubah, waktu tujuan wajib di masa depan. Data historis yang sebelumnya tersimpan tidak dihapus.

Uji logika frontend dengan node scripts/check-clock.cjs. Terdapat 16 pemeriksaan jam WIB, pergantian tengah malam, sesi 23.00-24.00, jam perangkat salah, dan pengenalan jadwal lama. Suite PHP mempunyai 80 pemeriksaan termasuk penolakan waktu lampau pada service/HTTP, akun admin, ekspor, dan serialisasi konfigurasi Supabase. Jumlah tabel tetap tujuh, tanpa migrasi tambahan.


## Supabase

Proyek cloud terverifikasi: kbnbnvunyuwtilidinum. Struktur 7 tabel terpasang dan dilindungi RLS; data ekspor lokal telah diimpor. Host Session pooler: aws-1-ap-southeast-2.pooler.supabase.com. Perintah php artisan ssr:supabase memeriksa tujuan/data sumber, mencadangkan .env, dan mempertahankan APP_KEY. Pada 23 November 2026 pengguna berhasil menjalankannya; koneksi aktif sekarang Supabase dan penyimpanan reservasi baru sudah dicoba melalui aplikasi. Panduan/status lengkap di docs/SUPABASE.md.


## Akun sederhana

Akun yang diminta pengguna adalah admin@admin dengan password admin123, peran KASIR. Untuk database lokal yang sudah terpasang, jalankan php artisan ssr:akun-admin. Akun kasir lama diperbarui menggunakan ID yang sama agar riwayat reservasi/pembayaran tetap terhubung. Perintah memakai DEMO_PASSWORD di .env. Seed ulang tidak mengubah password akun admin yang sudah ada kecuali perintah reset tersebut dijalankan.

## Hosting Render Free

Dockerfile dan render.yaml menyiapkan satu Web Service Free, tetap memakai Supabase tanpa menambah tabel. Panduan lengkap: docs/RENDER.md. Isi APP_KEY lama dan DB_PASSWORD Supabase melalui Environment Render. Jangan memasukkan .env/ekspor/cadangan privat ke repository. Startup tidak menjalankan migrasi atau seed. URL HTTPS mengikuti RENDER_EXTERNAL_URL. Session file dapat hilang saat restart/tidur sehingga perlu login ulang; data reservasi tetap berada di Supabase.

Uji HTTP produksi di balik proxy melalui php scripts/verify-render.php. Deployment publik belum dibuat sampai akun Render dan sumber kode terhubung serta build berhasil.
