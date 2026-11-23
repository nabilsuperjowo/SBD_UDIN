<?php

use App\Services\Installer;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('ssr:database', function () {
    $name = config('database.connections.pgsql.database');
    if (! is_string($name) || ! preg_match('/^[a-z][a-z0-9_]{0,62}$/D', $name) || in_array($name, ['postgres','template0','template1'])) {
        $this->error('Nama database tujuan tidak diizinkan.'); return 1;
    }
    config(['database.connections.install_admin' => array_replace(config('database.connections.pgsql'), ['database' => 'postgres'])]);
    $admin = DB::connection('install_admin');
    if (! $admin->select('SELECT 1 FROM pg_database WHERE datname = ?', [$name])) {
        $admin->statement('CREATE DATABASE "'.$name.'"');
        $this->info('Database '.$name.' dibuat.');
    } else { $this->info('Database '.$name.' sudah ada.'); }
    DB::disconnect('install_admin');
    return $this->call('ssr:install', ['--demo' => true]);
})->purpose('Buat database PostgreSQL baru tanpa menghapus database lama.');

Artisan::command('ssr:install {--demo}', function (Installer $installer) {
    $installer->install();
    if ($this->option('demo')) { $installer->seed(); }
    $this->info('Struktur siap. Tepat '.count($installer->tables()).' tabel, tanpa tabel migrations.');
    if ($this->option('demo')) { $this->info('Akun demo: admin@admin. Password mengikuti DEMO_PASSWORD di .env.'); }
})->purpose('Pasang struktur 7 tabel pada database kosong.');

Artisan::command('ssr:hapus-pemilik', function (Installer $installer) {
    $result = $installer->removeOwners();
    $this->info('Akun pemilik dihapus: '.$result['deleted'].'. Akun terkait transaksi dinonaktifkan: '.$result['archived'].'.');
    $this->info('Hanya akun kasir aktif yang dapat masuk. Riwayat transaksi tetap tersimpan.');
});

Artisan::command('ssr:akun-admin', function (Installer $installer) {
    $installer->adminAccount(true);
    $installer->removeOwners();
    $this->info('Akun kasir siap: admin@admin. Password mengikuti DEMO_PASSWORD di .env. ID petugas dan riwayat tetap dipertahankan.');
});

Artisan::command('ssr:slots {--hari=30}', function (ReservationService $reservations) {
    $days = max(1, min(365, (int) $this->option('hari')));
    foreach (DB::table('lapangan')->where('status_aktif', true)->pluck('id_lapangan') as $court) {
        for ($i = 0; $i < $days; $i++) { $reservations->generate($court, now()->addDays($i)->toDateString()); }
    }
    $this->info('Jadwal '.$days.' hari siap. Pembuatan ulang tidak menduplikasi slot.');
});

Artisan::command('ssr:check', function (Installer $installer) {
    $tables = $installer->tables();
    $this->table(['Tabel'], array_map(fn ($table) => [$table], $tables));
    if ($tables !== Installer::TABLES) { $this->error('Jumlah/daftar tabel berbeda dari rancangan 7 tabel.'); return 1; }
    $this->info('LULUS: tepat 7 tabel aplikasi.');
});

Artisan::command('ssr:export', function (\App\Services\DatabaseExport $export) {
    $connection = DB::connection();
    if ($connection->getDriverName() !== 'pgsql'
        || !in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)
        || $connection->getDatabaseName() !== 'ssr_kasir_7') {
        $this->error('Ekspor ini khusus database lokal ssr_kasir_7. Konfigurasi tidak diubah.');
        return 1;
    }
    $file = $export->write();
    $this->info('Ekspor berhasil: '.$file);
    $this->info('Database tidak diubah. File berisi data pelanggan dan hash password; jangan unggah ke Git atau bagikan publik.');
})->purpose('Ekspor data tujuh tabel lokal untuk dipindahkan ke Supabase.');

Artisan::command('ssr:supabase', function (\App\Services\SupabaseConnection $supabase) {
    $this->info('Tujuan: '.config('supabase.host').' (Session pooler).');
    $password = (string)$this->secret('Password DATABASE Supabase (bukan password login admin@admin)', false);
    $this->info('Menguji koneksi, struktur, akun, dan jumlah data sebelum mengganti .env...');
    try {
        $result = $supabase->connect($password);
    } catch (Throwable $error) {
        $this->error('Koneksi belum dialihkan. Periksa password database Supabase, koneksi internet, dan data sumber.');
        if (get_class($error) === RuntimeException::class) { $this->error($error->getMessage()); }
        else { $this->error('Kode kesalahan: '.(string)$error->getCode()); }
        return 1;
    } finally { unset($password); }
    $this->call('config:clear');
    $this->info('BERHASIL: .env aplikasi sekarang menggunakan Supabase. APP_KEY dipertahankan.');
    $this->line('Cadangan konfigurasi: '.$result['backup']);
    $this->table(['Tabel','Baris'],array_map(fn($table,$count)=>[$table,$count],array_keys($result['counts']),$result['counts']));
    $this->info('Buka ulang halaman aplikasi dan login dengan admin@admin.');
})->purpose('Uji dan alihkan koneksi Laravel ke proyek Supabase SSR yang telah diimpor.');
