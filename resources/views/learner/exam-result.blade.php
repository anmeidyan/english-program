<x-app-layout>
    @php
        $pending = $attempt->status->value === 'pending_review';
        $passed = $attempt->passed === true;
    @endphp
    <x-slot name="header">
        <p class="text-xs text-slate-500">{{ $exam->type->label() }} · {{ __('percobaan') }} #{{ $attempt->attempt_number }}</p>
        <h1 class="text-xl font-bold">{{ $exam->title }}</h1>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 space-y-5">
        <section @class(['rounded-2xl p-6 text-center', 'bg-emerald-50 dark:bg-emerald-950' => $passed, 'bg-sky-50 dark:bg-sky-950' => $pending, 'bg-red-50 dark:bg-red-950' => ! $passed && ! $pending]) role="status">
            <div class="text-5xl" aria-hidden="true">{{ $passed ? '🎉' : ($pending ? '⏳' : '💪') }}</div>
            <h2 class="text-2xl font-bold mt-2">
                {{ $passed ? __('Lulus!') : ($pending ? __('Menunggu penilaian') : __('Belum lulus')) }}
            </h2>
            <p class="text-4xl font-extrabold mt-2">{{ $attempt->score !== null ? rtrim(rtrim((string) $attempt->score, '0'), '.') : '—' }}<span class="text-lg">%</span></p>
            <p class="text-sm text-slate-600 dark:text-slate-300 mt-1">{{ __('Nilai lulus :p%', ['p' => $exam->passing_score]) }} @if($exam->min_skill_score) · {{ __('min. :m% tiap skill', ['m' => $exam->min_skill_score]) }} @endif</p>
            @if ($pending)
                <p class="text-sm mt-3">{{ __('Jawaban speaking/writing sedang dinilai reviewer (maks. 48 jam). Konten berikutnya terbuka setelah penilaian selesai; kamu akan menerima notifikasi.') }}</p>
            @endif
        </section>

        @if (! empty($attempt->skill_scores))
            <section class="card p-5">
                <h2 class="font-bold mb-3">{{ __('Skor per skill') }}</h2>
                <div class="space-y-3">
                    @foreach ($attempt->skill_scores as $skill => $score)
                        @php $ok = $score >= max($exam->min_skill_score, 0) && (! $exam->min_skill_score || $score >= $exam->min_skill_score); @endphp
                        <div>
                            <div class="flex justify-between text-sm"><span class="capitalize">{{ $skill }}</span><span @class(['font-semibold', 'text-red-600' => $exam->min_skill_score && $score < $exam->min_skill_score])>{{ $score }}%</span></div>
                            <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800 mt-1" role="progressbar" aria-valuenow="{{ $score }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $skill }}">
                                <div @class(['h-2 rounded-full', 'bg-brand-500' => $ok, 'bg-red-500' => ! $ok]) style="width: {{ min(100, $score) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! $passed && ! $pending)
            <section class="card p-5 space-y-2 text-sm">
                <h2 class="font-bold text-base">{{ __('Langkah berikutnya') }}</h2>
                @if ($row?->cooldown_until && $row->cooldown_until->isFuture())
                    <p>{{ __('Kamu bisa mencoba lagi mulai :t.', ['t' => $row->cooldown_until->timezone(auth()->user()->timezone)->translatedFormat('d M Y H:i')]) }}</p>
                @else
                    <p>{{ __('Kamu bisa langsung mencoba lagi dengan soal baru.') }}</p>
                @endif
                @if ($remedials->isNotEmpty())
                    <p>{{ __('Selesaikan dulu lesson wajib review berikut (quiz ≥ 80%):') }}</p>
                    <ul class="list-disc pl-5">
                        @foreach ($remedials as $task)<li><a class="text-brand-600 underline" href="{{ route('lessons.show', $task->lesson) }}">{{ $task->lesson->title }}</a></li>@endforeach
                    </ul>
                @endif
            </section>
        @endif

        @if ($reveal && ! empty($review))
            <section class="card p-5 space-y-4">
                <h2 class="font-bold">{{ __('Pembahasan soal yang salah') }}</h2>
                @foreach ($review as $item)
                    <div class="border-t border-slate-100 dark:border-slate-800 pt-3 text-sm space-y-1">
                        <p class="font-medium">{{ $item['prompt'] }}</p>
                        <p class="text-red-700 dark:text-red-300">{{ __('Jawabanmu') }}: {{ $item['your_answer'] }}</p>
                        <p class="text-emerald-700 dark:text-emerald-300">{{ __('Jawaban benar') }}: {{ $item['correct'] }}</p>
                        @if ($item['explanation'])<p class="text-slate-600 dark:text-slate-400">{{ $item['explanation'] }}</p>@endif
                    </div>
                @endforeach
            </section>
        @elseif (! $reveal && ! $passed && ! $pending)
            <p class="text-xs text-slate-500">{{ __('Untuk Unit/Level Exam hanya skor per topik yang ditampilkan agar bank soal tetap terjaga.') }}</p>
        @endif

        @php $subs = $attempt->answers->pluck('submission')->filter(); @endphp
        @if ($subs->isNotEmpty())
            <section class="card p-5 space-y-3">
                <h2 class="font-bold">{{ __('Speaking & writing') }}</h2>
                @foreach ($subs as $sub)
                    <div class="text-sm border-t border-slate-100 dark:border-slate-800 pt-3">
                        <p class="font-medium capitalize">{{ $sub->type }} — {{ $sub->status->value === 'reviewed' ? __('sudah dinilai') : __('menunggu penilaian') }}</p>
                        @if ($sub->final_score)
                            <p class="text-slate-600 dark:text-slate-300">@foreach ($sub->final_score as $k => $v){{ str_replace('_', ' ', $k) }}: {{ $v }}/9 @if (! $loop->last) · @endif @endforeach</p>
                        @endif
                        @if ($sub->feedback)<p class="mt-1">{{ $sub->feedback }}</p>@endif
                        @foreach ($sub->inline_feedback ?? [] as $note)
                            <p class="mt-1"><mark class="bg-amber-100 dark:bg-amber-900 rounded px-1">{{ $note['quote'] ?? '' }}</mark> — {{ $note['comment'] ?? '' }}</p>
                        @endforeach
                    </div>
                @endforeach
            </section>
        @endif

        <div class="flex flex-wrap gap-2">
            <a href="{{ $nextUrl }}" class="btn-primary">{{ __('Lanjutkan belajar') }} →</a>
            <a href="{{ route('exams.show', $exam) }}" class="btn-secondary">{{ __('Halaman ujian') }}</a>
        </div>
    </div>
</x-app-layout>
