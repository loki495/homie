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

        <form wire:submit="login" class="space-y-4">
            <flux:input wire:model="email" type="email" label="Email" autofocus autocomplete="username" />
            <flux:input wire:model="password" type="password" label="Password" autocomplete="current-password" />
            <flux:checkbox wire:model="remember" label="Remember me" />

            <flux:button type="submit" variant="primary" class="w-full">Log in</flux:button>
        </form>
    </div>
</div>
