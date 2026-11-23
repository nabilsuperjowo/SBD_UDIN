<?php

// Runs before Laravel starts. Never print secret values in build/runtime logs.
$fail = static function (string $message): never {
    fwrite(STDERR, 'Konfigurasi deploy belum lengkap: '.$message.PHP_EOL);
    exit(1);
};
$key = (string) getenv('APP_KEY');
$decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
if (!is_string($decoded) || strlen($decoded) !== 32) { $fail('APP_KEY harus memakai kunci Laravel yang valid dan tetap.'); }
if (getenv('APP_ENV') !== 'production' || !in_array(getenv('APP_DEBUG'), ['false', '0'], true)) {
    $fail('APP_ENV=production dan APP_DEBUG=false wajib digunakan.');
}
if (getenv('DB_CONNECTION') !== 'pgsql' || getenv('DB_SSLMODE') !== 'require') {
    $fail('Gunakan PostgreSQL dengan DB_SSLMODE=require.');
}
foreach (['DB_HOST','DB_DATABASE','DB_USERNAME','DB_PASSWORD'] as $name) {
    if (getenv($name) === false || getenv($name) === '') { $fail($name.' wajib diisi pada Environment Render.'); }
}
if (getenv('SESSION_DRIVER') !== 'file' || getenv('CACHE_STORE') !== 'file') {
    $fail('SESSION_DRIVER dan CACHE_STORE harus file agar tetap tujuh tabel.');
}
if (!in_array(getenv('SESSION_SECURE_COOKIE'), ['true', '1'], true)) {
    $fail('SESSION_SECURE_COOKIE=true wajib digunakan.');
}
$port = (string) (getenv('PORT') ?: '10000');
if (!ctype_digit($port) || (int) $port < 1024 || (int) $port > 65535) { $fail('PORT tidak valid.'); }
$url = (string) (getenv('APP_URL') ?: getenv('RENDER_EXTERNAL_URL'));
if (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
    $fail('APP_URL atau RENDER_EXTERNAL_URL harus URL HTTPS yang valid.');
}
echo 'Konfigurasi deploy valid; rahasia tidak ditampilkan.'.PHP_EOL;
