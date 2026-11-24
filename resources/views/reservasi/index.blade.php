@extends('layouts.app')
@section('content')
@php
    use App\Services\ReservationService;
    use App\Support\Money;
    $statuses = ReservationService::STATUSES;
    $methods = ReservationService::METHODS;
    $chosenCourt = old('id_lapangan', $editing?->id_lapangan);
    $chosenTime = old('jam_mulai', $editing ? substr($editing->jam_mulai, 0, 5) : '');
    $chosenDuration = old('durasi_jam', $editing?->durasi_jam);
    $chosenStatus = old('status', $editing?->status ?? 'MENUNGGU_BAYAR');
    $paymentPaid = $editing?->status_pembayaran === 'BERHASIL';
    if ($paymentPaid && $chosenStatus === 'MENUNGGU_BAYAR') {
        $chosenStatus = $editing->status;
    }
@endphp
<section class="hero"><p class="eyebrow">SMART SPORT RESERVATION</p><h1>PILIH JADWAL. <span>MAIN MAKSIMAL.</span></h1><p class="muted">Form reservasi 2 Lapangan Futsal & 2 Lapangan Badminton. Jam operasional 07.00–24.00 WIB, sesi per 60 menit.</p></section>

<section class="stats" aria-label="Ringkasan reservasi">
    <article class="stat"><p>Total Reservasi</p><strong id="reservation-count">{{ $stats->jumlah }}</strong></article>
    <article class="stat"><p>Total Nilai Reservasi Aktif</p><strong id="reservation-value">{{ Money::rupiah((string) $stats->nilai) }}</strong></article>
    <article class="stat"><p>Tarif Sewa per Jam</p><div class="rates">@foreach($courts->groupBy('kategori') as $category => $group)<span>{{ ucfirst(strtolower($category)) }} <b>{{ Money::rupiah((string) $group->first()->harga_per_jam) }}</b></span>@endforeach</div></article>
</section>

