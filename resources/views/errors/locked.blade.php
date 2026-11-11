<x-app-layout>
    <div class="max-w-xl mx-auto px-4 py-16 text-center space-y-4">
        <div class="text-5xl" aria-hidden="true">🔒</div>
        <h1 class="text-2xl font-bold">{{ __('Belum bisa dibuka') }}</h1>
        <p class="text-slate-600 dark:text-slate-300">{{ $decision->message }}</p>
        @if ($decision->until)
            <p class="text-sm">{{ __('Tersedia lagi: :t', ['t' => $decision->until->timezone(auth()->user()->timezone)->translatedFormat('d M Y H:i')]) }}</p>
        @endif
        @if ($decision->redirectUrl)
            <a href="{{ $decision->redirectUrl }}" class="btn-primary">{{ __('Ke materi yang seharusnya') }} →</a>
        @endif
    </div>
</x-app-layout>
