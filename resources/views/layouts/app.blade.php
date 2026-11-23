<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Reservasi Kasir') · Galaxy Futsal Centre</title>
    <script>try{document.documentElement.dataset.theme=localStorage.getItem('ssr-kasir-theme')||'dark'}catch(e){}</script>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ filemtime(public_path('assets/app.css')) }}">
    <script src="{{ asset('assets/booking-clock.js') }}?v={{ filemtime(public_path('assets/booking-clock.js')) }}" defer></script>
    <script src="{{ asset('assets/app.js') }}?v={{ filemtime(public_path('assets/app.js')) }}" defer></script>
</head>
<body>
<header class="topbar"><div class="container header-inner">
    <a class="brand" href="{{ route('reservasi.index') }}" aria-label="Galaxy Futsal, beranda"><strong>GALAXY <span>FUTSAL</span></strong><small>CENTRE · TAJUR BOGOR</small></a>
    <div class="header-actions">
        <button class="button small ghost" type="button" data-theme-toggle>Mode terang</button>
        @auth
        <a class="button small ghost" href="{{ route('reservasi.print', request()->only('q','tanggal','status')) }}">Lihat / Cetak Daftar</a>
        <form action="{{ route('logout') }}" method="post">@csrf<button class="button small ghost" type="submit">Keluar</button></form>
        @endauth
    </div>
</div></header>
<main class="container">
    @if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())
    <div class="alert error" role="alert"><strong>Data belum disimpan.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
<footer class="container footer">Galaxy Futsal Centre · Jl. Raya Tajur No.37, Tajur, Bogor Timur, Kota Bogor · Operasional 07.00–24.00 WIB</footer>
</body>
</html>
