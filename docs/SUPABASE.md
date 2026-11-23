# Pemindahan database SSR ke Supabase

Organisasi tujuan kacpiybubmrgneonynxr. Proyek aktif terverifikasi kbnbnvunyuwtilidinum, masih memakai nama bawaan akun. Pada 23 November 2026, tujuh tabel aplikasi berhasil dipasang melalui koneksi Supabase. RLS aktif pada ketujuh tabel, anon/authenticated tidak memiliki hak baca/tulis. Data lokal sudah berhasil diimpor. Pengguna telah berhasil menjalankan ssr:supabase, mengalihkan .env ke Supabase, login, dan menyimpan reservasi baru.

## Akses yang dibutuhkan

Hubungkan integrasi Supabase agar proyek dapat diperiksa dan SQL dapat dipasang. Pilih proyek milik pengguna yang memang dimaksud untuk SSR. Jangan membuat proyek berbayar, menimpa tabel lama, atau memakai proyek lain tanpa identifikasi tujuan yang jelas.

Supabase mengelola PostgreSQL. Website Laravel tetap membutuhkan hosting PHP tersendiri. Laravel memakai autentikasi session dan akses SQL server-side seperti pada versi lokal.

## Rencana pemindahan

1. Periksa proyek tujuan, versi database, tujuh nama tabel yang mungkin sudah ada, serta jumlah barisnya. Jika tabel tujuan berisi data, jangan mengosongkan atau menggabungkannya secara otomatis.
2. Ekspor tujuh tabel lokal berikut beserta data dan nilai sequence. Data pelanggan, akun, password hash, reservasi, dan pembayaran ikut dipindahkan. File ekspor harus disimpan privat, bukan dimasukkan ZIP/Git.
3. Pada public schema tujuan yang kosong, pasang database/sql/schema.sql dalam satu transaksi. Tidak perlu CREATE DATABASE karena database utama Supabase umumnya bernama postgres.
4. Jalankan database/sql/supabase_protect.sql untuk mengaktifkan RLS dan mencabut hak anon/authenticated pada tujuh tabel aplikasi. Tidak menambahkan policy akses publik. Laravel harus terhubung sebagai pemilik tabel atau role server yang telah diperiksa haknya; akun postgres Supabase dapat melewati RLS.
5. Impor data yang sudah diekspor, lalu periksa jumlah baris, FK, pembayaran, nilai sequence, dan jumlah tujuh tabel public. Jangan menjalankan seed demo sebelum mengimpor akun asli karena dapat menimbulkan konflik data.
6. Ambil konfigurasi Connect > Session pooler. Simpan password database langsung ke .env pada mesin aplikasi. Pertahankan APP_KEY. Gunakan DB_SSLMODE=require dan database tujuan sesuai Connect, biasanya postgres.
7. Uji koneksi, login, daftar, simpan/edit, bentrok jadwal, dan polling. Alihkan koneksi aktif hanya sesudah data tujuan diperiksa. Hentikan penulisan data lokal selama ekspor akhir dan perpindahan agar tidak ada transaksi tertinggal.

Jangan menjalankan php artisan ssr:database pada Supabase. Perintah tersebut khusus pembuatan database lokal dan sengaja menolak nama postgres. Sesudah impor, php artisan ssr:check digunakan untuk memeriksa tujuh tabel aplikasi. Supabase mempunyai schema/tabel internal platform sendiri; hitungan tujuh berlaku pada tabel aplikasi di schema public.

## Koneksi

.env.supabase.example memuat template tanpa rahasia. Host session pooler harus disalin dari dashboard, bukan ditebak dari region. Session pooler sesuai untuk Laravel persisten pada jaringan IPv4 serta mendukung prepared statements. Jika menggunakan transaction pooler, prepared statements dan pengaturan berbasis session perlu disesuaikan dahulu. Karena itu rancangan awal menggunakan session pooler.

## Keterbatasan akses sesi pengembangan

Sesi agen sebelumnya ditolak mengakses 127.0.0.1:5432 dengan Permission denied 10013. Jika pembatasan masih berlaku, pengguna harus mengekspor data melalui terminal laptopnya. Integrasi Supabase hanya memberi akses database cloud, bukan otomatis menghilangkan pembatasan ke database lokal. Koneksi Laravel ke server cloud juga harus diuji dari lingkungan yang dapat menjangkaunya.

Sumber resmi

- https://supabase.com/docs/guides/database/connecting-to-postgres
- https://supabase.com/docs/guides/database/postgres/row-level-security

## Status terakhir dan ekspor lokal

