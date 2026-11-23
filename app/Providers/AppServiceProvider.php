<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Console\Events\CommandStarting::class, function ($event) {
            if (str_starts_with((string) $event->command, 'migrate')) {
                throw new \RuntimeException('Gunakan php artisan ssr:install --demo. Migrasi standar menambah tabel migrations sehingga jumlahnya bukan tujuh.');
            }
        });
    }
}
