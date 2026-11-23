<?php

namespace App\Services;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SensitiveParameter;

class SupabaseConnection
{
    public static function environment(string $existing, #[SensitiveParameter] array $updates): string
    {
        foreach ($updates as $key => $value) {
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/D', $key)) { throw new RuntimeException('Nama konfigurasi tidak valid.'); }
            $quoted = '"'.str_replace(['\\','"','$',"\r","\n"], ['\\\\','\\"','\\$','\\r','\\n'], (string)$value).'"';
            $line = $key.'='.$quoted;
            $pattern = '/^[\t ]*'.preg_quote($key,'/').'[\t ]*=.*$/m';
            if (preg_match($pattern,$existing)) {
                $existing = preg_replace_callback($pattern, fn () => $line, $existing);
            } else { $existing = rtrim($existing).PHP_EOL.$line.PHP_EOL; }
        }
        $parsed = Dotenv::parse($existing);
        foreach ($updates as $key => $value) {
            if (($parsed[$key] ?? null) !== (string)$value) { throw new RuntimeException('Konfigurasi tidak dapat disimpan dengan benar.'); }
        }
        return $existing;
    }

    public function connect(#[SensitiveParameter] string $password): array
    {
        if ($password === '') { throw new RuntimeException('Password database Supabase wajib diisi.'); }
        $target = config('supabase');
        $profile = array_replace(config('database.connections.pgsql'), [
            'url' => null, 'host' => $target['host'], 'port' => $target['port'],
            'database' => $target['database'], 'username' => $target['username'], 'password' => $password,
            'search_path' => 'public', 'sslmode' => 'require', 'timezone' => 'Asia/Jakarta',
            'options' => [\PDO::ATTR_EMULATE_PREPARES => false],
        ]);
        config(['database.connections.supabase_probe' => $profile]);
        DB::purge('supabase_probe');
        $previousTimeout = getenv('PGCONNECT_TIMEOUT');
        putenv('PGCONNECT_TIMEOUT=8');
        try {
            $connection = DB::connection('supabase_probe');
            $database = $connection->selectOne('SELECT current_database() AS name,current_setting(\'TimeZone\') AS timezone');
            if ($database->name !== 'postgres' || $database->timezone !== 'Asia/Jakarta') {
                throw new RuntimeException('Database atau zona waktu tujuan tidak sesuai.');
            }
            $tables = $connection->table('information_schema.tables')->where('table_schema','public')->where('table_type','BASE TABLE')->orderBy('table_name')->pluck('table_name')->all();
            if ($tables !== Installer::TABLES) { throw new RuntimeException('Struktur Supabase harus tepat tujuh tabel SSR.'); }
            if (!$connection->table('pengguna')->where('email','admin@admin')->where('peran','KASIR')->where('status_aktif',true)->exists()) {
                throw new RuntimeException('Akun admin belum tersedia pada database tujuan.');
            }
            $counts = [];
            foreach ($tables as $table) { $counts[$table] = $connection->table($table)->count(); }

            // Jangan mengalihkan aplikasi bila data bisnis lokal berubah sesudah ekspor.
            $current = DB::connection();
            if ($current->getDriverName() === 'pgsql' && in_array($current->getConfig('host'),['127.0.0.1','localhost','::1'],true)) {
                $exportPath = storage_path('app/private/supabase-export.json');
                if (!is_file($exportPath)) { throw new RuntimeException('File ekspor sumber belum ditemukan.'); }
                $export = json_decode(file_get_contents($exportPath),true,flags: JSON_THROW_ON_ERROR);
                if (($export['format'] ?? null) !== 'ssr-kasir-7-data-v1') {
                    throw new RuntimeException('Format ekspor sumber tidak sesuai.');
                }
                $latest = app(DatabaseExport::class)->snapshot();
                foreach (array_diff($tables,['pengguna']) as $table) {
                    if (($latest['tables'][$table] ?? null) !== ($export['tables'][$table] ?? null)
                        || $counts[$table] !== ($export['counts'][$table] ?? -1)) {
                        throw new RuntimeException('Data berubah sejak ekspor. Sinkronkan data terlebih dahulu sebelum mengalihkan koneksi.');
                    }
                }
            }
            $envPath = base_path('.env');
            $before = file_get_contents($envPath);
            if ($before === false) { throw new RuntimeException('Konfigurasi aplikasi tidak dapat dibaca.'); }
            $updates = [
                'DB_CONNECTION'=>'pgsql','DB_URL'=>'','DB_HOST'=>$target['host'],'DB_PORT'=>(string)$target['port'],
                'DB_DATABASE'=>'postgres','DB_USERNAME'=>$target['username'],'DB_PASSWORD'=>$password,
                'DB_SCHEMA'=>'public','DB_SSLMODE'=>'require','DB_EMULATE_PREPARES'=>'false',
            ];
            $after = self::environment($before,$updates);
            if ((Dotenv::parse($before)['APP_KEY'] ?? null) !== (Dotenv::parse($after)['APP_KEY'] ?? null)) {
                throw new RuntimeException('APP_KEY harus dipertahankan.');
            }
            $suffix = date('YmdHis').'-'.bin2hex(random_bytes(4));
            $backup = storage_path('app/private/env-before-supabase-'.$suffix.'.env');
            $next = storage_path('app/private/env-next-'.$suffix.'.tmp');
            if (file_put_contents($backup,$before,LOCK_EX) === false || file_put_contents($next,$after,LOCK_EX) === false) {
                throw new RuntimeException('Cadangan konfigurasi tidak dapat disimpan.');
            }
            @chmod($backup,0600);
            @chmod($next,0600);
            if (!rename($next,$envPath)) { throw new RuntimeException('Konfigurasi belum dapat diganti. Cadangan tetap disimpan.'); }
            return ['backup'=>$backup,'counts'=>$counts,'host'=>$target['host']];
        } finally {
            DB::purge('supabase_probe');
            config(['database.connections.supabase_probe' => null]);
            if ($previousTimeout === false) { putenv('PGCONNECT_TIMEOUT'); } else { putenv('PGCONNECT_TIMEOUT='.$previousTimeout); }
        }
    }
}
