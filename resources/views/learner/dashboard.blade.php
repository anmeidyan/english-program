<x-app-layout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-6 space-y-6">
        <div>
            <p class="text-sm text-slate-500">{{ __('Halo, :name!', ['name' => $user->name]) }}</p>
            <h1 class="text-2xl font-bold">{{ $level ? $level->name : __('Selamat datang di EnglishPath') }}</h1>
        </div>

        <div class="grid lg:grid-cols-3 gap-5">
            {{-- continue learning --}}
            <section class="lg:col-span-2 rounded-2xl bg-gradient-to-br from-brand-600 to-brand-800 dark:from-brand-800 dark:to-brand-900 text-white p-6 shadow-sm" aria-labelledby="next-title">
                <p class="text-sm text-brand-100" id="next-title">{{ __('Lanjutkan belajar') }}</p>
                @php $m = $next['model']; @endphp
                <h2 class="text-xl font-bold mt-1">
                    @switch($next['kind'])
                        @case('lesson') {{ $m->title }} @break
                        @case('quiz') {{ __('Quiz') }}: {{ $m->title }} @break
                        @case('unit_exam') {{ __('Unit Exam') }}: {{ $m->title }} @break
                        @case('level_exam') {{ __('Level Exam') }} {{ $m->label() }} @break
                        @case('done') {{ __('Level selesai! 🎉') }} @break
                        @default {{ __('Belum ada materi yang dipublikasikan') }}
                    @endswitch
                </h2>
                @if ($next['status'])<p class="text-sm text-brand-100 mt-1">{{ $next['status']->label() }}</p>@endif
                <a href="{{ $next['url'] }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-white text-brand-700 px-5 py-2.5 font-semibold hover:bg-brand-50 dark:bg-slate-900/60 dark:text-slate-100 dark:hover:bg-slate-900">
                    {{ $next['kind'] === 'lesson' ? __('Mulai lesson') : __('Buka') }} →
                </a>
                @if ($levelProgress)
                    <div class="mt-6">
                        <div class="flex justify-between text-sm"><span>{{ __('Progres :level', ['level' => $level->label()]) }}</span><span>{{ $levelProgress['percent'] }}% · ±{{ $levelProgress['hours_left'] }} {{ __('jam tersisa') }}</span></div>
                        <div class="h-2 rounded-full bg-white/20 mt-2" role="progressbar" aria-valuenow="{{ $levelProgress['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Progres level') }}">
                            <div class="h-2 rounded-full bg-white" style="width: {{ $levelProgress['percent'] }}%"></div>
                        </div>
                    </div>
                @endif
            </section>

            {{-- stats --}}
            <section class="grid grid-cols-2 gap-3 content-start" aria-label="{{ __('Statistik') }}">
                <div class="card p-4"><div class="text-2xl font-bold">🔥 {{ $user->streak_current }}</div><div class="text-xs text-slate-500">{{ __('hari beruntun') }} · {{ __('rekor') }} {{ $user->streak_longest }}</div></div>
                <div class="card p-4"><div class="text-2xl font-bold">⭐ {{ number_format($user->xp_total) }}</div><div class="text-xs text-slate-500">XP</div></div>
                <a href="{{ route('flashcards') }}" class="card p-4 hover:ring-brand-400"><div class="text-2xl font-bold">🃏 {{ $dueCards }}</div><div class="text-xs text-slate-500">{{ __('kartu jatuh tempo') }}</div></a>
                <div class="card p-4"><div class="text-2xl font-bold">🎯 {{ $user->daily_goal_minutes }}′</div><div class="text-xs text-slate-500">{{ __('target harian') }}</div></div>
            </section>
        </div>

        <div class="grid lg:grid-cols-3 gap-5">
            <section class="card p-5" aria-labelledby="radar-title">
                <h2 id="radar-title" class="font-bold">{{ __('Skill kamu') }}</h2>
                <p class="text-xs text-slate-500 mb-2">{{ __('Rata-rata 5 ujian terakhir') }}</p>
                <x-radar-chart :scores="$radar" />
                @php $weak = collect($radar)->filter(fn ($v) => $v !== null)->sort()->keys()->first(); @endphp
                @if ($weak)<p class="text-sm text-center mt-2">{{ __('Paling perlu dilatih:') }} <strong class="capitalize">{{ $weak }}</strong></p>@endif
            </section>

            <section class="card p-5 lg:col-span-2" aria-labelledby="remedial-title">
                <h2 id="remedial-title" class="font-bold mb-3">{{ __('Wajib review') }}</h2>
                @forelse ($remedials as $task)
                    <a href="{{ route('lessons.show', $task->lesson) }}" class="flex items-center justify-between py-2 border-t first:border-0 border-slate-100 dark:border-slate-800 hover:text-brand-600">
                        <span>{{ $task->lesson->title }}</span><span class="text-xs text-slate-500">{{ $task->attempt->exam->title }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">{{ __('Tidak ada — pertahankan!') }}</p>
                @endforelse

                <h2 class="font-bold mt-6 mb-3">{{ __('Riwayat ujian') }}</h2>
                @if ($history->isEmpty())
                    <p class="text-sm text-slate-500">{{ __('Belum ada ujian.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="text-left text-slate-500"><th class="py-1">{{ __('Tanggal') }}</th><th>{{ __('Ujian') }}</th><th>{{ __('Skor') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($history as $h)
                                    <tr class="border-t border-slate-100 dark:border-slate-800">
                                        <td class="py-1.5 whitespace-nowrap">{{ $h->submitted_at?->timezone($user->timezone)->format('d M') }}</td>
                                        <td class="max-w-48 truncate">{{ $h->exam->title }}</td>
                                        <td>{{ $h->score }}</td>
                                        <td><span @class(['chip', 'bg-emerald-100 text-emerald-800' => $h->passed, 'bg-red-100 text-red-800' => $h->passed === false, 'bg-sky-100 text-sky-800' => $h->passed === null])>{{ str_replace('_', ' ', $h->status->value) }}</span></td>
                                        <td class="text-right"><a class="text-brand-600 underline" href="{{ route('exams.result', $h) }}">{{ __('Lihat') }}</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        @if ($badges->isNotEmpty())
            <section class="card p-5">
                <h2 class="font-bold mb-3">{{ __('Badge') }}</h2>
                <div class="flex flex-wrap gap-3">
                    @foreach ($badges as $ub)
                        <div class="flex items-center gap-2 chip bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-200 text-sm px-3 py-1.5" title="{{ $ub->badge->description }}">{{ $ub->badge->icon }} {{ $ub->badge->name }}</div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
