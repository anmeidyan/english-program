<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-bold">Tenses Master</h1>
        <p class="text-sm text-slate-500">{{ __('16 tenses: 4 waktu (present, past, future, past future) × 4 aspek. Terbuka bertahap sesuai levelmu.') }}</p>
    </x-slot>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-6">
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ($topics as $topic)
                @php $row = $rows[$topic->id] ?? null; @endphp
                @php $isOpen = $open[$topic->id]; @endphp
                <div @class(['card p-4', 'opacity-60' => ! $isOpen])>
                    <div class="text-xs uppercase tracking-wide text-slate-500">{{ $topic->time }} · {{ $topic->aspect }}</div>
                    <h2 class="font-bold mt-1">{{ $topic->name }}</h2>
                    <p class="font-mono text-xs mt-2 text-slate-600 dark:text-slate-400">{{ $topic->formula['positive'] ?? '' }}</p>
                    <div class="mt-3 flex items-center justify-between text-xs">
                        @if ($row?->status->isPassed())
                            <span class="chip bg-emerald-100 text-emerald-800">✓ {{ __('Lulus') }}</span>
                        @elseif ($isOpen)
                            <span class="chip bg-brand-50 text-brand-700">{{ __('Terbuka') }}</span>
                        @else
                            <span class="chip bg-slate-100 dark:bg-slate-800">🔒 {{ $topic->requiredLevel?->label() ?? '' }}</span>
                        @endif
                        @if ($isOpen)<a class="text-brand-600 font-semibold" href="{{ route('tenses.show', $topic) }}">{{ __('Pelajari') }} →</a>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
