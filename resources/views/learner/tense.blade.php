<x-app-layout>
    @php $c = $topic->content ?? []; @endphp
    <x-slot name="header">
        <a href="{{ route('tenses.index') }}" class="text-xs text-slate-500 hover:underline">← Tenses Master</a>
        <h1 class="text-xl font-bold">{{ $topic->name }}</h1>
    </x-slot>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 space-y-4">
        <x-lesson.timeline :label="$c['timeline_label'] ?? $topic->name" :position="$c['timeline'] ?? 'now'" />
        <x-lesson.block :block="['type' => 'tense_formula', 'data' => $topic->formula]" />
        @if (! empty($c['function']))
            <section class="card p-5"><h2 class="font-bold mb-2">{{ __('Fungsi') }}</h2><ul class="list-disc pl-5 text-sm space-y-1">@foreach ($c['function'] as $f)<li>{{ $f }}</li>@endforeach</ul></section>
        @endif
        @if (! empty($c['signals']))
            <section class="card p-5"><h2 class="font-bold mb-2">Time signal</h2><div class="flex flex-wrap gap-2">@foreach ($c['signals'] as $s)<span class="chip bg-slate-100 dark:bg-slate-800">{{ $s }}</span>@endforeach</div></section>
        @endif
        @if (! empty($c['examples']))
            <x-lesson.block :block="['type' => 'example_list', 'data' => ['title' => __('Contoh'), 'items' => array_map(fn ($e) => ['en' => $e[0], 'id' => $e[1] ?? null], $c['examples'])]]" />
        @endif
        @if (! empty($c['compare']))
            <x-lesson.block :block="['type' => 'tip', 'data' => ['text' => $c['compare']]]" />
        @endif
        @if (! empty($c['mistake']))
            <x-lesson.block :block="['type' => 'common_mistake', 'data' => $c['mistake']]" />
        @endif
        @if ($topic->exam)
            <a href="{{ route('exams.show', $topic->exam) }}" class="card p-5 flex items-center justify-between hover:ring-brand-400">
                <span class="font-bold">{{ __('Ujian mini (Tenses Checkpoint)') }}</span>
                <span class="text-sm text-slate-500">{{ $row?->status->label() ?? __('Siap ujian') }} →</span>
            </a>
        @endif
    </div>
</x-app-layout>
