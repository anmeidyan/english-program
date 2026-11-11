<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-bold">{{ __('Flashcard') }}</h1>
        <p class="text-sm text-slate-500">{{ __(':learned dari :total kata sudah dipelajari · spaced repetition (SM-2)', ['learned' => $learned, 'total' => $total]) }}</p>
    </x-slot>
    <div class="max-w-xl mx-auto px-4 sm:px-6 py-6"
         x-data="flashcards({ dueUrl: @js(route('api.flashcards.due')), reviewUrl: @js(url('/app/api/flashcards/__ID__/review')) })">
        <p x-show="loading" class="text-center text-slate-500">…</p>
        <p x-show="error" x-text="error" class="text-center text-red-600"></p>

        <template x-if="!loading && card">
            <div class="space-y-4">
                <p class="text-center text-xs text-slate-500" x-text="`${index + 1} / ${cards.length}` + (card.is_new ? ' · {{ __('kartu baru') }}' : '')"></p>
                <button type="button" class="card w-full min-h-64 p-8 flex flex-col items-center justify-center text-center gap-2" @click="flipped = !flipped" :aria-label="flipped ? '{{ __('Sembunyikan arti') }}' : '{{ __('Tampilkan arti') }}'">
                    <span class="text-3xl font-bold" x-text="card.word.word"></span>
                    <span class="text-sm text-slate-500" x-text="`${card.word.part_of_speech ?? ''} · US ${card.word.ipa_us ?? ''} · UK ${card.word.ipa_uk ?? ''}`"></span>
                    <template x-if="flipped">
                        <div class="mt-4 space-y-1">
                            <p class="text-xl font-semibold text-brand-700 dark:text-brand-300" x-text="card.word.meaning_id"></p>
                            <p class="text-sm" x-text="card.word.definition_en"></p>
                            <p class="text-sm italic text-slate-500" x-text="card.word.example"></p>
                        </div>
                    </template>
                    <span x-show="!flipped" class="text-xs text-slate-400 mt-4">{{ __('Ketuk untuk melihat arti') }}</span>
                </button>
                <div class="flex justify-center gap-2">
                    <button type="button" class="chip bg-slate-100 dark:bg-slate-800 px-3 py-1" @click="say('en-US')">🔊 US</button>
                    <button type="button" class="chip bg-slate-100 dark:bg-slate-800 px-3 py-1" @click="say('en-GB')">🔊 UK</button>
                </div>
                <div class="grid grid-cols-4 gap-2" x-show="flipped">
                    <button type="button" class="btn bg-red-100 text-red-800" @click="rate('again')">{{ __('Lagi') }}<br><span class="text-[10px] font-normal">Again</span></button>
                    <button type="button" class="btn bg-amber-100 text-amber-800" @click="rate('hard')">{{ __('Sulit') }}<br><span class="text-[10px] font-normal">Hard</span></button>
                    <button type="button" class="btn bg-emerald-100 text-emerald-800" @click="rate('good')">{{ __('Bisa') }}<br><span class="text-[10px] font-normal">Good</span></button>
                    <button type="button" class="btn bg-sky-100 text-sky-800" @click="rate('easy')">{{ __('Mudah') }}<br><span class="text-[10px] font-normal">Easy</span></button>
                </div>
            </div>
        </template>

        <template x-if="!loading && !card && !error">
            <div class="card p-8 text-center space-y-2">
                <div class="text-4xl">✅</div>
                <p class="font-bold" x-text="done ? `{{ __('Sesi selesai —') }} ${done} {{ __('kartu diulang!') }}` : '{{ __('Tidak ada kartu jatuh tempo.') }}'"></p>
                <p class="text-sm text-slate-500">{{ __('Kata dari setiap lesson yang kamu selesaikan otomatis masuk ke dek ini.') }}</p>
            </div>
        </template>
    </div>
</x-app-layout>
