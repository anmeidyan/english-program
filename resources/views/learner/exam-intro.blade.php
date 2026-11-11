<x-app-layout>
    @php
        $item = $exam->examable;
        $skills = $exam->rules->groupBy(fn ($r) => $r->skill?->value ?? 'mixed')->map->sum('count');
    @endphp
    <x-slot name="header">
        <p class="text-xs text-slate-500">{{ $exam->type->label() }}</p>
        <h1 class="text-xl font-bold">{{ $exam->title }}</h1>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 space-y-5">
        <section class="card p-5 grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
            <div><div class="text-2xl font-bold">{{ $exam->question_count }}</div><div class="text-xs text-slate-500">{{ __('soal') }}</div></div>
            <div><div class="text-2xl font-bold">{{ $exam->duration_minutes ? $exam->duration_minutes.'′' : '∞' }}</div><div class="text-xs text-slate-500">{{ __('durasi') }}</div></div>
            <div><div class="text-2xl font-bold">{{ $exam->passing_score }}%</div><div class="text-xs text-slate-500">{{ __('nilai lulus') }}</div></div>
            <div><div class="text-2xl font-bold">{{ $exam->min_skill_score ? $exam->min_skill_score.'%' : '—' }}</div><div class="text-xs text-slate-500">{{ __('min. tiap skill') }}</div></div>
        </section>

        <section class="card p-5 text-sm space-y-2">
            <h2 class="font-bold text-base">{{ __('Aturan') }}</h2>
            <ul class="list-disc pl-5 space-y-1 text-slate-600 dark:text-slate-300">
                <li>{{ __('Skor = poin benar / total poin × 100.') }} @if($exam->min_skill_score) {{ __('Setiap skill harus mencapai minimal :min%.', ['min' => $exam->min_skill_score]) }} @endif</li>
                @if ($exam->duration_minutes)<li>{{ __('Timer berjalan di server. Soal yang belum dijawab saat waktu habis bernilai 0.') }}</li>@endif
                <li>{{ __('Jawaban tersimpan otomatis setiap kali berubah. Jika tab tertutup, kamu bisa melanjutkan selama waktu belum habis.') }}</li>
                <li>{{ __('Audio listening dapat diputar maksimal :n kali.', ['n' => $exam->max_audio_plays]) }}</li>
                <li>{{ __('Pindah tab dan copy/paste dicatat.') }}</li>
                @if ($exam->type->value !== 'lesson_quiz')<li>{{ __('Jika gagal: masa tunggu bertahap dan lesson "wajib review" harus diselesaikan dulu.') }}</li>@endif
                <li>{{ __('Ujian tidak tersedia offline.') }}</li>
            </ul>
            @if ($skills->count() > 1)
                <div class="flex flex-wrap gap-2 pt-2">
                    @foreach ($skills as $skill => $count)<span class="chip bg-slate-100 dark:bg-slate-800">{{ ucfirst($skill) }} · {{ $count }}</span>@endforeach
                </div>
            @endif
        </section>

        @if ($remedials->isNotEmpty())
            <section class="card p-5 space-y-2">
                <h2 class="font-bold">{{ __('Wajib review sebelum mengulang') }}</h2>
                <ul class="space-y-1 text-sm">
                    @foreach ($remedials as $task)
                        <li><a class="text-brand-600 underline" href="{{ route('lessons.show', $task->lesson) }}">{{ $task->lesson->title }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="card p-5" x-data="{
                until: @js($decision->until?->toIso8601String()),
                left: 0, starting: false, error: null,
                tick() { if (!this.until) return; this.left = Math.max(0, Math.round((new Date(this.until) - Date.now()) / 1000)); if (this.left === 0) this.until = null; },
                get clock() { const h = Math.floor(this.left / 3600), m = Math.floor(this.left % 3600 / 60), s = this.left % 60; return `${h ? h + ':' : ''}${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`; },
                async start() {
                    this.starting = true; this.error = null;
                    try { const { data } = await axios.post(@js(route('api.attempts.start', $exam))); window.location.href = data.runner_url; }
                    catch (e) { this.error = e?.response?.data?.message || '{{ __('Ujian belum bisa dimulai.') }}'; if (e?.response?.data?.until) { this.until = e.response.data.until; } this.starting = false; }
                } }" x-init="tick(); setInterval(() => tick(), 1000)">
            @if ($active)
                <p class="text-sm mb-3">{{ __('Kamu punya ujian yang sedang berjalan.') }}</p>
                <a href="{{ route('exams.run', $active) }}" class="btn-primary w-full sm:w-auto">{{ __('Lanjutkan ujian') }}</a>
            @elseif ($decision->allowed)
                @unless (auth()->user()->hasVerifiedEmail())
                    <p class="text-sm text-amber-700 mb-3">{{ __('Verifikasi email kamu sebelum ujian pertama.') }} <a class="underline" href="{{ route('verification.notice') }}">{{ __('Kirim ulang verifikasi') }}</a></p>
                @endunless
                <button type="button" class="btn-primary w-full sm:w-auto" @click="start()" :disabled="starting || !!until || !$store.net.online">
                    <span x-show="!until">{{ __('Mulai ujian') }}</span>
                    <span x-show="until" x-text="`{{ __('Tersedia dalam') }} ${clock}`"></span>
                </button>
                <p class="text-sm text-amber-700 mt-2" x-show="!$store.net.online">{{ __('Kamu sedang offline — ujian membutuhkan koneksi.') }}</p>
            @else
                <p class="text-sm mb-3" role="status">{{ $decision->message }}</p>
                @if ($decision->reason === 'cooldown')
                    <button type="button" class="btn-primary" disabled x-text="until ? `{{ __('Tersedia dalam') }} ${clock}` : '{{ __('Muat ulang halaman') }}'"></button>
                    <button type="button" class="btn-ghost" x-show="!until" @click="window.location.reload()">↻</button>
                @elseif ($decision->redirectUrl)
                    <a class="btn-secondary" href="{{ $decision->redirectUrl }}">{{ __('Ke materi berikutnya') }}</a>
                @endif
            @endif
            <p class="text-sm text-red-600 mt-2" x-show="error" x-text="error" role="alert"></p>
        </section>

        @if ($history->isNotEmpty())
            <section class="card p-5">
                <h2 class="font-bold mb-2">{{ __('Riwayat percobaan') }}</h2>
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-slate-500"><th class="py-1">#</th><th>{{ __('Tanggal') }}</th><th>{{ __('Skor') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($history as $h)
                            <tr class="border-t border-slate-100 dark:border-slate-800">
                                <td class="py-1.5">{{ $h->attempt_number }}</td>
                                <td>{{ $h->started_at->timezone(auth()->user()->timezone)->format('d M H:i') }}</td>
                                <td>{{ $h->score ?? '—' }}</td>
                                <td>{{ str_replace('_', ' ', $h->status->value) }}</td>
                                <td class="text-right">@if(! $h->isActive())<a class="text-brand-600 underline" href="{{ route('exams.result', $h) }}">{{ __('Hasil') }}</a>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    </div>
</x-app-layout>
