<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /** One-click sign-in for the demo accounts listed below (only when enabled in config). */
    public function loginAs(string $email): void
    {
        $demo = config('englishpath.demo_logins');
        abort_unless($demo['enabled'] && in_array($email, array_column($demo['accounts'], 'email'), true), 403);

        $this->form->email = $email;
        $this->form->password = $demo['password'];
        $this->login();
    }
}; ?>

<div>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if (config('englishpath.demo_logins.enabled'))
        <section class="mb-5 rounded-xl bg-brand-50 dark:bg-slate-800 p-4" aria-labelledby="demo-title">
            <h2 id="demo-title" class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Akses demo') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Password semua akun:') }} <code class="font-mono">{{ config('englishpath.demo_logins.password') }}</code> · {{ __('klik untuk langsung masuk') }}</p>
            <ul class="mt-3 grid gap-2">
                @foreach (config('englishpath.demo_logins.accounts') as $account)
                    <li>
                        <button type="button" wire:click="loginAs('{{ $account['email'] }}')" wire:loading.attr="disabled"
                                class="w-full flex items-center justify-between gap-3 rounded-lg bg-white dark:bg-slate-900 ring-1 ring-slate-200 dark:ring-slate-700 px-3 py-2 text-left hover:ring-brand-400">
                            <span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">{{ $account['role'] }}</span>
                                <span class="block text-xs font-mono text-slate-500 dark:text-slate-400">{{ $account['email'] }}</span>
                            </span>
                            <span class="text-xs font-semibold text-brand-600 dark:text-brand-300">{{ __('Masuk') }} →</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <form wire:submit="login">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</div>