<section class="panel" id="form-reservasi"><div class="panel-title"><h2>{{ $editing ? 'Edit Reservasi '.$editing->kode : 'Form Reservasi Lapangan Baru' }}</h2>@if($editing)<a class="button small ghost" href="{{ route('reservasi.index') }}">Batal Edit</a>@endif</div>
<form class="panel-body" method="post" action="{{ $editing ? route('reservasi.update', $editing->id_reservasi) : route('reservasi.store') }}" id="reservation-form" data-availability-url="{{ route('availability') }}" data-editing="{{ $editing?->id_reservasi }}" data-snapshot="{{ $editing?->tarif_snapshot }}" data-original-court="{{ $editing?->id_lapangan }}" data-original-date="{{ $editing?->tanggal }}" data-original-time="{{ $editing ? substr($editing->jam_mulai,0,5) : '' }}" data-original-duration="{{ $editing?->durasi_jam }}" data-server-now="{{ \Carbon\CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d\TH:i:s.vP') }}">
@csrf
<input type="hidden" name="edit_revision" value="{{ old('edit_revision', $editing ? ReservationService::revision($editing) : '') }}">
@if($editing) @method('PUT') @endif
<input type="hidden" name="request_key" value="{{ old('request_key', $editing?->request_key ?? (string) \Illuminate\Support\Str::uuid()) }}">
<div class="alert error" id="editing-conflict" hidden role="alert"></div>
<div class="form-grid">
    <label>Nama Pemesan<input type="text" name="nama" value="{{ old('nama', $editing?->nama_snapshot) }}" placeholder="Masukkan nama pemesan / tim" required maxlength="150" autocomplete="name"></label>
    <label>No. WhatsApp<input type="tel" name="no_whatsapp" value="{{ old('no_whatsapp', $editing?->whatsapp_snapshot) }}" placeholder="081234567890" required maxlength="20" autocomplete="tel" inputmode="tel"></label>
    <label>Email (opsional)<input type="email" name="email" value="{{ old('email', $editing?->email_snapshot) }}" placeholder="nama@email.com" maxlength="255" autocomplete="email"></label>

    <label>Lapangan<select name="id_lapangan" id="court" required><option value="">-- Pilih Lapangan --</option>@foreach($courts as $court)<option value="{{ $court->id_lapangan }}" data-price="{{ $court->harga_per_jam }}" @selected((string)$chosenCourt === (string)$court->id_lapangan)>{{ $court->nama }} · {{ Money::rupiah((string)$court->harga_per_jam) }}/jam</option>@endforeach</select></label>
    <label>Tanggal Main<input type="date" name="tanggal" id="date" min="{{ $editing && $editing->tanggal < now('Asia/Jakarta')->toDateString() ? $editing->tanggal : now('Asia/Jakarta')->toDateString() }}" value="{{ old('tanggal', $editing?->tanggal) }}" required></label>
    <div class="time-grid">
        <label>Jam Mulai (WIB)<select name="jam_mulai" id="start" required><option value="">-- Jam --</option>@for($h=7;$h<24;$h++) @php $time=sprintf('%02d:00',$h); @endphp<option value="{{ $time }}" @selected($chosenTime === $time)>{{ str_replace(':', '.', $time) }}</option>@endfor</select></label>
        <label>Durasi<select name="durasi_jam" id="duration" required><option value="">-- Durasi --</option>@for($d=1;$d<=17;$d++)<option value="{{ $d }}" @selected((int)$chosenDuration === $d)>{{ $d }} jam</option>@endfor</select></label>
    </div>

    <label>Metode Pembayaran<select name="metode" required><option value="">-- Pilih Metode --</option>@foreach($methods as $key=>$label)<option value="{{ $key }}" @selected(old('metode', $editing?->metode) === $key)>{{ $label }}</option>@endforeach</select></label>
    <label>Status Reservasi<select name="status" id="status" required>@foreach($statuses as $key=>$label)<option value="{{ $key }}" @selected($chosenStatus === $key) @disabled($paymentPaid && $key === 'MENUNGGU_BAYAR')>{{ $label }}</option>@endforeach</select></label>
    <label>Catatan (opsional)<input type="text" name="catatan" value="{{ old('catatan', $editing?->catatan) }}" placeholder="Contoh: sewa bola, jumlah pemain" maxlength="1000"></label>
    <div><label>Estimasi Total Bayar</label><output id="estimate" class="estimate" aria-live="polite">Rp 0</output></div>
    <div class="form-hint"><p id="server-clock" class="muted">Waktu server: {{ now('Asia/Jakarta')->format('d/m/Y H.i.s') }} WIB</p><p id="time-hint" class="muted" role="status"></p><p id="availability-hint" class="muted" aria-live="polite">Pilih lapangan dan tanggal untuk melihat jam yang terisi.</p><p id="payment-hint" class="muted"></p></div>
</div>
<div class="form-actions"><span class="muted tiny">{{ auth()->user()->nama }} · Semua waktu dalam WIB</span><button class="button primary" type="submit" id="save-button">{{ $editing ? 'Simpan Perubahan' : 'Simpan Reservasi' }}</button></div>
</form></section>

<section class="panel list-panel" id="live-reservations" data-live-url="{{ route('reservasi.live') }}"><div class="panel-title"><h2>Daftar Reservasi Tersimpan <small class="live-status" id="live-status" role="status">Menghubungkan...</small></h2><a class="button small print-link" href="{{ route('reservasi.print', request()->only('q','tanggal','status')) }}">Lihat / Cetak Daftar</a></div>
<form class="filters" action="{{ route('reservasi.index') }}" method="get" role="search">
    <input type="search" name="q" value="{{ request('q') }}" aria-label="Cari pemesan, WhatsApp atau kode" placeholder="Cari nama / WhatsApp / kode">
    <input type="date" name="tanggal" value="{{ request('tanggal') }}" aria-label="Filter tanggal main">
    <select name="status" aria-label="Filter status"><option value="">Semua status</option>@foreach($statuses as $key=>$label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select>
    <button class="button small ghost" type="submit">Tampilkan</button>@if(request()->hasAny('q','tanggal','status'))<a href="{{ route('reservasi.index') }}" class="reset">Reset</a>@endif
</form>
<div id="reservation-live-list">@include('reservasi.table')</div>
</section>
@endsection
