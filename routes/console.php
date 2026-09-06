<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Demo mode only - the ->when() check makes this a no-op on every normal
// (non-demo) deployment of this same image, same convention as
// config('homie.demo_mode') everywhere else.
Schedule::command('demo:cleanup')
    ->daily()
    ->when(fn (): bool => (bool) config('homie.demo_mode'));
