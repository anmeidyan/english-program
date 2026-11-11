{{--
    Renders the Alpine-scoped question `q` for every type (PRD 4.8).
    $mode: practice | exam. Parent scope provides the questionMixin helpers.
--}}
@php $mode = $mode ?? 'practice'; @endphp
<div class="space-y-4">
    {{-- passage for reading items --}}
    <template x-if="q.passage">
        <details class="card p-4 text-sm leading-7" {{ $mode === 'exam' ? 'open' : '' }}>
            <summary class="cursor-pointer font-semibold">{{ __('Teks bacaan') }}</summary>
            <p class="mt-2 whitespace-pre-line" x-text="q.passage"></p>
        </details>
    </template>

    {{-- audio for listening / dictation / repeat --}}
    <template x-if="q.has_audio">
        <div class="flex flex-wrap items-center gap-3"
             @if ($mode === 'exam')
                 x-data="audioPlayer({ fetchSource: fetchAudio(q), plays: q.audio_plays, maxPlays: attempt.max_audio_plays })"
             @else
                 x-data="audioPlayer({ tts: q.tts_text, url: q.media_url })"
             @endif>
            <button type="button" class="btn-secondary" @click="toggle()" :disabled="!playing && limitReached" aria-label="{{ __('Putar audio') }}">
                <span x-text="playing ? '⏸ {{ __('Jeda') }}' : '🔊 {{ __('Dengarkan') }}'"></span>
            </button>
            <div class="flex gap-1" role="group" aria-label="{{ __('Kecepatan') }}">
                <template x-for="r in [0.75, 1, 1.25]" :key="r">
                    <button type="button" class="chip" :class="rate === r ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800'" @click="setRate(r)" x-text="`${r}x`"></button>
                </template>
            </div>
            <span class="text-xs text-slate-500" x-show="maxPlays > 0" x-text="`{{ __('Diputar') }} ${plays}/${maxPlays}`"></span>
            <span class="text-xs text-red-600" x-show="error" x-text="error"></span>
        </div>
    </template>

    {{-- prompt (fill_blank renders inline inputs instead) --}}
    <template x-if="q.type !== 'fill_blank'">
        <p class="text-base font-medium text-slate-900 dark:text-white whitespace-pre-line" x-text="q.prompt"></p>
    </template>

    {{-- single choice --}}
    <template x-if="q.type === 'mcq_single'">
        <div class="grid gap-2" role="radiogroup" :aria-label="q.prompt">
            <template x-for="opt in q.options" :key="opt.key">
                <button type="button" role="radio" class="option" :class="isPicked(q, opt.key) && 'option-selected'" :aria-checked="isPicked(q, opt.key)"
                        :disabled="locked(q)" @click="setAnswer(q, opt.key)">
                    <span class="w-6 h-6 shrink-0 rounded-full border grid place-items-center text-xs font-bold uppercase" x-text="opt.key"></span>
                    <span x-text="opt.text"></span>
                </button>
            </template>
        </div>
    </template>

    {{-- multiple choice --}}
    <template x-if="q.type === 'mcq_multi'">
        <div class="grid gap-2" role="group">
            <p class="text-xs text-slate-500">{{ __('Pilih semua jawaban yang benar.') }}</p>
            <template x-for="opt in q.options" :key="opt.key">
                <button type="button" role="checkbox" class="option" :class="isPicked(q, opt.key) && 'option-selected'" :aria-checked="isPicked(q, opt.key)"
                        :disabled="locked(q)" @click="toggleMulti(q, opt.key)">
                    <span class="w-5 h-5 shrink-0 rounded border grid place-items-center" x-text="isPicked(q, opt.key) ? '✓' : ''"></span>
                    <span x-text="opt.text"></span>
                </button>
            </template>
        </div>
    </template>

    {{-- true / false / not given --}}
    <template x-if="q.type === 'true_false_ng'">
        <div class="grid grid-cols-3 gap-2" role="radiogroup">
            <template x-for="opt in q.options" :key="opt">
                <button type="button" role="radio" class="option justify-center!" :class="isPicked(q, opt) && 'option-selected'" :aria-checked="isPicked(q, opt)"
                        :disabled="locked(q)" @click="setAnswer(q, opt)" x-text="tfLabel(opt)"></button>
            </template>
        </div>
    </template>

    {{-- fill in the blank --}}
    <template x-if="q.type === 'fill_blank'">
        <p class="text-base leading-10 text-slate-900 dark:text-white">
            <template x-for="(part, i) in promptParts(q)" :key="i">
                <span><span x-text="part"></span><template x-if="i < promptParts(q).length - 1">
                    <input type="text" class="input inline-block w-36 mx-1 py-1 text-center" autocomplete="off" autocapitalize="off" spellcheck="false"
                           :aria-label="`{{ __('Isian') }} ${i + 1}`" :disabled="locked(q)" :value="blankValue(q, i)" @input="setBlank(q, i, $event.target.value)">
                </template></span>
            </template>
        </p>
    </template>

    {{-- word order --}}
    <template x-if="q.type === 'word_order'">
        <div class="space-y-3">
            <div class="min-h-14 rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-700 p-2 flex flex-wrap gap-2" aria-live="polite">
                <template x-for="(token, i) in orderPicked(q)" :key="token">
                    <button type="button" class="chip bg-brand-600 text-white text-sm px-3 py-1.5" :disabled="locked(q)" @click="orderRemove(q, i)" x-text="token.split(':').slice(1).join(':')"></button>
                </template>
                <span class="text-sm text-slate-400 self-center" x-show="orderPicked(q).length === 0">{{ __('Ketuk kata di bawah sesuai urutan') }}</span>
                <span class="self-center font-bold" x-show="orderPicked(q).length" x-text="q.end_punctuation"></span>
            </div>
            <div class="flex flex-wrap gap-2">
                <template x-for="t in orderAvailable(q)" :key="t.id">
                    <button type="button" class="chip bg-slate-100 dark:bg-slate-800 text-sm px-3 py-1.5" :disabled="locked(q)" @click="orderAdd(q, t.id)" x-text="t.word"></button>
                </template>
            </div>
        </div>
    </template>

    {{-- matching --}}
    <template x-if="q.type === 'matching'">
        <div class="grid gap-2">
            <template x-for="left in q.left" :key="left.key">
                <label class="flex items-center gap-3">
                    <span class="w-1/2 font-medium" x-text="left.text"></span>
                    <select class="input w-1/2" :disabled="locked(q)" :value="matchValue(q, left.key)" @change="setMatch(q, left.key, $event.target.value)">
                        <option value="">—</option>
                        <template x-for="right in q.right" :key="right.key">
                            <option :value="right.key" :selected="matchValue(q, left.key) === right.key" x-text="right.text"></option>
                        </template>
                    </select>
                </label>
            </template>
        </div>
    </template>

    {{-- dictation / transformation / error correction --}}
    <template x-if="['dictation', 'transformation', 'error_correction'].includes(q.type)">
        <div>
            <template x-if="q.type === 'error_correction' && q.sentence">
                <button type="button" class="text-xs text-brand-600 underline mb-2" :disabled="locked(q)" @click="setAnswer(q, q.sentence)">{{ __('Salin kalimat untuk diperbaiki') }}</button>
            </template>
            <input type="text" class="input" autocomplete="off" autocapitalize="off" spellcheck="false" :disabled="locked(q)"
                   :aria-label="q.prompt" :value="getAnswer(q) ?? ''" @input="setAnswer(q, $event.target.value)" placeholder="{{ __('Ketik jawabanmu…') }}">
        </div>
    </template>

    {{-- writing --}}
    <template x-if="q.type === 'writing'">
        <div>
            <textarea class="input min-h-48" :disabled="locked(q)" :aria-label="q.prompt" :value="getAnswer(q)?.text ?? ''"
                      @input="setAnswer(q, { text: $event.target.value })" placeholder="{{ __('Tulis jawabanmu di sini…') }}"></textarea>
            <p class="text-xs mt-1" :class="(wordCount(getAnswer(q)?.text) < q.min_words || (q.max_words && wordCount(getAnswer(q)?.text) > q.max_words)) ? 'text-amber-600' : 'text-emerald-600'"
               x-text="`${wordCount(getAnswer(q)?.text)} {{ __('kata') }} (${q.min_words}–${q.max_words})`"></p>
        </div>
    </template>

    {{-- speaking --}}
    <template x-if="q.type === 'speaking'">
        <div x-data="recorder({ maxSeconds: q.max_seconds })"
             @if ($mode === 'exam') @recorded="uploadRecording(q, $event.detail)" @else @recorded="onRecorded(q, $event.detail)" @endif
             class="space-y-3">
            <template x-if="q.target_text">
                <blockquote class="card p-4 text-lg" x-text="q.target_text"></blockquote>
            </template>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn-primary" x-show="!recording" @click="startRecording()" :disabled="locked(q)">🎙 {{ __('Mulai rekam') }}</button>
                <button type="button" class="btn bg-red-600 text-white" x-show="recording" @click="stopRecording()">⏹ {{ __('Berhenti') }}</button>
                <span class="font-mono text-sm" x-text="`${clock} / ${Math.floor(maxSeconds / 60)}:${String(maxSeconds % 60).padStart(2, '0')}`"></span>
                <span class="text-xs text-red-600" x-show="recording" aria-live="polite">● REC</span>
            </div>
            <template x-if="blobUrl">
                <div class="space-y-1">
                    <p class="text-xs text-slate-500">{{ __('Rekamanmu:') }}</p>
                    <audio :src="blobUrl" controls class="w-full" aria-label="{{ __('Rekaman kamu') }}"></audio>
                    <p class="text-xs text-slate-500" x-show="transcript" x-text="`{{ __('Terdengar:') }} “${transcript}”`"></p>
                </div>
            </template>
            @if ($mode === 'exam')
                <p class="text-xs" x-show="uploads[q.id]" :class="uploads[q.id] === 'error' ? 'text-red-600' : 'text-emerald-600'"
                   x-text="{ uploading: '{{ __('Mengunggah…') }}', done: '{{ __('Rekaman tersimpan ✓') }}', error: '{{ __('Gagal mengunggah, rekam ulang.') }}' }[uploads[q.id]]"></p>
            @endif
            <p class="text-sm text-red-600" x-show="error" x-text="error"></p>
        </div>
    </template>
</div>
