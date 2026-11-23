<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureCashier
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->status_aktif || $request->user()?->peran !== 'KASIR') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['email' => 'Gunakan akun kasir yang aktif.']);
        }
        return $next($request);
    }
}
