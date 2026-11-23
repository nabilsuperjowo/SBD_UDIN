<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        // Render terminates TLS before forwarding the request to this container.
        if (filter_var(getenv('RENDER') ?: false, FILTER_VALIDATE_BOOL)) {
            $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);
        }
        $middleware->alias(['kasir' => \App\Http\Middleware\EnsureCashier::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Database\UniqueConstraintViolationException $e, Request $request) {
            return back()->withErrors(['data' => 'Data berubah atau sudah digunakan. Periksa kembali formulir.'])->withInput($request->except('password'));
        });
        $exceptions->render(function (\Illuminate\Database\QueryException $e, Request $request) {
            if (app()->environment('testing')) { return null; }
            report($e);
            return response()->view('auth.unavailable', [], 503);
        });
    })->create();
