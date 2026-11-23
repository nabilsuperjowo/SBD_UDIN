@extends('layouts.app')
@section('title', 'Masuk Kasir')
@section('content')
<section class="login-intro"><p class="eyebrow">SMART SPORT RESERVATION</p><h1>SIAPKAN JADWAL.<br><span>LAYANI TIMMU.</span></h1><p class="muted">Masuk untuk mencatat reservasi Galaxy Futsal & Badminton Center.</p></section>
<section class="panel login-panel"><div class="panel-title"><h2>Masuk Kasir</h2></div>
<form method="post" action="{{ route('login.store') }}" class="panel-body stack">@csrf
<label>Email<input type="email" name="email" value="{{ old('email') }}" placeholder="nama@contoh.com" required autocomplete="username" autofocus></label>
<label>Kata sandi<input type="password" name="password" required autocomplete="current-password" placeholder="Masukkan kata sandi"></label>
<button class="button primary" type="submit">Masuk ke Reservasi</button>
</form></section>
@endsection
