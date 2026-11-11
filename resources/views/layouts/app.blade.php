<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        @stack('head')
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:bg-white focus:px-3 focus:py-2 focus:rounded">{{ __('Lewati ke konten') }}</a>
        <div class="min-h-screen">
            <livewire:layout.navigation />

            <div x-data x-cloak x-show="!$store.net.online" class="bg-amber-500 text-amber-950 text-sm text-center py-1.5 px-4" role="status">
                {{ __('Kamu sedang offline. Lesson yang sudah diunduh tetap bisa dipelajari; ujian membutuhkan koneksi.') }}
            </div>

            @if (session('error'))
                <div class="max-w-5xl mx-auto mt-4 px-4"><div class="rounded-xl bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200 px-4 py-3 text-sm" role="alert">{{ session('error') }}</div></div>
            @endif
            @if (session('status'))
                <div class="max-w-5xl mx-auto mt-4 px-4"><div class="rounded-xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200 px-4 py-3 text-sm" role="status">{{ session('status') }}</div></div>
            @endif

            @if (isset($header))
                <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
                    <div class="max-w-5xl mx-auto py-5 px-4 sm:px-6">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <main id="main" class="pb-24 sm:pb-10">
                {{ $slot }}
            </main>
        </div>

        @include('partials.pwa-prompts')
        @stack('scripts')
    </body>
</html>