Di cloud telah diterapkan create_ssr_kasir_7_tables dan index_lapangan_kategori. Pemeriksaan SQL mengonfirmasi 7 tabel public, 7 tabel dilindungi RLS, dan index kategori tersedia. Data sudah diimpor: pengguna 1, pelanggan 1, kategori 2, lapangan 4, reservasi 1, pembayaran 1, jadwal 17. ID, hubungan FK, dan nilai baris diperiksa dalam transaksi. Nilai sequence dipulihkan. Akun pemilik tanpa transaksi tidak disalin, sesuai permintaan sebelumnya.

Ekspor awal sudah dijalankan melalui php artisan ssr:export. Ekspor memakai transaksi read-only repeatable-read dan menyimpan storage/app/private/supabase-export.json. Data tidak dihapus atau diubah. File mencakup akun beserta hash password, pelanggan, master, reservasi, pembayaran, dan slot, termasuk riwayat diarsipkan. Jangan unggah file ini ke Git atau layanan publik. Kirim hanya pemberitahuan hasil terminal, bukan isi file/rahasia.

Impor awal sudah selesai dan diverifikasi. Jangan mengulang impor ke tabel yang sudah terisi. Data baru kini ditulis langsung ke Supabase. Password database cloud sudah dimasukkan oleh pengguna lewat terminal tersembunyi dan koneksi runtime Laravel sudah dialihkan.

Advisory security hanya memberi INFO RLS Enabled No Policy untuk 7 tabel. Ini disengaja karena semua akses memakai Laravel server-side dan hak anon/authenticated dicabut. Jangan menambahkan policy publik untuk menyembunyikan pemberitahuan tersebut. Rujukan https://supabase.com/docs/guides/database/database-linter?lint=0008_rls_enabled_no_policy

Advisory FK lapangan.id_kategori sudah ditangani dengan index. Pemberitahuan unused index pada database kosong bukan alasan menghapus index yang mendukung foreign key/query aplikasi. Rujukan https://supabase.com/docs/guides/database/database-linter?lint=0001_unindexed_foreign_keys


## Akun dan langkah koneksi berikutnya

Akun Supabase telah diperbarui menjadi admin@admin dengan password admin123 (hash BCrypt), peran KASIR. ID petugas dan riwayat transaksi tetap. Pemeriksaan SQL mengonfirmasi satu akun kasir aktif, tanpa PEMILIK, tujuh tabel dilindungi RLS, dan tidak ada hak baca API publik.

Aplikasi sudah terhubung ke Supabase. Host Session pooler yang disalin pengguna adalah aws-1-ap-southeast-2.pooler.supabase.com. Username postgres.kbnbnvunyuwtilidinum, port 5432, database postgres, SSL require, zona waktu Asia/Jakarta.

Perintah perpindahan yang sudah dijalankan pengguna pada terminal PowerShell laptop:

```powershell
cd "C:\Users\user\Documents\Codex\2026-11-17\new-chat\ssr-kasir-7"
php artisan ssr:supabase
```

Password DATABASE Supabase dimasukkan pada prompt tersembunyi. Password ini berbeda dari password login aplikasi. Perintah menguji koneksi, zona waktu, tujuh tabel, akun admin, dan jumlah data cloud. Untuk sumber lokal, isi enam tabel bisnis dibandingkan dengan ekspor awal; akun pengguna dikecualikan karena perubahan akun admin/penghapusan pemilik memang disengaja. Bila pemeriksaan gagal, .env tidak diganti. Jangan mencatat transaksi lokal selama proses perpindahan; pemeriksaan sumber bukan penguncian permanen kasir lain.

Sesudah lolos, .env dicadangkan di storage/app/private/env-before-supabase-*.env, konfigurasi diganti secara atomik, APP_KEY dipertahankan, lalu cache konfigurasi dibersihkan. Password dengan karakter khusus ditulis dengan escaping yang diverifikasi melalui parser Dotenv. Cadangan .env tetap privat dan tidak masuk ZIP.

Jika ada server Laravel atau queue worker yang berjalan lama, mulai ulang setelah mengubah konfigurasi agar membaca nilai baru. Jalankan php artisan ssr:check, lalu buka aplikasi dan login admin@admin / admin123. Perintah ssr:akun-admin tidak diperlukan pada cloud karena akun tersebut sudah disiapkan. Koneksi langsung telah berhasil diuji oleh pengguna dari terminal laptop, dilanjutkan uji login dan penyimpanan reservasi. Sesi agen tetap tidak dapat menguji akses TCP langsung. Tahap berikutnya adalah hosting proses Laravel di Render, lihat docs/RENDER.md.
