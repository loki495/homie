<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        // Prefills the shared, publicly-known demo credentials - a demo
        // visitor's goal is to see the dashboard, not to go find the
        // credentials first. Never happens outside demo mode: a real
        // deployment's admin credentials are never known to this code.
        if (config('homie.demo_mode')) {
            $this->email = (string) config('homie.demo_admin_email');
            $this->password = (string) config('homie.demo_admin_password');
        }
    }

    public function login(): void
    {
        $this->validate();

        $throttleKey = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'Those credentials don\'t match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $this->redirect('/', navigate: false);
    }
};
?>

<div class="flex min-h-screen items-center justify-center bg-slate-100 px-4 dark:bg-slate-900">
    <div class="w-full max-w-sm rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-800">
        <h1 class="mb-6 text-center text-lg font-semibold text-slate-800 dark:text-slate-100">Homie</h1>

        @if (config('homie.demo_mode'))
            <div class="mb-4 rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
                This is a live demo — credentials are pre-filled below, just click Log in.
            </div>
        @endif

        <form wire:submit="login" class="space-y-4">
            <flux:input wire:model="email" type="email" label="Email" autofocus autocomplete="username" />
            <flux:input wire:model="password" type="password" label="Password" autocomplete="current-password" />
            <flux:checkbox wire:model="remember" label="Remember me" />

            <flux:button type="submit" variant="primary" class="w-full">Log in</flux:button>
        </form>
    </div>
</div>
