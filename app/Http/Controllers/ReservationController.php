<?php

namespace App\Http\Controllers;

use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController
{
    private function filtered(Request $request, ReservationService $service)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'tanggal' => 'nullable|date_format:Y-m-d', 'status' => 'nullable|in:MENUNGGU_BAYAR,TERKONFIRMASI,SELESAI,DIBATALKAN']);
        return $service->query()
            ->when($filters['q'] ?? null, function ($query, $search) {
                $escaped = str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $search);
                $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->where(fn ($q) => $q->where('r.nama_snapshot', $operator, '%'.$escaped.'%')->orWhere('r.kode', $operator, '%'.$escaped.'%')->orWhere('r.whatsapp_snapshot', $operator, '%'.$escaped.'%'));
            })
            ->when($filters['tanggal'] ?? null, fn ($q, $d) => $q->where('r.tanggal', $d))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('r.status', $status));
    }

    public function index(Request $request, ReservationService $service, ?int $id = null)
    {
        ['stats' => $stats, 'rows' => $rows] = $this->listing($request, $service);
        $courts = DB::table('lapangan as l')->join('kategori_lapangan as k','k.id_kategori','=','l.id_kategori')->where('l.status_aktif', true)->where('k.status_aktif', true)->select('l.*', 'k.nama as kategori')->orderBy('l.id_lapangan')->get();
        $editing = $id ? $service->query()->where('r.id_reservasi', $id)->firstOrFail() : null;
        return view('reservasi.index', compact('rows', 'courts', 'stats', 'editing'));
    }

    private function listing(Request $request, ReservationService $service): array
    {
        $query = $this->filtered($request, $service);
        $stats = (clone $query)->select([])->selectRaw("COUNT(*) AS jumlah, COALESCE(SUM(CASE WHEN r.status <> 'DIBATALKAN' THEN r.tarif_snapshot*r.durasi_jam ELSE 0 END),0) AS nilai")->reorder()->first();
        $rows = $query->orderByDesc('r.tanggal')->orderByDesc('r.jam_mulai')->orderByDesc('r.id_reservasi')
            ->paginate(12)->withPath(route('reservasi.index'))->appends($request->only('q', 'tanggal', 'status'));
        if ($rows->currentPage() > $rows->lastPage()) {
            $rows = $query->paginate(12, ['*'], 'page', $rows->lastPage())->withPath(route('reservasi.index'))->appends($request->only('q', 'tanggal', 'status'));
        }
        return compact('stats', 'rows');
    }

    public function live(Request $request, ReservationService $service)
    {
        $data = $request->validate([
            'page' => 'nullable|integer|min:1|max:1000000',
            'known' => 'nullable|string|size:64',
            'slot_lapangan' => 'nullable|required_with:slot_tanggal|integer|exists:lapangan,id_lapangan',
            'slot_tanggal' => 'nullable|required_with:slot_lapangan|date_format:Y-m-d',
            'editing' => 'nullable|integer|min:1',
        ]);
        ['stats' => $stats, 'rows' => $rows] = $this->listing($request, $service);
        $html = view('reservasi.table', compact('rows'))->render();
        $count = (int) $stats->jumlah;
        $value = \App\Support\Money::rupiah((string) $stats->nilai);
        $version = hash('sha256', json_encode([$count, $value, $rows->currentPage(), $html]));
        $editing = empty($data['editing']) ? null : $service->query()->where('r.id_reservasi', $data['editing'])->first();
        $busy = null;
        if (! empty($data['slot_lapangan']) && ! empty($data['slot_tanggal'])) {
            $busy = $this->busySlots((int) $data['slot_lapangan'], $data['slot_tanggal'], $data['editing'] ?? null);
        }
        return response()->json([
            'version' => $version, 'changed' => ! hash_equals($version, $data['known'] ?? ''),
            'html' => hash_equals($version, $data['known'] ?? '') ? null : $html,
            'count' => $count, 'value' => $value, 'page' => $rows->currentPage(),
            'terisi' => $busy, 'editing_revision' => $editing ? ReservationService::revision($editing) : null,
            'server_now' => \Carbon\CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d\TH:i:s.vP'),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function store(Request $request, ReservationService $service)
    {
        $id = $service->save($request->user(), $this->validated($request));
        return redirect()->route('reservasi.index')->with('success', 'Reservasi berhasil disimpan.')->with('saved', $id);
    }

    public function update(Request $request, ReservationService $service, int $id)
    {
        $service->save($request->user(), $this->validated($request), $id);
        return redirect()->route('reservasi.index')->with('success', 'Perubahan reservasi berhasil disimpan.');
    }

    public function destroy(ReservationService $service, int $id)
    {
        $service->archive($id);
        return redirect()->route('reservasi.index')->with('success', 'Reservasi dihapus dari daftar dan slot dilepas. Catatan pembayaran tetap tersimpan.');
    }

    public function print(Request $request, ReservationService $service)
    {
        $rows = $this->filtered($request, $service)->orderBy('r.tanggal')->orderBy('r.jam_mulai')->get();
        $value = $rows->where('status', '!=', 'DIBATALKAN')->sum(fn ($r) => \App\Support\Money::cents((string) $r->tarif_snapshot) * $r->durasi_jam);
        return view('reservasi.print', ['rows' => $rows, 'value' => \App\Support\Money::decimal($value)]);
    }

    public function availability(Request $request, ReservationService $service)
    {
        $data = $request->validate(['id_lapangan' => 'required|integer|exists:lapangan,id_lapangan', 'tanggal' => 'required|date_format:Y-m-d', 'kecuali' => 'nullable|integer']);
        // GET tidak membuat baris jadwal. Slot yang belum dibuat dianggap tersedia.
        return response()->json(['terisi' => $this->busySlots((int) $data['id_lapangan'], $data['tanggal'], $data['kecuali'] ?? null), 'server_now' => \Carbon\CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d\TH:i:s.vP')])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function busySlots(int $court, string $date, ?int $except): array
    {
        return DB::table('jadwal_lapangan')->where('id_lapangan', $court)->where('tanggal', $date)
            ->where(fn ($q) => $q->where('ditutup', true)->orWhere(fn ($q) => $q->whereNotNull('id_reservasi')->when($except, fn ($q, $id) => $q->where('id_reservasi', '!=', $id))))
            ->orderBy('jam_mulai')->pluck('jam_mulai')->map(fn ($time) => substr($time, 0, 5))->all();
    }

    private function validated(Request $request): array
    {
        $request->merge(['no_whatsapp' => ReservationService::phone((string) $request->input('no_whatsapp')), 'nama' => trim((string) $request->input('nama'))]);
        return $request->validate([
            'request_key' => 'required|uuid', 'nama' => 'required|string|max:150', 'no_whatsapp' => ['required','regex:/^62[1-9][0-9]{7,12}$/D'],
            'edit_revision' => ($request->routeIs('reservasi.update') ? 'required' : 'nullable').'|string|size:64',
            'email' => 'nullable|email|max:255', 'id_lapangan' => 'required|integer|exists:lapangan,id_lapangan',
            'tanggal' => 'required|date_format:Y-m-d', 'jam_mulai' => 'required|date_format:H:i', 'durasi_jam' => 'required|integer|min:1|max:17',
            'metode' => 'required|in:TUNAI,TRANSFER,QRIS', 'status' => 'required|in:MENUNGGU_BAYAR,TERKONFIRMASI,SELESAI,DIBATALKAN', 'catatan' => 'nullable|string|max:1000',
        ]);
    }
}
