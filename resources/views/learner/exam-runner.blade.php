<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-4"
         x-data="examRunner({ attempt: @js($payload), apiBase: @js(url('/app/api/attempts/'.$attempt->id)) })">
        <div class="sticky top-14 z-20 -mx-4 sm:mx-0 px-4 py-3 bg-slate-50/95 dark:bg-slate-950/95 backdrop-blur flex items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-800">
            <div class="min-w-0">
                <p class="text-xs text-slate-500 truncate">{{ $attempt->exam->title }}</p>
                <p class="text-sm font-semibold"><span x-text="index + 1"></span>/<span x-text="questions.length"></span> · <span x-text="answeredCount"></span> {{ __('terjawab') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="font-mono text-lg font-bold" :class="remaining !== null && remaining < 60 ? 'text-red-600' : ''" role="timer" aria-live="off" x-text="clock"></span>
                <button type="button" class="btn-primary" @click="submit()" :disabled="submitting || !$store.net.online">{{ __('Kumpulkan') }}</button>
            </div>
        </div>

        <p class="mt-3 rounded-xl bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200 px-4 py-2 text-sm" x-show="error" x-text="error" role="alert"></p>
        <p class="mt-3 rounded-xl bg-amber-50 text-amber-800 px-4 py-2 text-sm" x-show="!$store.net.online" role="alert">{{ __('Koneksi terputus. Jawaban akan dikirim ulang saat online; jangan tutup halaman.') }}</p>

        <template x-for="q in [current]" :key="current.id">
            <section class="card p-5 mt-4 space-y-4" aria-labelledby="question-heading">
                <div class="flex items-center justify-between">
                    <h2 id="question-heading" tabindex="-1" class="text-sm font-semibold text-slate-500 focus:outline-none">
                        {{ __('Soal') }} <span x-text="index + 1"></span> · <span class="capitalize" x-text="q.skill"></span>
                    </h2>
                    <span class="text-xs" :class="failed[q.id] ? 'text-red-600' : 'text-slate-400'"
                          x-text="saving[q.id] ? '{{ __('Menyimpan…') }}' : (failed[q.id] ? '{{ __('Belum tersimpan') }}' : (saved[q.id] ? '{{ __('Tersimpan ✓') }}' : ''))"></span>
                </div>
                @include('partials.question', ['mode' => 'exam'])
            </section>
        </template>

        <div class="flex justify-between mt-4">
            <button type="button" class="btn-secondary" @click="go(index - 1)" :disabled="index === 0">← {{ __('Sebelumnya') }}</button>
            <button type="button" class="btn-secondary" @click="go(index + 1)" x-show="index < questions.length - 1">{{ __('Berikutnya') }} →</button>
            <button type="button" class="btn-primary" @click="submit()" x-show="index === questions.length - 1" :disabled="submitting">{{ __('Kumpulkan') }}</button>
        </div>

        <nav class="mt-6 flex flex-wrap gap-2" aria-label="{{ __('Navigasi soal') }}">
            <template x-for="(q, i) in questions" :key="q.id">
                <button type="button" class="w-9 h-9 rounded-lg text-sm font-semibold"
                        :class="i === index ? 'bg-brand-600 text-white' : ((hasAnswer(q) || uploads[q.id] === 'done') ? 'bg-brand-100 text-brand-800 dark:bg-slate-700 dark:text-white' : 'bg-white ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700')"
                        @click="go(i)" :aria-label="`{{ __('Soal') }} ${i + 1}`" :aria-current="i === index" x-text="i + 1"></button>
            </template>
        </nav>
    </div>
</x-app-layout>
