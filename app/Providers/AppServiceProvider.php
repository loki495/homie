<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\RequireAuthentication;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::addPersistentMiddleware(RequireAuthentication::class);
    }
}
