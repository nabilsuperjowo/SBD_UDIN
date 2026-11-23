<?php

namespace App\Services;

use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public const STATUSES = ['MENUNGGU_BAYAR' => 'Menunggu Pembayaran', 'TERKONFIRMASI' => 'Terkonfirmasi', 'SELESAI' => 'Selesai', 'DIBATALKAN' => 'Dibatalkan'];
    public const METHODS = ['TUNAI' => 'Tunai', 'TRANSFER' => 'Transfer', 'QRIS' => 'QRIS'];

    public static function phone(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);
        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }

    public static function revision(object $row): string
    {
        return hash('sha256', json_encode(array_map(fn ($key) => (string) ($row->{$key} ?? ''), [
            'id_reservasi', 'id_pelanggan', 'id_lapangan', 'nama_snapshot', 'whatsapp_snapshot', 'email_snapshot',
            'tanggal', 'jam_mulai', 'durasi_jam', 'tarif_snapshot', 'status', 'catatan', 'diubah_pada', 'dihapus_pada',
        ])));
    }

    public function query()
    {
        return DB::table('reservasi as r')
            ->join('lapangan as l', 'l.id_lapangan', '=', 'r.id_lapangan')
            ->join('kategori_lapangan as k', 'k.id_kategori', '=', 'l.id_kategori')
            ->join('pembayaran as p', 'p.id_reservasi', '=', 'r.id_reservasi')
            ->whereNull('r.dihapus_pada')
            ->select('r.*', 'l.nama as nama_lapangan', 'k.nama as kategori', 'p.metode', 'p.status as status_pembayaran', 'p.jumlah_bayar')
            ->selectRaw('(r.tarif_snapshot * r.durasi_jam) AS total_bayar');
    }

    public function save(User $actor, array $data, ?int $id = null): int
    {
        try {
            return DB::transaction(function () use ($actor, $data, $id) {
                $old = $id ? DB::table('reservasi')->where('id_reservasi', $id)->whereNull('dihapus_pada')->lockForUpdate()->firstOrFail() : null;
                if ($old) {
                    $this->ensure(isset($data['edit_revision']) && hash_equals(self::revision($old), $data['edit_revision']), 'Reservasi ini sudah berubah dari perangkat lain. Muat ulang data sebelum menyimpan perubahan.');
                }
                $courtIds = array_unique(array_filter([$old?->id_lapangan, (int) $data['id_lapangan']]));
                $courts = DB::table('lapangan')->whereIn('id_lapangan', $courtIds)->orderBy('id_lapangan')->lockForUpdate()->get()->keyBy('id_lapangan');
                $court = $courts->get((int) $data['id_lapangan']);
                abort_unless($court, 404);
                if (! $id && ($existing = DB::table('reservasi')->where('request_key', $data['request_key'])->first())) {
                    return (int) $existing->id_reservasi;
                }
                $this->ensure($court->status_aktif && DB::table('kategori_lapangan')->where('id_kategori', $court->id_kategori)->value('status_aktif'), 'Lapangan atau kategorinya sedang nonaktif.');
                $start = CarbonImmutable::parse($data['tanggal'].' '.$data['jam_mulai'], 'Asia/Jakarta');
                $hour = $start->hour;
                $duration = (int) $data['durasi_jam'];
                $this->ensure($start->minute === 0 && $start->second === 0 && $hour >= 7 && $hour + $duration <= 24, 'Jam main harus berada antara 07.00 dan 24.00 WIB.');
                $scheduleUnchanged = $old
                    && (int) $old->id_lapangan === (int) $court->id_lapangan
                    && $old->tanggal === $data['tanggal']
                    && substr($old->jam_mulai, 0, 5) === $start->format('H:i')
                    && (int) $old->durasi_jam === $duration;
                $serverNow = CarbonImmutable::now('Asia/Jakarta');
                // Data lama masih boleh diperbarui statusnya. Booking/reschedule baru wajib di masa depan.
                if (! $scheduleUnchanged) {
                    $this->ensure($start->gt($serverNow), 'Tanggal dan jam mulai sudah lewat. Pilih waktu setelah jam sekarang (WIB).');
                }
                $this->ensure($data['status'] !== 'SELESAI' || $start->addHours($duration)->lte($serverNow), 'Reservasi baru boleh ditandai Selesai setelah jam bermain berakhir.');

                $price = $old && (int) $old->id_lapangan === (int) $court->id_lapangan ? $old->tarif_snapshot : $court->harga_per_jam;
                $total = Money::decimal(Money::cents((string) $price) * $duration);
                $payment = $old ? DB::table('pembayaran')->where('id_reservasi', $id)->lockForUpdate()->firstOrFail() : null;
                if ($payment?->status === 'BERHASIL') {
                    $this->ensure($data['status'] !== 'MENUNGGU_BAYAR', 'Pembayaran sudah diterima. Status tidak dapat dikembalikan ke menunggu.');
                    $this->ensure(Money::cents((string) $payment->jumlah_bayar) === Money::cents($total), 'Tagihan reservasi yang sudah lunas tidak dapat diubah. Pilih durasi atau lapangan dengan total yang sama.');
                    $this->ensure($payment->metode === $data['metode'], 'Metode pembayaran yang sudah lunas tidak dapat diubah.');
                }
                if ($old?->status === 'DIBATALKAN') {
                    $this->ensure($data['status'] === 'DIBATALKAN', 'Reservasi dibatalkan tidak dapat diaktifkan lagi. Buat reservasi baru.');
                }

                $this->generate((int) $court->id_lapangan, $data['tanggal']);
                $hours = array_map(fn ($h) => sprintf('%02d:00:00', $h), range($hour, $hour + $duration - 1));
                $slots = DB::table('jadwal_lapangan')->where('id_lapangan', $court->id_lapangan)
                    ->where('tanggal', $data['tanggal'])->whereIn('jam_mulai', $hours)->orderBy('jam_mulai')->lockForUpdate()->get();
                if ($data['status'] !== 'DIBATALKAN') {
                    $this->ensure($slots->count() === $duration, 'Slot jadwal belum lengkap.');
                    foreach ($slots as $slot) {
                        $this->ensure(! $slot->ditutup && ($slot->id_reservasi === null || (int) $slot->id_reservasi === $id), 'Jadwal bertabrakan atau slot ditutup. Pilih jam atau lapangan lain.');
                    }
                }

                DB::table('pelanggan')->insertOrIgnore(['nama' => $data['nama'], 'no_whatsapp' => $data['no_whatsapp'], 'email' => $data['email'] ?? null, 'dibuat_pada' => now()]);
                $customer = DB::table('pelanggan')->where('no_whatsapp', $data['no_whatsapp'])->lockForUpdate()->firstOrFail();
                DB::table('pelanggan')->where('id_pelanggan', $customer->id_pelanggan)->update(['nama' => $data['nama'], 'email' => $data['email'] ?? null]);
                if (! $scheduleUnchanged) {
                    $this->ensure($start->gt(CarbonImmutable::now('Asia/Jakarta')), 'Jam mulai sudah lewat saat pemesanan diproses. Pilih jam berikutnya.');
                }
                $row = [
                    'id_pelanggan' => $customer->id_pelanggan,
                    'id_lapangan' => $court->id_lapangan,
                    'nama_snapshot' => $data['nama'], 'whatsapp_snapshot' => $data['no_whatsapp'], 'email_snapshot' => $data['email'] ?? null,
                    'tanggal' => $data['tanggal'], 'jam_mulai' => $start->format('H:i:s'), 'durasi_jam' => $duration,
                    'tarif_snapshot' => $price, 'status' => $data['status'], 'catatan' => $data['catatan'] ?? null, 'diubah_pada' => now(),
                ];
                if ($old) {
                    DB::table('reservasi')->where('id_reservasi', $id)->update($row);
                    DB::table('jadwal_lapangan')->where('id_reservasi', $id)->update(['id_reservasi' => null]);
                } else {
                    $id = (int) DB::table('reservasi')->insertGetId($row + [
                        'id_petugas' => $actor->getKey(), 'kode' => 'GFC-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                        'request_key' => $data['request_key'], 'dibuat_pada' => now(),
                    ], 'id_reservasi');
                }
                if ($data['status'] !== 'DIBATALKAN') {
                    DB::table('jadwal_lapangan')->whereIn('id_slot', $slots->pluck('id_slot'))->update(['id_reservasi' => $id]);
                }
                $paid = in_array($data['status'], ['TERKONFIRMASI', 'SELESAI'], true);
                $paymentData = ['id_petugas' => $actor->getKey(), 'metode' => $data['metode']];
                if ($payment?->status !== 'BERHASIL') {
                    $paymentData += ['jumlah_bayar' => $total, 'status' => $paid ? 'BERHASIL' : ($data['status'] === 'DIBATALKAN' ? 'DIBATALKAN' : 'MENUNGGU'), 'dibayar_pada' => $paid ? now() : null];
                }
                if ($payment) {
                    DB::table('pembayaran')->where('id_pembayaran', $payment->id_pembayaran)->update($paymentData);
                } else {
                    DB::table('pembayaran')->insert($paymentData + ['id_reservasi' => $id, 'dibuat_pada' => now()]);
                }
                return $id;
            }, 5);
        } catch (UniqueConstraintViolationException $e) {
            if (! $id && ($duplicate = DB::table('reservasi')->where('request_key', $data['request_key'])->first())) {
                return (int) $duplicate->id_reservasi;
            }
            throw $e;
        }
    }

    public function archive(int $id): void
    {
        DB::transaction(function () use ($id) {
            $r = DB::table('reservasi')->where('id_reservasi', $id)->lockForUpdate()->firstOrFail();
            if ($r->dihapus_pada !== null) { return; }
            DB::table('lapangan')->where('id_lapangan', $r->id_lapangan)->lockForUpdate()->firstOrFail();
            DB::table('jadwal_lapangan')->where('id_reservasi', $id)->update(['id_reservasi' => null]);
            DB::table('reservasi')->where('id_reservasi', $id)->update(['status' => 'DIBATALKAN', 'dihapus_pada' => now(), 'diubah_pada' => now()]);
            DB::table('pembayaran')->where('id_reservasi', $id)->where('status', 'MENUNGGU')->update(['status' => 'DIBATALKAN']);
        }, 5);
    }

    public function generate(int $court, string $date): void
    {
        $slots = [];
        for ($hour = 7; $hour < 24; $hour++) {
            $slots[] = ['id_lapangan' => $court, 'tanggal' => $date, 'jam_mulai' => sprintf('%02d:00:00', $hour), 'ditutup' => false];
        }
        DB::table('jadwal_lapangan')->insertOrIgnore($slots);
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) { throw ValidationException::withMessages(['reservasi' => $message]); }
    }
}
