<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-bold">{{ __('Latihan Speaking Harian') }}</h1>
        <p class="text-sm text-slate-500">{{ __('Bicara dalam bahasa Inggris, setiap kalimat langsung dikoreksi. Target: :goal jawaban per hari.', ['goal' => $state['goal']]) }}</p>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 space-y-4"
         x-data="speakingPractice({
             state: @js($state),
             turnUrl: @js(route('api.speaking.turn')),
             topicUrl: @js(route('api.speaking.topic')),
             i18n: {
                 micDenied: @js(__('Akses mikrofon ditolak. Izinkan mikrofon di pengaturan browser, atau ketik jawabanmu.')),
                 micError: @js(__('Mikrofon tidak dapat dipakai. Ketik jawabanmu di bawah.')),
                 sendError: @js(__('Gagal mengirim. Coba lagi.')),
                 offline: @js(__('Kamu sedang offline. Latihan speaking butuh koneksi internet.')),
             },
         })">

        {{-- topic + daily goal --}}
        <div class="card p-4 sm:p-5 space-y-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Topik hari ini') }} · <span x-text="state.level"></span></p>
                    <p class="text-lg font-bold" x-text="state.topic.title"></p>
                    <p class="text-sm text-slate-500" x-text="state.topic.title_id"></p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="chip bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200" title="{{ __('Hari berturut-turut mencapai target speaking') }}">🎙️ {{ trans_choice(':count hari', $streak, ['count' => $streak]) }}</span>
                    <button type="button" class="btn-ghost text-xs px-2 py-1" @click="changeTopic()" :disabled="sending || listening">↻ {{ __('Ganti topik') }}</button>
                </div>
            </div>
            <div>
                <div class="flex justify-between text-xs text-slate-500 mb-1">
                    <span x-text="`${Math.min(state.today_turns, state.goal)} / ${state.goal} {{ __('jawaban hari ini') }}`"></span>
                    <span x-show="state.today_turns >= state.goal" class="text-emerald-600 font-semibold">✓ {{ __('Target tercapai') }}</span>
                    <span x-show="state.today_turns < state.goal" x-text="`+${state.xp} XP`"></span>
                </div>
                <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-full bg-emerald-500 transition-all" :style="`width: ${progress}%`"></div>
                </div>
            </div>
        </div>

        <div x-show="celebrate !== null" x-transition class="card p-4 text-center bg-emerald-50 dark:bg-emerald-900/30 ring-emerald-200 dark:ring-emerald-800" role="status">
            <p class="font-bold text-emerald-800 dark:text-emerald-200">🎉 {{ __('Target speaking hari ini tercapai!') }} <span x-text="`+${celebrate} XP`"></span></p>
            <p class="text-sm text-emerald-700 dark:text-emerald-300">{{ __('Streak harianmu juga terhitung. Boleh lanjut ngobrol sepuasnya.') }}</p>
        </div>

        {{-- conversation --}}
        <div class="space-y-4" aria-live="polite">
            <div class="flex gap-2 items-start">
                <span class="shrink-0 w-8 h-8 rounded-full bg-brand-600 text-white grid place-items-center text-sm" aria-hidden="true">🤖</span>
                <div class="card px-4 py-3 max-w-[85%]">
                    <p x-text="state.topic.opener"></p>
                    <button type="button" class="mt-1 text-xs text-brand-600 dark:text-brand-300" @click="say(state.topic.opener)">🔊 {{ __('Dengarkan') }}</button>
                </div>
            </div>

            <template x-for="turn in state.turns" :key="turn.id">
                <div class="space-y-4">
                    {{-- learner turn with correction --}}
                    <div class="flex justify-end">
                        <div class="max-w-[90%] space-y-2">
                            <div class="rounded-2xl px-4 py-3 bg-brand-50 dark:bg-slate-800 ring-1 ring-brand-100 dark:ring-slate-700">
                                <div class="flex items-center justify-between gap-3 mb-1">
                                    <span class="text-xs text-slate-500">{{ __('Kamu') }}</span>
                                    <span class="chip"
                                          :class="turn.score >= 85 ? 'bg-emerald-100 text-emerald-800' : (turn.score >= 60 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800')"
                                          x-text="`${turn.score}/100`"></span>
                                </div>
                                <p class="leading-relaxed">
                                    <template x-for="(w, i) in diff(turn)" :key="i">
                                        <span>
                                            <span x-show="w.type === 'same'" x-text="w.text"></span>
                                            <del x-show="w.type === 'del'" class="text-red-600 dark:text-red-400 decoration-2" x-text="w.text"></del>
                                            <ins x-show="w.type === 'add'" class="no-underline font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-100/70 dark:bg-emerald-900/40 rounded px-0.5" x-text="w.text"></ins>
                                        </span>
                                    </template>
                                </p>
                            </div>

                            <div class="card px-4 py-3 text-sm space-y-2">
                                <template x-if="turn.corrections.length === 0">
                                    <p class="text-emerald-700 dark:text-emerald-300 font-medium">✓ {{ __('Tidak ada kesalahan. Mantap!') }}</p>
                                </template>
                                <template x-if="turn.corrections.length > 0">
                                    <div class="space-y-1.5">
                                        <p class="font-semibold">{{ __('Koreksi') }}</p>
                                        <ul class="space-y-1.5">
                                            <template x-for="(c, i) in turn.corrections" :key="i">
                                                <li>
                                                    <span class="line-through text-red-600 dark:text-red-400" x-text="c.original"></span>
                                                    → <span class="font-semibold text-emerald-700 dark:text-emerald-300" x-text="c.corrected"></span>
                                                    <span class="block text-slate-600 dark:text-slate-400" x-text="c.explanation"></span>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
                                <div x-show="turn.corrected.trim() !== turn.transcript.trim()" class="pt-1 border-t border-slate-100 dark:border-slate-800">
                                    <p class="text-xs text-slate-500">{{ __('Kalimat yang benar') }}</p>
                                    <p class="font-medium" x-text="turn.corrected"></p>
                                    <button type="button" class="text-xs text-brand-600 dark:text-brand-300" @click="say(turn.corrected, 0.85)">🔊 {{ __('Dengarkan & tirukan') }}</button>
                                </div>
                                <p x-show="turn.tip" class="text-slate-600 dark:text-slate-400">💡 <span x-text="turn.tip"></span></p>
                            </div>
                        </div>
                    </div>

                    {{-- coach reply --}}
                    <div class="flex gap-2 items-start">
                        <span class="shrink-0 w-8 h-8 rounded-full bg-brand-600 text-white grid place-items-center text-sm" aria-hidden="true">🤖</span>
                        <div class="card px-4 py-3 max-w-[85%]">
                            <p x-text="turn.reply"></p>
                            <button type="button" class="mt-1 text-xs text-brand-600 dark:text-brand-300" @click="say(turn.reply)">🔊 {{ __('Dengarkan') }}</button>
                        </div>
                    </div>
                </div>
            </template>
            <div x-ref="bottom"></div>
        </div>

        {{-- input --}}
        <div class="card p-4 space-y-3 sticky bottom-3">
            <p x-show="error" x-text="error" class="text-sm text-red-600" role="alert"></p>

            <template x-if="supported">
                <div class="flex items-center gap-3">
                    <button type="button" @click="toggleMic()" :disabled="sending"
                            class="shrink-0 w-14 h-14 rounded-full grid place-items-center text-white text-2xl shadow transition"
                            :class="listening ? 'bg-red-600 animate-pulse' : 'bg-brand-600 hover:bg-brand-700'"
                            :aria-label="listening ? @js(__('Berhenti & kirim')) : @js(__('Mulai bicara'))">
                        <span x-text="listening ? '■' : '🎤'"></span>
                    </button>
                    <div class="min-w-0 flex-1 text-sm">
                        <p x-show="listening && !live" class="text-slate-500">{{ __('Mendengarkan… silakan bicara dalam bahasa Inggris.') }}</p>
                        <p x-show="listening && live" class="italic" x-text="live"></p>
                        <p x-show="!listening && !sending && !draft" class="text-slate-500">{{ __('Tekan mikrofon, jawab pertanyaan, lalu tekan ■ untuk dikoreksi.') }}</p>
                        <p x-show="sending" class="text-slate-500">{{ __('Mengoreksi…') }}</p>
                    </div>
                </div>
            </template>
            <p x-show="!supported" class="text-sm text-amber-700 dark:text-amber-300">{{ __('Browser ini belum mendukung pengenalan suara (pakai Chrome atau Edge untuk bicara langsung). Sementara itu, ketik jawabanmu.') }}</p>

            <div x-show="!listening && (draft || !supported || !autoSend)" class="space-y-2">
                <label for="speaking-draft" class="sr-only">{{ __('Jawabanmu') }}</label>
                <textarea id="speaking-draft" x-model="draft" rows="2" maxlength="{{ config('englishpath.speaking.max_chars') }}"
                          class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm"
                          placeholder="{{ __('Jawabanmu dalam bahasa Inggris…') }}"
                          @keydown.enter.prevent="send()"></textarea>
                <div class="flex justify-end">
                    <button type="button" class="btn-primary" @click="send()" :disabled="sending || draft.trim().length < 2">{{ __('Koreksi') }}</button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                <label class="inline-flex items-center gap-1.5" x-show="supported"><input type="checkbox" x-model="autoSend" class="rounded"> {{ __('Langsung koreksi saat berhenti bicara') }}</label>
                <label class="inline-flex items-center gap-1.5"><input type="checkbox" x-model="voiceOn" class="rounded"> {{ __('Bacakan balasan') }}</label>
                <label class="inline-flex items-center gap-1.5">{{ __('Aksen') }}
                    <select x-model="accent" class="rounded border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs py-0.5">
                        <option value="en-US">US</option>
                        <option value="en-GB">UK</option>
                    </select>
                </label>
            </div>
        </div>

        {{-- 14-day calendar --}}
        <div class="card p-4">
            <p class="text-sm font-semibold mb-3">{{ __('14 hari terakhir') }}</p>
            <div class="grid grid-cols-7 gap-1.5">
                @foreach ($history as $day)
                    @php($d = \Illuminate\Support\Carbon::parse($day['date']))
                    <div @class([
                            'rounded-lg p-1.5 text-center text-[11px] leading-tight',
                            'bg-emerald-500 text-white' => $day['goal'],
                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' => ! $day['goal'] && $day['turns'] > 0,
                            'bg-slate-100 text-slate-400 dark:bg-slate-800' => $day['turns'] === 0,
                         ])
                         title="{{ $d->translatedFormat('l, j M') }}: {{ $day['turns'] }} {{ __('jawaban') }}{{ $day['score'] !== null ? ' · '.__('skor').' '.$day['score'] : '' }}">
                        <div class="font-semibold">{{ $d->format('j') }}</div>
                        <div>{{ $day['turns'] ?: '·' }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
