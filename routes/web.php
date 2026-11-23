<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/masuk', 'auth.login')->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.store');
});
Route::middleware(['auth', 'kasir'])->group(function () {
    Route::post('/keluar', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [ReservationController::class, 'index'])->name('reservasi.index');
    Route::get('/reservasi/cetak', [ReservationController::class, 'print'])->name('reservasi.print');
    Route::get('/reservasi/live', [ReservationController::class, 'live'])->name('reservasi.live');
    Route::get('/jadwal/tersedia', [ReservationController::class, 'availability'])->name('availability');
    Route::post('/reservasi', [ReservationController::class, 'store'])->name('reservasi.store');
    Route::get('/reservasi/{id}/edit', [ReservationController::class, 'index'])->whereNumber('id')->name('reservasi.edit');
    Route::put('/reservasi/{id}', [ReservationController::class, 'update'])->whereNumber('id')->name('reservasi.update');
    Route::delete('/reservasi/{id}', [ReservationController::class, 'destroy'])->whereNumber('id')->name('reservasi.destroy');
});
