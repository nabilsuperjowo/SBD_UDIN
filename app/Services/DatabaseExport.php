<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseExport
{
    private const ORDER = [
        'pengguna' => 'id_pengguna', 'pelanggan' => 'id_pelanggan',
        'kategori_lapangan' => 'id_kategori', 'lapangan' => 'id_lapangan',
        'reservasi' => 'id_reservasi', 'pembayaran' => 'id_pembayaran', 'jadwal_lapangan' => 'id_slot',
    ];

    public function snapshot(): array
    {
        return DB::transaction(function () {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            }
            if (app(Installer::class)->tables() !== Installer::TABLES) {
                throw new RuntimeException('Struktur sumber harus tepat tujuh tabel SSR Kasir. Ekspor dibatalkan.');
            }
            $tables = [];
            $counts = [];
            foreach (self::ORDER as $table => $key) {
                $tables[$table] = DB::table($table)->orderBy($key)->get()->map(function ($record) {
                    $row = (array) $record;
                    foreach ($row as $column => &$value) {
                        // Pertahankan BIGINT tanpa pembulatan saat JSON dibaca JavaScript.
                        if ($value !== null && str_starts_with($column, 'id_')) {
                            $value = (string) $value;
                        }
                    }
                    unset($value);
                    return $row;
                })->all();
                $counts[$table] = count($tables[$table]);
            }
            return [
                'format' => 'ssr-kasir-7-data-v1',
                'source_database' => DB::connection()->getDatabaseName(),
                'exported_at' => now('Asia/Jakarta')->toIso8601String(),
                'counts' => $counts, 'tables' => $tables,
            ];
        });
    }

    public function write(): string
    {
        $data = $this->snapshot();
        $file = storage_path('app/private/supabase-export.json');
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($file, $json, LOCK_EX) === false) {
            throw new RuntimeException('File ekspor tidak dapat disimpan. Database tidak diubah.');
        }
        return $file;
    }
}
