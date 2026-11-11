{{-- install prompt (after first lesson) and "new version" toast, PRD 11.4 --}}
<div x-data x-cloak class="fixed inset-x-0 bottom-20 sm:bottom-4 z-40 flex flex-col items-center gap-2 px-4 pointer-events-none">
    <div x-show="$store.pwa.canInstall && $store.pwa.eligible" x-transition
         class="pointer-events-auto w-full max-w-md rounded-2xl bg-white dark:bg-slate-800 shadow-lg ring-1 ring-slate-200 dark:ring-slate-700 p-4 flex items-center gap-3">
        <x-application-logo class="w-10 h-10 shrink-0" />
        <div class="flex-1 text-sm">
            <p class="font-semibold">{{ __('Pasang EnglishPath') }}</p>
            <p class="text-slate-600 dark:text-slate-300">{{ __('Belajar lebih cepat dari layar utama, juga saat offline.') }}</p>
        </div>
        <button type="button" class="btn-primary text-sm" @click="$store.pwa.install()">{{ __('Pasang') }}</button>
        <button type="button" class="text-slate-500 hover:text-slate-700 dark:hover:text-slate-200" @click="$store.pwa.dismiss()" aria-label="{{ __('Tutup') }}">✕</button>
    </div>
    <div x-show="$store.pwa.updateReady" x-transition
         class="pointer-events-auto w-full max-w-md rounded-2xl bg-slate-900 text-white shadow-lg p-4 flex items-center gap-3" role="status">
        <p class="flex-1 text-sm">{{ __('Versi baru tersedia — muat ulang') }}</p>
        <button type="button" class="rounded-lg bg-white text-slate-900 px-3 py-1.5 text-sm font-semibold" @click="$store.pwa.reload()">{{ __('Muat ulang') }}</button>
    </div>
</div>
