<?php

// Tanpa --pgsql: SQLite in-memory, tidak menyentuh database PostgreSQL.
// Dengan --pgsql: schema tes acak, dihapus setelah tes. Public tidak diubah.
use App\Models\User;
use App\Services\Installer;
use App\Services\ReservationService;
use App\Support\Money;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$app->instance('env', 'testing');
config(['app.debug' => true, 'session.driver' => 'array', 'cache.default' => 'array']);
$postgres = in_array('--pgsql', $argv, true);
$schema = null;
$schemaCreated = false;
$exitCode = 0;
$checks = 0;
$fixedNow = \Carbon\CarbonImmutable::parse('2026-11-22 21:15:00', 'Asia/Jakarta');
\Carbon\CarbonImmutable::setTestNow($fixedNow);
\Illuminate\Support\Facades\Date::setTestNow($fixedNow);
function check(bool $condition, string $name): void {
    global $checks;
    if (! $condition) { throw new RuntimeException('GAGAL: '.$name); }
    $checks++;
    echo 'LULUS '.$checks.' - '.$name.PHP_EOL;
}
function rejects(Closure $fn, string $name): void {
    try { $fn(); } catch (ValidationException) { check(true, $name); return; }
    throw new RuntimeException('GAGAL: '.$name.' tidak ditolak');
}
try {
    if ($postgres) {
        config(['database.default' => 'pgsql']);
        $schema = 'ssr_test_'.bin2hex(random_bytes(6));
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        $schemaCreated = true;
        config(['database.connections.pgsql.search_path' => $schema]);
        DB::purge('pgsql');
    } else {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.foreign_key_constraints' => true]);
        DB::purge('sqlite');
    }
    $installer = app(Installer::class);
    $installer->install();
    $installer->seed();
    $installer->install();
    $installer->seed();
    check($installer->tables() === Installer::TABLES, 'Tepat tujuh tabel, instalasi/seed idempoten');
    check(DB::table('lapangan')->count() === 4 && User::count() === 1, 'Data Galaxy 4 lapangan dan hanya 1 akun kasir');
    $legacyOwner = User::create(['nama' => 'Pemilik Lama', 'email' => 'owner-old@example.test', 'password_hash' => 'OwnerTest2026!', 'peran' => 'PEMILIK', 'status_aktif' => true]);
    check(!Auth::attempt(['email' => $legacyOwner->email, 'password' => 'OwnerTest2026!', 'peran' => 'KASIR', 'status_aktif' => true]), 'Akun pemilik tidak dapat login sebagai kasir');
    $installer->removeOwners();
    check(!User::whereKey($legacyOwner->getKey())->exists(), 'Akun pemilik tanpa transaksi dihapus');
    $user = User::where('peran', 'KASIR')->firstOrFail();
    $futsal = DB::table('lapangan')->where('nama', 'Futsal 1')->value('id_lapangan');
    $badminton = DB::table('lapangan')->where('nama', 'Badminton 1')->value('id_lapangan');
    $service = app(ReservationService::class);
    $base = ['request_key' => (string) \Illuminate\Support\Str::uuid(), 'nama' => 'Nabil Test', 'no_whatsapp' => '6281234567890', 'email' => 'test@example.test', 'id_lapangan' => $futsal, 'tanggal' => now()->addDay()->toDateString(), 'jam_mulai' => '11:00', 'durasi_jam' => 2, 'metode' => 'TUNAI', 'status' => 'MENUNGGU_BAYAR', 'catatan' => '<script>alert(1)</script>'];
    $id = $service->save($user, $base);
    check(DB::table('jadwal_lapangan')->where('id_reservasi', $id)->count() === 2, 'Reservasi 2 jam menguasai 2 slot');
    check(Money::cents((string) DB::table('pembayaran')->where('id_reservasi', $id)->value('jumlah_bayar')) === 30000000, 'Tagihan futsal 2 jam Rp300.000');
    check($service->save($user, $base) === $id && DB::table('reservasi')->count() === 1, 'Pengiriman ulang tidak menduplikasi reservasi');
    $fresh = fn (array $replace = []) => array_replace($base, ['request_key' => (string) \Illuminate\Support\Str::uuid()], $replace);
    rejects(fn () => $service->save($user, $fresh(['jam_mulai' => '12:00', 'durasi_jam' => 1])), 'Bentrok sebagian pada jam kedua ditolak');
    check(DB::table('reservasi')->count() === 1 && DB::table('pembayaran')->count() === 1, 'Bentrok di-rollback tanpa transaksi parsial');
    $adjacent = $service->save($user, $fresh(['jam_mulai' => '13:00', 'durasi_jam' => 1]));
    check($adjacent !== $id, 'Jadwal berurutan tepat di batas akhir diterima');
    DB::table('lapangan')->where('id_lapangan', $futsal)->update(['harga_per_jam' => '180000.00']);
    $revision = ReservationService::revision($service->query()->where('r.id_reservasi', $id)->first());
    $service->save($user, $base + ['edit_revision' => $revision], $id);
    check(Money::cents((string) DB::table('reservasi')->where('id_reservasi', $id)->value('tarif_snapshot')) === 15000000, 'Edit lapangan sama mempertahankan snapshot tarif lama');
    $revision = ReservationService::revision($service->query()->where('r.id_reservasi', $id)->first());
    $service->save($user, array_replace($base, ['edit_revision' => $revision, 'catatan' => 'Diubah perangkat kedua']), $id);
    rejects(fn () => $service->save($user, $base + ['edit_revision' => $revision], $id), 'Edit dari data lama ditolak agar tidak menimpa perangkat lain');
    check(DB::table('reservasi')->where('id_reservasi', $id)->value('catatan') === 'Diubah perangkat kedua', 'Konflik edit mempertahankan perubahan yang sudah tersimpan');
    rejects(fn () => $service->save($user, $fresh(['jam_mulai' => '23:00', 'durasi_jam' => 2])), 'Melewati 24.00 ditolak');
    rejects(fn () => $service->save($user, $fresh(['id_lapangan' => $badminton, 'jam_mulai' => '20:00', 'status' => 'SELESAI'])), 'Selesai sebelum jam bermain berakhir ditolak');
    $countBeforePast = DB::table('reservasi')->count();
    rejects(fn () => $service->save($user, $fresh(['tanggal' => '2026-11-21', 'jam_mulai' => '23:00', 'durasi_jam' => 1])), 'Booking hari sebelumnya ditolak meskipun jamnya lebih besar');
    rejects(fn () => $service->save($user, $fresh(['tanggal' => '2026-11-22', 'jam_mulai' => '20:00', 'durasi_jam' => 1])), 'Pukul 21.15 WIB tidak dapat memesan pukul 20.00 hari ini');
    rejects(fn () => $service->save($user, $fresh(['tanggal' => '2026-11-22', 'jam_mulai' => '21:00', 'durasi_jam' => 1])), 'Jam berjalan 21.00 ditolak jika sekarang sudah 21.15');
    check(DB::table('reservasi')->count() === $countBeforePast, 'Penolakan waktu lampau tidak membuat transaksi');
    $tonight = $service->save($user, $fresh(['tanggal' => '2026-11-22', 'jam_mulai' => '22:00', 'durasi_jam' => 1]));
    check($tonight > 0, 'Pukul 22.00 hari ini masih bisa dipesan saat 21.15');
    $service->archive($tonight);
    $service->archive($id);
    $service->archive($id);
    check(DB::table('jadwal_lapangan')->where('id_reservasi', $id)->count() === 0, 'Hapus idempoten dan melepas seluruh slot');
    $new = $service->save($user, $fresh(['nama' => 'Nama Baru', 'durasi_jam' => 1, 'status' => 'TERKONFIRMASI']));
    check(DB::table('pembayaran')->where('id_reservasi', $new)->value('status') === 'BERHASIL', 'Konfirmasi kasir mencatat pelunasan');
    check(DB::table('reservasi')->where('id_reservasi', $id)->value('nama_snapshot') === 'Nabil Test', 'Perubahan profil pelanggan tidak mengubah dokumen historis');
    $paidRevision = ReservationService::revision($service->query()->where('r.id_reservasi', $new)->first());
    rejects(fn () => $service->save($user, $fresh(['edit_revision' => $paidRevision, 'durasi_jam' => 1, 'status' => 'MENUNGGU_BAYAR']), $new), 'Lunas tidak dapat dikembalikan ke menunggu');
    rejects(fn () => $service->save($user, $fresh(['edit_revision' => $paidRevision, 'durasi_jam' => 2, 'status' => 'TERKONFIRMASI']), $new), 'Tagihan yang sudah lunas tidak dapat diubah');
    rejects(fn () => $service->save($user, $fresh(['edit_revision' => $paidRevision, 'tanggal' => '2026-11-22', 'jam_mulai' => '20:00', 'durasi_jam' => 1, 'status' => 'TERKONFIRMASI']), $new), 'Edit tidak dapat memindahkan reservasi ke waktu yang sudah lewat');
    // Buat saat jamnya masih di masa depan, kemudian majukan jam untuk menyelesaikan riwayat.
    $pastPayload = $fresh(['id_lapangan' => $badminton, 'tanggal' => '2026-11-21', 'jam_mulai' => '23:00', 'durasi_jam' => 1, 'metode' => 'QRIS', 'status' => 'TERKONFIRMASI']);
    $earlier = $fixedNow->subDay()->setTime(22,30);
    \Carbon\CarbonImmutable::setTestNow($earlier);
    \Illuminate\Support\Facades\Date::setTestNow($earlier);
    $past = $service->save($user, $pastPayload);
    \Carbon\CarbonImmutable::setTestNow($fixedNow);
    \Illuminate\Support\Facades\Date::setTestNow($fixedNow);
    $pastRevision = ReservationService::revision($service->query()->where('r.id_reservasi', $past)->first());
    $service->save($user, array_replace($pastPayload, ['status' => 'SELESAI', 'edit_revision' => $pastRevision]), $past);
    check(DB::table('reservasi')->where('id_reservasi', $past)->value('status') === 'SELESAI', 'Jadwal lama 23.00–24.00 tetap boleh diselesaikan tanpa membuat booking lampau baru');
    $service->generate($badminton, $base['tanggal']);
    DB::table('jadwal_lapangan')->where('id_lapangan', $badminton)->where('tanggal', $base['tanggal'])->where('jam_mulai', '14:00:00')->update(['ditutup' => true]);
    rejects(fn () => $service->save($user, $fresh(['id_lapangan' => $badminton, 'jam_mulai' => '14:00', 'durasi_jam' => 1])), 'Slot ditutup tidak dapat dipesan');
    check(Money::decimal(Money::cents('9999999999.99')) === '9999999999.99', 'Perhitungan uang tepat tanpa float');
    Auth::guard()->setUser($user);
    $http = $app->make(\Illuminate\Contracts\Http\Kernel::class);
    foreach (['/' => 'Form dan tabel', '/reservasi/'.$new.'/edit' => 'Halaman edit', '/reservasi/cetak' => 'Halaman cetak', '/jadwal/tersedia?id_lapangan='.$futsal.'&tanggal='.$base['tanggal'] => 'Endpoint ketersediaan'] as $url => $name) {
        $request = \Illuminate\Http\Request::create($url, 'GET');
        $response = $http->handle($request);
        check($response->getStatusCode() === 200, $name.' HTTP 200'.($response->getStatusCode() !== 200 ? ' '.substr(strip_tags($response->getContent()),0,900) : ''));
        if ($url === '/') {
            check(!str_contains($response->getContent(), '<script>alert(1)</script>'), 'Input pelanggan di-escape pada HTML');
            check(str_contains($response->getContent(), 'id="date" min="2026-11-22"') && str_contains($response->getContent(), 'data-server-now="2026-11-22T21:15:00.000+07:00"'), 'Form memakai batas tanggal hari ini dan jam server WIB');
        }
        $http->terminate($request, $response);
    }
    $getLive = function (array $params = []) use ($http) {
        $request = \Illuminate\Http\Request::create('/reservasi/live?'.http_build_query($params), 'GET');
        $request->headers->set('Accept', 'application/json');
        $response = $http->handle($request);
        if ($response->getStatusCode() !== 200) { throw new RuntimeException('Live gagal: '.$response->getContent()); }
        check(str_contains($response->headers->get('Cache-Control'), 'no-store'), 'Respons live tidak disimpan cache');
        return json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    };
    $liveParams = ['slot_lapangan' => $futsal, 'slot_tanggal' => $base['tanggal'], 'editing' => $new];
    $liveFirst = $getLive($liveParams);
    check($liveFirst['server_now'] === '2026-11-22T21:15:00.000+07:00', 'Sinkronisasi live mengirim waktu server WIB untuk batas pemesanan');
    check($liveFirst['changed'] && $liveFirst['count'] === $service->query()->count() && is_string($liveFirst['html']), 'Snapshot live memuat daftar dan jumlah dari database');
    check($liveFirst['editing_revision'] === $paidRevision && !in_array('11:00', $liveFirst['terisi']), 'Snapshot edit memiliki versi dan tidak menganggap slot sendiri bentrok');
    $liveSame = $getLive($liveParams + ['known' => $liveFirst['version']]);
    check(!$liveSame['changed'] && $liveSame['html'] === null, 'Data tidak berubah tidak mengirim ulang HTML');
    $liveNew = $service->save($user, $fresh(['id_lapangan' => $futsal, 'jam_mulai' => '15:00', 'durasi_jam' => 1, 'nama' => 'LIVE <script>unsafe</script>']));
    $liveAdded = $getLive($liveParams + ['known' => $liveFirst['version']]);
    check($liveAdded['changed'] && $liveAdded['count'] === $liveFirst['count'] + 1 && $liveAdded['version'] !== $liveFirst['version'], 'Perubahan perangkat lain muncul pada snapshot berikutnya');
    check(in_array('15:00', $liveAdded['terisi']) && $liveAdded['value'] !== $liveFirst['value'], 'Slot dan total nilai diperbarui setelah reservasi lain masuk');
    check(str_contains($liveAdded['html'], '&lt;script&gt;unsafe&lt;/script&gt;') && !str_contains($liveAdded['html'], '<script>unsafe</script>'), 'HTML pembaruan live meng-escape input pengguna');
    $filteredLive = $getLive(['q' => 'LIVE']);
    check($filteredLive['count'] === 1, 'Pembaruan live menghormati filter pencarian');
    $service->archive($liveNew);
    $liveRemoved = $getLive($liveParams + ['known' => $liveAdded['version']]);
    check($liveRemoved['changed'] && $liveRemoved['count'] === $liveFirst['count'], 'Penghapusan perangkat lain memperbarui jumlah dan daftar');
    check(!in_array('15:00', $liveRemoved['terisi']) && $liveRemoved['value'] === $liveFirst['value'], 'Slot kembali tersedia dan nilai pulih setelah penghapusan');
    $lastPage = $getLive(['page' => 999]);
    check($lastPage['page'] === 1, 'Halaman yang kosong setelah penghapusan kembali ke halaman terakhir');
    $service->archive($new);
    check(DB::table('pembayaran')->where('id_reservasi', $new)->value('status') === 'BERHASIL', 'Penghapusan tidak menghilangkan penerimaan pembayaran');
    check($installer->tables() === Installer::TABLES, 'Jumlah tabel tetap tujuh setelah seluruh alur');
    $linkedOwner = User::create(['nama' => 'Pemilik Historis', 'email' => 'historical-owner@example.test', 'password_hash' => 'OwnerTest2026!', 'peran' => 'PEMILIK', 'status_aktif' => true]);
    DB::table('reservasi')->where('id_reservasi', $past)->update(['id_petugas' => $linkedOwner->getKey()]);
    $installer->removeOwners();
    check(User::findOrFail($linkedOwner->getKey())->status_aktif === false && DB::table('reservasi')->where('id_reservasi', $past)->exists(), 'Akun historis dinonaktifkan tanpa menghapus transaksi');
    $originalUserId = $user->getKey();
    User::whereKey($originalUserId)->update(['email' => 'kasir@galaxy.test']);
    $renamed = $installer->adminAccount(true);
    check($renamed->getKey() === $originalUserId && $renamed->email === 'admin@admin', 'Pembaruan akun sederhana mempertahankan ID petugas lama');
    check(\Illuminate\Support\Facades\Hash::check(env('DEMO_PASSWORD','admin123'), $renamed->password_hash), 'Password akun sederhana disimpan sebagai hash yang valid');
    Auth::logout();
    $liveGuestRequest = \Illuminate\Http\Request::create('/reservasi/live','GET');
    $liveGuestRequest->headers->set('Accept','application/json');
    $liveGuestResponse = $http->handle($liveGuestRequest);
    check($liveGuestResponse->getStatusCode() === 401, 'Data live tidak dapat dibaca tanpa login');
    $response = $http->handle(\Illuminate\Http\Request::create('/', 'GET'));
    check($response->getStatusCode() === 302, 'Form kasir memerlukan login');
    $response = $http->handle(\Illuminate\Http\Request::create('/masuk', 'GET'));
    check($response->getStatusCode() === 200, 'Halaman masuk dapat dibuka');
    $response = $http->handle(\Illuminate\Http\Request::create('/masuk', 'POST', ['email' => 'admin@admin', 'password' => env('DEMO_PASSWORD', 'admin123')]));
    check($response->getStatusCode() === 302 && Auth::check(), 'Login demo benar-benar diautentikasi');
    $payload = $fresh(['id_lapangan' => $badminton, 'jam_mulai' => '16:00', 'durasi_jam' => 1]);
    $before = DB::table('reservasi')->count();
    foreach ([1,2] as $attempt) {
        $request = \Illuminate\Http\Request::create('/reservasi', 'POST', $payload);
        $request->headers->set('Accept', 'application/json');
        $response = $http->handle($request);
        check($response->getStatusCode() === 302, 'POST formulir berhasil, percobaan '.$attempt);
    }
    check(DB::table('reservasi')->count() === $before + 1, 'Idempotensi bekerja melalui controller HTTP');
    $request = \Illuminate\Http\Request::create('/reservasi', 'POST', $fresh(['jam_mulai' => '16:30']));
    $request->headers->set('Accept', 'application/json');
    $response = $http->handle($request);
    check($response->getStatusCode() === 422, 'Jam bukan kelipatan satu jam ditolak HTTP 422');
    $request = \Illuminate\Http\Request::create('/reservasi', 'POST', $fresh(['tanggal' => '2026-11-21', 'jam_mulai' => '23:00', 'durasi_jam' => 1]));
    $request->headers->set('Accept', 'application/json');
    $response = $http->handle($request);
    check($response->getStatusCode() === 422 && str_contains($response->getContent(), 'sudah lewat'), 'POST langsung tetap menolak tanggal/jam lampau');
    $export = app(\App\Services\DatabaseExport::class)->snapshot();
    check(count($export['tables']) === 7 && $export['format'] === 'ssr-kasir-7-data-v1', 'Ekspor hanya mengambil tujuh tabel aplikasi');
    check($export['counts']['reservasi'] === DB::table('reservasi')->count() && $export['counts']['pembayaran'] === DB::table('pembayaran')->count(), 'Ekspor mencakup riwayat reservasi dan pembayaran lengkap');
    check(is_string($export['tables']['pengguna'][0]['id_pengguna']) && !empty($export['tables']['pengguna'][0]['password_hash']), 'ID ekspor presisi dan hash login tetap tersedia');
    check(!array_key_exists('APP_KEY', $export) && !array_key_exists('DB_PASSWORD', $export), 'Ekspor tidak memuat APP_KEY atau password koneksi database');
    $envSource = "APP_KEY=base64:unchanged\nDB_HOST=localhost\nDB_URL=postgresql://old-server/old-db\nDB_HOST=127.0.0.1\n";
    $envPasswords = ['plain-example', 'with space # and hash', 'dollar${APP_KEY}$end', 'quote"single\'slash\\end', "line\nbreak\rreturn"];
    foreach ($envPasswords as $index => $examplePassword) {
        $envUpdated = \App\Services\SupabaseConnection::environment($envSource, [
            'DB_HOST' => 'aws-1-ap-southeast-2.pooler.supabase.com', 'DB_URL' => '',
            'DB_PASSWORD' => $examplePassword, 'DB_EMULATE_PREPARES' => 'false',
        ]);
        $envParsed = \Dotenv\Dotenv::parse($envUpdated);
        check($envParsed['DB_PASSWORD'] === $examplePassword, 'Password konfigurasi dipertahankan, variasi '.($index + 1));
        check($envParsed['APP_KEY'] === 'base64:unchanged' && $envParsed['DB_HOST'] === config('supabase.host')
            && $envParsed['DB_URL'] === '' && $envParsed['DB_EMULATE_PREPARES'] === 'false', 'Koneksi lama diganti dan APP_KEY dipertahankan, variasi '.($index + 1));
    }
    echo PHP_EOL.'BERHASIL: '.$checks.' pemeriksaan ('.($postgres ? 'PostgreSQL' : 'SQLite in-memory').').'.PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->__toString().PHP_EOL);
    $exitCode = 1;
} finally {
    \Carbon\CarbonImmutable::setTestNow();
    \Illuminate\Support\Facades\Date::setTestNow();
    if ($schemaCreated) {
        DB::statement('DROP SCHEMA IF EXISTS "'.$schema.'" CASCADE');
    }
}
exit($exitCode);
