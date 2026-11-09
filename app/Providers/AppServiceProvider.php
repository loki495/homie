<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\RequireAuthentication;
use Illuminate\Foundation\Vite;
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

        // A local `npm run dev` writes public/hot, but the browser tests run in their own container
        // where that dev server's localhost is unreachable, so the page arrived without any JS and
        // every Alpine store binding threw. Tests render the built manifest instead.
        if ($this->app->environment('testing')) {
            $this->app->make(Vite::class)->useHotFile(public_path('hot-testing'));
        }
    }
}
