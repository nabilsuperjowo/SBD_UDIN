# Hosting SSR di Render Free

Database Supabase sudah digunakan oleh aplikasi lokal. Deploy ini hanya memindahkan proses web Laravel ke Render, tetap memakai public schema dengan tujuh tabel yang ada. Tidak membuat database, menjalankan migrasi, mengimpor ulang, atau mereset akun.

## Berkas yang disiapkan

- Dockerfile: PHP 8.4 + Apache, PostgreSQL PDO, Composer no-dev.
- render.yaml: satu Web Service bernama ssr dengan plan free, region Singapore, health check /up.
- docker/entrypoint.sh: memvalidasi environment, memakai PORT Render, menyiapkan cache konfigurasi/rute/view, lalu menjalankan Apache.
- .dockerignore dan .gitignore: mengecualikan .env, ekspor data, cadangan rahasia, vendor lokal, dan cache runtime.
- HTTPS proxy dikenali hanya pada environment Render. Pada production, URL aplikasi dipaksa HTTPS.

## Hubungkan akun dan kode

Hubungkan integrasi Render di ChatGPT agar status dan deployment dapat dikelola dari percakapan. Pengguna harus menyelesaikan login/otorisasi sendiri. Selama koneksi belum dikonfirmasi, belum ada layanan Render yang dibuat.

Kode harus tersedia dalam repository Git yang dapat dibaca Render, atau image Docker yang dapat ditarik Render. Bila menggunakan GitHub, buat repository private lalu masukkan isi folder ssr-kasir-7 sebagai root repository. Sertakan Dockerfile, render.yaml, composer.json/lock, app, bootstrap, config, docker, database, public, resources, routes, dan artisan. Jangan unggah .env atau storage/app/private. Template .env.example dan .env.supabase.example boleh disertakan.

Jika memakai dashboard, buka https://dashboard.render.com, pilih New > Blueprint, lalu hubungkan repository tersebut. Render membaca render.yaml dan menyiapkan tepat satu Web Service Free. Periksa plan tetap Free sebelum membuat layanan. Tidak perlu Render Postgres, disk, worker, cron, atau Redis.

## Rahasia pada Environment Render

Blueprint meminta dua nilai:

| Key | Isi |
| --- | --- |
| APP_KEY | Salin nilai APP_KEY yang sudah ada di .env lokal, termasuk awalan base64:. Pertahankan nilainya. |
| DB_PASSWORD | Password DATABASE Supabase yang sudah berhasil dipakai melalui ssr:supabase. |

Masukkan nilai langsung pada form Environment Render tanpa tanda kutip pembungkus dari file .env. Jangan kirim ke chat, repository, atau build arguments. Jangan menjalankan key:generate setiap startup. APP_URL otomatis mengambil RENDER_EXTERNAL_URL saat startup; untuk custom domain, tentukan APP_URL=https://domain-yang-dipakai.

Nilai lainnya sudah didefinisikan dalam render.yaml: APP_ENV production, APP_DEBUG false, DB_CONNECTION pgsql, session pooler port 5432, DB_SSLMODE require, SESSION_DRIVER file, SESSION_SECURE_COOKIE true, CACHE_STORE file, QUEUE_CONNECTION sync, dan LOG_CHANNEL stderr. Password login aplikasi tetap admin@admin / admin123 sesuai akun yang sudah tersimpan di Supabase.

Jika membuat Web Service secara manual, pilih runtime Docker, plan Free, Dockerfile ./Dockerfile, Docker context ., Health Check Path /up; pindahkan semua envVars dari render.yaml ke Environment dan isi dua rahasia tersebut. Jangan menyalin APP_URL localhost dari .env lokal.

## Memeriksa hasil

Tunggu build dan startup berhasil, lalu buka URL HTTPS yang diberikan Render. Periksa /up, halaman /masuk, login, daftar reservasi, simpan/edit dengan tanggal mendatang, penolakan jadwal bentrok/lampau, dan sinkronisasi dua tab. Pemeriksaan simpan memakai data nyata: beri nama jelas untuk transaksi uji dan arsipkan sesudahnya. Pastikan data lokal yang sama terlihat karena kedua instance memakai database Supabase yang sama.

Login tetap menggunakan session file, tanpa tabel kedelapan. Filesystem Render bersifat sementara: login dapat berakhir saat restart/redeploy/tidur sehingga pengguna perlu masuk kembali. Data reservasi dan pembayaran tetap berada di Supabase. Cache throttle juga kembali kosong saat instance diganti; satu instance Free digunakan untuk demo ini. Jangan menyimpan file unggahan permanen di disk Render Free.

Render Free tidur setelah 15 menit tanpa traffic; akses berikutnya dapat memerlukan sekitar satu menit. Kuota bulanan tetap berlaku. Buka aplikasi lebih awal sebelum presentasi. Polling tiap tiga detik hanya berjalan ketika halaman kasir terlihat.

## Validasi lokal

```powershell
php scripts/verify.php
php scripts/verify-render.php
```

Suite aplikasi memakai SQLite in-memory, bukan database Supabase. Suite Render memeriksa respons HTTP di balik TLS proxy tanpa menghubungi PostgreSQL. Docker Desktop pada sesi persiapan belum menjalankan Linux engine; image belum dibangun/diuji melalui Docker. Build image, startup Apache, dan koneksi dari jaringan Render harus dikonfirmasi saat deployment. Jangan menyebut website sudah online sebelum mendapat deployment sukses dan URL yang benar-benar dapat dibuka.

Sumber resmi:

- https://render.com/docs/deploy-php-laravel-docker
- https://render.com/docs/blueprint-spec
- https://render.com/docs/configure-environment-variables
- https://render.com/docs/free
