<x-app-layout>
    @php
        $icon = function ($status) {
            return match ($status?->value) {
                'passed', 'passed_by_placement' => ['✓', 'bg-emerald-500 text-white', __('Lulus')],
                'failed' => ['!', 'bg-red-500 text-white', __('Belum lulus')],
                'pending_review' => ['⏳', 'bg-sky-500 text-white', __('Menunggu penilaian')],
                'exam_ready', 'exam_in_progress' => ['?', 'bg-amber-500 text-white', __('Siap quiz')],
                'unlocked', 'in_progress' => ['▶', 'bg-brand-600 text-white', __('Terbuka')],
                default => ['🔒', 'bg-slate-200 text-slate-500 dark:bg-slate-800', __('Terkunci')],
            };
        };
    @endphp
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold">{{ $level->name }}</h1>
                <p class="text-sm text-slate-500">±{{ $level->estimated_hours }} {{ __('jam') }} · {{ $levelRow?->status->label() ?? __('Terkunci') }}</p>
            </div>
            <div class="flex gap-1 overflow-x-auto" role="tablist" aria-label="{{ __('Level') }}">
                @foreach ($levels as $l)
                    <a href="{{ route('learn.level', $l) }}" role="tab" aria-selected="{{ $l->id === $level->id ? 'true' : 'false' }}"
                       @class(['chip text-sm px-3 py-1', 'bg-brand-600 text-white' => $l->id === $level->id, 'bg-slate-100 dark:bg-slate-800' => $l->id !== $level->id])>{{ $l->label() }}</a>
                @endforeach
            </div>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 space-y-8">
        @foreach ($level->modules as $module)
            <section aria-labelledby="module-{{ $module->id }}">
                <h2 id="module-{{ $module->id }}" class="text-sm font-bold uppercase tracking-wide text-slate-500 mb-3">{{ $module->title }}</h2>
                <div class="space-y-5">
                    @foreach ($module->units as $unit)
                        @php $unitStatus = $unitRows[$unit->id]->status ?? null; @endphp
                        <div class="card p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-bold">{{ $unit->title }}</h3>
                                    <p class="text-sm text-slate-500">{{ $unit->description }}</p>
                                </div>
                                <div x-data="offlineUnit({{ $unit->id }})" class="shrink-0 text-right">
                                    @if ($unitStatus)
                                        <button type="button" class="btn-ghost text-xs" @click="download()" :disabled="state === 'busy' || !$store.net.online">
                                            <span x-show="state === 'idle'">⬇ {{ __('Unduh untuk offline') }}</span>
                                            <span x-show="state === 'busy'" x-text="`{{ __('Mengunduh') }} ${progress}`"></span>
                                            <span x-show="state === 'done'" x-text="`✓ ${progress} {{ __('tersimpan') }}`"></span>
                                            <span x-show="state === 'error'">{{ __('Gagal, coba lagi') }}</span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <ol class="mt-4 relative border-s-2 border-slate-200 dark:border-slate-700 ms-4 space-y-3">
                                @foreach ($unit->lessons as $lesson)
                                    @php
                                        $row = $lessonRows[$lesson->id] ?? null;
                                        [$sym, $cls, $label] = $icon($row?->status);
                                        $open = ($row && $row->status->isOpen()) || $levelOpen;
                                    @endphp
                                    <li class="ms-6">
                                        <span class="absolute -start-4 w-8 h-8 rounded-full grid place-items-center text-xs font-bold {{ $cls }}" aria-hidden="true">{{ $sym }}</span>
                                        @if ($open)
                                            <a href="{{ route('lessons.show', $lesson) }}" class="flex items-center justify-between gap-2 py-1.5 hover:text-brand-600">
                                                <span><span class="font-medium">{{ $lesson->title }}</span> <span class="text-xs text-slate-500">· {{ $lesson->type->value }} · {{ $lesson->estimated_minutes }}′</span></span>
                                                <span class="text-xs text-slate-500">{{ $label }}@if($row?->best_score) · {{ (float) $row->best_score }}%@endif</span>
                                            </a>
                                        @else
                                            <div class="flex items-center justify-between gap-2 py-1.5 text-slate-400">
                                                <span>{{ $lesson->title }}</span><span class="text-xs">{{ $label }}</span>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                                @if ($unit->exam)
                                    @php [$sym, $cls, $label] = $icon($unitStatus?->value === 'unlocked' || $unitStatus?->value === 'in_progress' ? null : $unitStatus); @endphp
                                    <li class="ms-6">
                                        <span class="absolute -start-4 w-8 h-8 rounded-full grid place-items-center text-xs font-bold {{ $cls }}" aria-hidden="true">🏁</span>
                                        <a href="{{ route('exams.show', $unit->exam) }}" class="flex items-center justify-between gap-2 py-1.5 font-semibold hover:text-brand-600">
                                            <span>{{ __('Unit Exam') }}</span>
                                            <span class="text-xs font-normal text-slate-500"
                                                  @if (($unitRows[$unit->id] ?? null)?->cooldown_until?->isFuture())
                                                      x-data="{ until: @js($unitRows[$unit->id]->cooldown_until->toIso8601String()), left: '' }"
                                                      x-init="setInterval(() => { const s = Math.max(0, Math.round((new Date(until) - Date.now()) / 1000)); left = `${Math.floor(s / 3600)}j ${Math.floor(s % 3600 / 60)}m` }, 1000)"
                                                      x-text="`{{ __('Tunggu') }} ${left}`"
                                                  @endif>{{ $unitStatus?->label() ?? __('Terkunci') }}</span>
                                        </a>
                                    </li>
                                @endif
                            </ol>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if ($level->exam)
            <a href="{{ route('exams.show', $level->exam) }}" class="card p-5 flex items-center justify-between hover:ring-brand-400">
                <div>
                    <h2 class="font-bold">🎓 {{ __('Level Exam') }} {{ $level->label() }}</h2>
                    <p class="text-sm text-slate-500">{{ __('Lulus untuk membuka level berikutnya dan mendapatkan sertifikat.') }}</p>
                </div>
                <span class="text-sm text-slate-500">{{ $levelRow?->status->label() ?? __('Terkunci') }}</span>
            </a>
        @endif

        @if ($level->modules->isEmpty())
            <p class="text-center text-slate-500">{{ __('Materi level ini sedang disiapkan.') }}</p>
        @endif
    </div>
</x-app-layout>
