<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Koneksi Belum Tersedia · Galaxy</title>
    <script>try{document.documentElement.dataset.theme=localStorage.getItem('ssr-kasir-theme')==='light'?'light':'dark'}catch(e){}</script>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ filemtime(public_path('assets/app.css')) }}">
</head>
<body>
<main class="container"><section class="panel unavailable" role="alert">
    <div class="unavailable-symbol">@include('partials.result-symbol', ['state' => 'error'])</div>
    <p class="eyebrow">GALAXY FUTSAL CENTRE</p>
    <h1>Data belum tersedia.</h1>
    <p class="muted">Koneksi database belum siap. Hubungi penanggung jawab aplikasi untuk menyiapkannya, lalu muat ulang halaman.</p>
    <a class="button primary" href="{{ url()->current() }}">Coba Lagi</a>
</section></main>
</body>
</html>
