<x-app-layout>
    @php
        $done = in_array($status->value, ['exam_ready', 'exam_in_progress', 'failed', 'passed', 'passed_by_placement', 'pending_review'], true);
        $level = $lesson->unit->module->level;
        $index = $siblings->search(fn ($l) => $l->id === $lesson->id);
    @endphp
    <x-slot name="header">
        <nav class="text-xs text-slate-500 mb-1" aria-label="Breadcrumb">
            <a href="{{ route('learn.level', $level) }}" class="hover:underline">{{ $level->label() }}</a> ›
            <span>{{ $lesson->unit->title }}</span> › {{ __('Lesson') }} {{ $index + 1 }}/{{ $siblings->count() }}
        </nav>
        <div class="flex flex-wrap items-center gap-2 justify-between">
            <h1 class="text-xl font-bold">{{ $lesson->title }}</h1>
            <div class="flex items-center gap-2 text-xs">
                <span class="chip bg-brand-50 text-brand-700 dark:bg-slate-800 dark:text-brand-300">{{ ucfirst($lesson->type->value) }}</span>
                <span class="chip bg-slate-100 dark:bg-slate-800">⏱ {{ $lesson->estimated_minutes }} {{ __('menit') }}</span>
                @if ($preview)<span class="chip bg-amber-100 text-amber-800">{{ __('Mode preview') }}</span>@endif
            </div>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 space-y-5"
         x-data="lessonPlayer({
            slug: @js($lesson->slug),
            questions: @js($questions),
            practiceUrl: @js(route('api.lessons.practice', $lesson)),
            completeUrl: @js(route('api.lessons.complete', $lesson)),
            completed: @js($done),
            quizUrl: @js($lesson->exam && $done ? route('exams.show', $lesson->exam) : null),
         })">

        @if ($remedial)
            <div class="rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 p-4 text-sm" role="alert">
                <strong>{{ __('Wajib review.') }}</strong> {{ __('Pelajari ulang lesson ini lalu lulus quiz-nya lagi (≥ 80%) sebelum mengulang ujian.') }}
            </div>
        @endif

        {{-- content blocks --}}
        <section class="space-y-4" aria-label="{{ __('Materi') }}">
            @foreach ($lesson->content ?? [] as $block)
                <x-lesson.block :block="$block" />
            @endforeach
        </section>

        {{-- vocabulary cards --}}
        @if ($lesson->vocabularies->isNotEmpty())
            <section class="space-y-3" aria-labelledby="vocab-title">
                <h2 id="vocab-title" class="text-lg font-bold">{{ __('Kartu kata') }}</h2>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach ($lesson->vocabularies as $word)
                        <div class="card p-4">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <span class="font-bold text-lg">{{ $word->word }}</span>
                                    <span class="text-xs text-slate-500">{{ $word->part_of_speech }}</span>
                                </div>
                                <div class="flex gap-1">
                                    <button type="button" class="chip bg-slate-100 dark:bg-slate-800" @click="EnglishPath.speak(@js($word->word), { lang: 'en-US', rate: 0.9 })" aria-label="{{ __('Dengarkan (US)') }}">🔊 US</button>
                                    <button type="button" class="chip bg-slate-100 dark:bg-slate-800" @click="EnglishPath.speak(@js($word->word), { lang: 'en-GB', rate: 0.9 })" aria-label="{{ __('Dengarkan (UK)') }}">🔊 UK</button>
                                </div>
                            </div>
                            <div class="text-xs text-slate-500 mt-1">US {{ $word->ipa_us }} · UK {{ $word->ipa_uk }}</div>
                            <div class="mt-2 font-medium">{{ $word->meaning_id }}</div>
                            <div class="text-sm text-slate-600 dark:text-slate-400">{{ $word->definition_en }}</div>
                            <div class="text-sm italic mt-1">“{{ $word->example }}”</div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- practice --}}
        @if ($questions->isNotEmpty())
            <section class="space-y-4" aria-labelledby="practice-title">
                <div class="flex items-center justify-between">
                    <h2 id="practice-title" class="text-lg font-bold">{{ __('Latihan') }}</h2>
                    <span class="text-sm text-slate-500" x-text="`${correctCount}/${questions.length} {{ __('benar') }}`"></span>
                </div>
                <p class="text-sm text-slate-500">{{ __('Latihan boleh diulang tanpa batas. Jawaban langsung diperiksa.') }}</p>
                <p class="text-xs text-amber-700" x-show="queued > 0" x-text="`${queued} {{ __('jawaban disimpan offline dan akan dikirim saat online.') }}`"></p>

                <template x-for="(q, i) in questions" :key="q.id">
                    <div class="card p-5 space-y-4">
                        <div class="text-xs font-semibold text-slate-400" x-text="`#${i + 1}`"></div>
                        @include('partials.question', ['mode' => 'practice'])

                        <div class="flex items-center gap-3" x-show="!results[q.id]">
                            <button type="button" class="btn-primary" @click="check(q)" :disabled="checking[q.id] || (!hasAnswer(q) && q.type !== 'speaking')">{{ __('Periksa') }}</button>
                        </div>

                        <template x-if="results[q.id]">
                            <div class="rounded-xl p-4 text-sm space-y-1" aria-live="polite"
                                 :class="results[q.id].offline ? 'bg-slate-100 dark:bg-slate-800' : (results[q.id].is_correct ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200' : (results[q.id].is_correct === null ? 'bg-sky-50 text-sky-900 dark:bg-sky-950 dark:text-sky-200' : 'bg-red-50 text-red-900 dark:bg-red-950 dark:text-red-200'))">
                                <template x-if="results[q.id].offline"><p>{{ __('Tersimpan offline — akan diperiksa saat online.') }}</p></template>
                                <template x-if="results[q.id].error"><p x-text="results[q.id].error"></p></template>
                                <template x-if="!results[q.id].offline && !results[q.id].error">
                                    <div class="space-y-1">
                                        <p class="font-semibold" x-show="q.type !== 'speaking'" x-text="results[q.id].is_correct ? '✓ {{ __('Benar!') }}' : (results[q.id].fraction > 0 ? `≈ ${Math.round(results[q.id].fraction * 100)}% {{ __('benar') }}` : '✗ {{ __('Belum tepat') }}')"></p>
                                        <p x-show="q.type === 'speaking'" x-text="results[q.id].details?.accuracy !== undefined ? `{{ __('Akurasi kata') }}: ${Math.round(results[q.id].details.accuracy * 100)}%` : '{{ __('Rekaman tersimpan untuk latihanmu. Bandingkan dengan audio contoh.') }}'"></p>
                                        <p x-show="!results[q.id].is_correct && formatCorrect(q, results[q.id])" x-text="`{{ __('Jawaban benar') }}: ${formatCorrect(q, results[q.id])}`"></p>
                                        <p class="text-slate-600 dark:text-slate-300" x-show="results[q.id].explanation" x-text="results[q.id].explanation"></p>
                                        <details x-show="results[q.id].transcript"><summary class="cursor-pointer">{{ __('Transkrip') }}</summary><p class="mt-1" x-text="results[q.id].transcript"></p></details>
                                    </div>
                                </template>
                                <button type="button" class="underline text-xs mt-1" @click="retry(q)">{{ __('Coba lagi') }}</button>
                            </div>
                        </template>
                    </div>
                </template>
            </section>
        @endif

        {{-- finish --}}
        <section class="card p-5 flex flex-col sm:flex-row items-start sm:items-center gap-4 justify-between">
            <div>
                <h2 class="font-bold" x-text="completed ? '{{ __('Materi selesai — saatnya quiz!') }}' : '{{ __('Sudah selesai mempelajari materi?') }}'"></h2>
                <p class="text-sm text-slate-500">{{ __('Lulus Lesson Quiz (≥ :score%) untuk membuka lesson berikutnya.', ['score' => $lesson->exam?->passing_score ?? 80]) }}</p>
            </div>
            @unless ($preview)
                <div class="flex gap-2">
                    <button type="button" class="btn-secondary" x-show="!completed" @click="complete()" :disabled="completing || !$store.net.online">{{ __('Tandai selesai') }}</button>
                    <a class="btn-primary" x-show="completed && quizUrl" :href="quizUrl" :class="!$store.net.online && 'pointer-events-none opacity-50'" :aria-disabled="!$store.net.online">{{ __('Mulai quiz') }} →</a>
                </div>
            @endunless
        </section>
        <p class="text-xs text-slate-500 text-center" x-show="!$store.net.online">{{ __('Quiz dan ujian membutuhkan koneksi internet.') }}</p>
    </div>
</x-app-layout>
