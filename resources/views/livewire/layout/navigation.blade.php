<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/');
    }
}; ?>

@php
    $links = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'label' => __('Beranda'), 'icon' => 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
        ['route' => 'learn', 'match' => 'learn*', 'label' => __('Belajar'), 'icon' => 'M4 19.5A2.5 2.5 0 016.5 17H20V3H6.5A2.5 2.5 0 004 5.5v14zM20 17v4H6.5'],
        ['route' => 'tenses.index', 'match' => 'tenses.*', 'label' => __('Tenses'), 'icon' => 'M12 8v4l3 3M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['route' => 'flashcards', 'match' => 'flashcards', 'label' => __('Flashcard'), 'icon' => 'M4 6h16v12H4zM8 6V4h12v12h-2'],
    ];
    $user = auth()->user();
@endphp

<nav x-data="{ open: false }" class="bg-white/90 dark:bg-slate-900/90 backdrop-blur border-b border-slate-200 dark:border-slate-800 sticky top-0 z-30">
    <div class="max-w-5xl mx-auto px-4 sm:px-6">
        <div class="flex justify-between h-14 items-center">
            <div class="flex items-center gap-6">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-bold">
                    <x-application-logo class="w-8 h-8" />
                    <span class="hidden sm:inline">EnglishPath</span>
                </a>
                <div class="hidden sm:flex gap-1">
                    @foreach ($links as $link)
                        <a href="{{ route($link['route']) }}"
                           @class(['px-3 py-2 rounded-lg text-sm font-medium', 'bg-brand-50 text-brand-700 dark:bg-slate-800 dark:text-white' => request()->routeIs($link['match']), 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white' => ! request()->routeIs($link['match'])])
                           @if (request()->routeIs($link['match'])) aria-current="page" @endif>{{ $link['label'] }}</a>
                    @endforeach
                    @if ($user->isStaff())
                        <a href="/admin" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300">Admin</a>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span x-data class="hidden sm:inline-flex items-center gap-1.5 text-xs" :class="$store.net.online ? 'text-emerald-600' : 'text-amber-600'" role="status">
                    <span class="w-2 h-2 rounded-full" :class="$store.net.online ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                    <span x-text="$store.net.online ? '{{ __('Online') }}' : '{{ __('Offline') }}'"></span>
                </span>
                <span class="text-sm font-semibold text-amber-600" title="{{ __('Streak harian') }}">🔥 {{ $user->streak_current }}</span>
                <span class="hidden sm:inline text-sm font-semibold text-brand-600" title="XP">⭐ {{ number_format($user->xp_total) }}</span>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="{{ __('Menu akun') }}">
                            <span class="w-7 h-7 rounded-full bg-brand-500 text-white grid place-items-center text-xs font-bold">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="px-4 py-2 text-xs text-slate-500">{{ $user->name }}</div>
                        <x-dropdown-link :href="route('profile')">{{ __('Profil') }}</x-dropdown-link>
                        <x-dropdown-link :href="route('certificates.index')">{{ __('Sertifikat') }}</x-dropdown-link>
                        <x-dropdown-link :href="route('placement')">{{ __('Placement test') }}</x-dropdown-link>
                        <x-dropdown-link :href="route('locale', app()->getLocale() === 'id' ? 'en' : 'id')">{{ app()->getLocale() === 'id' ? 'English UI' : 'Bahasa Indonesia' }}</x-dropdown-link>
                        <button type="button" x-data @click="$store.theme.toggle()" class="block w-full px-4 py-2 text-start text-sm leading-5 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">{{ __('Ganti tema terang/gelap') }}</button>
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>{{ __('Keluar') }}</x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>

    {{-- bottom tab bar on mobile --}}
    <div class="sm:hidden fixed bottom-0 inset-x-0 z-30 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 grid grid-cols-4" style="padding-bottom: env(safe-area-inset-bottom)">
        @foreach ($links as $link)
            <a href="{{ route($link['route']) }}" @class(['flex flex-col items-center py-2 text-xs gap-0.5', 'text-brand-600' => request()->routeIs($link['match']), 'text-slate-500' => ! request()->routeIs($link['match'])])>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}"/></svg>
                {{ $link['label'] }}
            </a>
        @endforeach
    </div>
</nav>
