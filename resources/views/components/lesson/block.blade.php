@props(['block', 'glossary' => true])
@php $type = $block['type'] ?? ''; @endphp
@php $d = $block['data'] ?? []; @endphp

@switch($type)
    @case('heading')
        <h2 class="text-lg font-bold text-slate-900 dark:text-white mt-6">{{ $d['text'] ?? '' }}</h2>
        @break

    @case('rich_text')
        <div class="prose-lesson">{!! clean_html($d['html'] ?? '') !!}</div>
        @break

    @case('tense_formula')
        <div class="grid gap-2 sm:grid-cols-3" role="group" aria-label="{{ __('Rumus kalimat') }}">
            @foreach (['positive' => ['(+)', 'border-emerald-200 bg-emerald-50', 'text-emerald-700 dark:text-emerald-300'], 'negative' => ['(−)', 'border-red-200 bg-red-50', 'text-red-700 dark:text-red-300'], 'question' => ['(?)', 'border-sky-200 bg-sky-50', 'text-sky-700 dark:text-sky-300']] as $key => [$sign, $box, $text])
                <div class="rounded-xl border p-3 {{ $box }} dark:bg-slate-800 dark:border-slate-700">
                    <div class="text-xs font-bold {{ $text }}">{{ $sign }}</div>
                    <div class="font-mono text-sm mt-1">{{ $d[$key] ?? '' }}</div>
                </div>
            @endforeach
        </div>
        @if (! empty($d['note']))
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ $d['note'] }}</p>
        @endif
        @break

    @case('example_list')
        <div class="card p-4">
            @if (! empty($d['title']))<h3 class="font-semibold mb-2">{{ $d['title'] }}</h3>@endif
            <ul class="space-y-2">
                @foreach ($d['items'] ?? [] as $item)
                    <li class="flex gap-3 items-start">
                        <button type="button" class="text-brand-600 shrink-0 mt-0.5" @click="EnglishPath.speak(@js($item['en']))" aria-label="{{ __('Dengarkan') }}">🔊</button>
                        <div>
                            <div class="font-medium text-slate-900 dark:text-white">{{ $item['en'] }}</div>
                            @if (! empty($item['id']))<div class="text-sm text-slate-500">{{ $item['id'] }}</div>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
        @break

    @case('table')
        <div class="overflow-x-auto card">
            <table class="min-w-full text-sm">
                @if (! empty($d['caption']))<caption class="text-left px-4 pt-3 font-semibold">{{ $d['caption'] }}</caption>@endif
                <thead><tr>@foreach ($d['headers'] ?? [] as $h)<th scope="col" class="text-left px-4 py-2 text-slate-500 font-medium border-b border-slate-200 dark:border-slate-700">{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($d['rows'] ?? [] as $row)
                        <tr class="border-b last:border-0 border-slate-100 dark:border-slate-800">
                            @foreach ((array) $row as $cell)<td class="px-4 py-2 align-top">{{ is_array($cell) ? implode(', ', $cell) : $cell }}</td>@endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @break

    @case('reading_passage')
        <article class="card p-5" x-data="glossary" @click="lookup($event)" @keydown.enter="lookup($event)">
            @if (! empty($d['title']))<h3 class="text-lg font-bold mb-2">{{ $d['title'] }}</h3>@endif
            <div class="prose-lesson text-base leading-8" data-reading>
                @foreach (preg_split("/\n\s*\n/", (string) ($d['text'] ?? '')) as $para)
                    <p>@foreach (preg_split('/(\s+)/', $para, -1, PREG_SPLIT_DELIM_CAPTURE) as $tok)@if (trim($tok) === ''){{ $tok }}@else<span class="glossary-word" tabindex="0" role="button" data-word="{{ strtolower(preg_replace("/[^A-Za-z'-]/", '', $tok)) }}">{{ $tok }}</span>@endif @endforeach</p>
                @endforeach
            </div>
            <p class="text-xs text-slate-500 mt-3" x-data="{ start: Date.now(), words: {{ str_word_count((string) ($d['text'] ?? '')) }}, wpm: null }">
                {{ __('Klik kata untuk melihat arti.') }}
                <button type="button" class="underline" @click.stop="wpm = Math.round(words / Math.max(1, (Date.now() - start) / 60000))">{{ __('Selesai membaca') }}</button>
                <span x-show="wpm" x-text="` · ${wpm} words/minute`"></span>
            </p>
            <div x-cloak x-show="open" @click.outside="open = false" @keydown.escape.window="open = false"
                 class="absolute z-20 w-72 card p-4 shadow-lg" :style="`left:${Math.min(x, window.innerWidth - 300)}px; top:${y}px; position:absolute`" role="dialog" aria-label="{{ __('Glosarium') }}">
                <template x-if="loading"><p class="text-sm">…</p></template>
                <template x-if="!loading && word">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2"><span class="font-bold" x-text="word.word"></span><span class="text-xs text-slate-500" x-text="word.part_of_speech"></span>
                            <button type="button" @click.stop="EnglishPath.speak(word.word)" aria-label="{{ __('Dengarkan') }}">🔊</button></div>
                        <div class="text-xs text-slate-500" x-text="`US ${word.ipa_us || ''}  UK ${word.ipa_uk || ''}`"></div>
                        <div class="text-sm" x-text="word.meaning_id"></div>
                        <div class="text-xs text-slate-500" x-text="word.definition_en"></div>
                        <button type="button" class="btn-secondary w-full mt-2 text-xs" @click.stop="add()" :disabled="added" x-text="added ? '{{ __('Sudah di flashcard') }}' : '{{ __('Tambah ke flashcard') }}'"></button>
                    </div>
                </template>
                <template x-if="!loading && !word"><p class="text-sm" x-text="`“${raw}” — {{ __('belum ada di kamus EnglishPath.') }}`"></p></template>
            </div>
        </article>
        @break

    @case('audio')
        <div class="card p-4" x-data="audioPlayer({ url: @js(! empty($d['url']) ? (str_starts_with($d['url'], 'http') ? $d['url'] : \Illuminate\Support\Facades\Storage::disk(config('englishpath.media.disk'))->url($d['url'])) : null), tts: @js($d['tts_text'] ?? null), accent: @js($d['accent'] ?? 'en-US') })">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn-primary" @click="toggle()" :aria-pressed="playing" aria-label="{{ __('Putar atau jeda audio') }}">
                    <span x-text="playing ? '⏸ {{ __('Jeda') }}' : '▶ {{ __('Putar') }}'"></span>
                </button>
                <button type="button" class="btn-ghost" @click="back5()" aria-label="{{ __('Ulang 5 detik') }}">⟲ 5s</button>
                <div class="flex gap-1" role="group" aria-label="{{ __('Kecepatan') }}">
                    @foreach ([0.75, 1, 1.25] as $r)
                        <button type="button" class="chip" :class="rate === {{ $r }} ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800'" @click="setRate({{ $r }})">{{ $r }}x</button>
                    @endforeach
                </div>
                <span class="text-sm text-slate-500">{{ $d['caption'] ?? '' }} · {{ ['en-US' => 'US', 'en-GB' => 'UK', 'en-AU' => 'AU'][$d['accent'] ?? 'en-US'] ?? '' }}</span>
            </div>
            <p class="text-sm text-red-600 mt-2" x-show="error" x-text="error"></p>
            @if (! empty($d['transcript']))
                <details class="mt-3 text-sm" x-show="typeof answeredCount === 'undefined' || answeredCount > 0">
                    <summary class="cursor-pointer text-slate-500">{{ __('Lihat transkrip') }}</summary>
                    <p class="mt-2 whitespace-pre-line">{{ $d['transcript'] }}</p>
                </details>
            @endif
        </div>
        @break

    @case('image')
        @if (! empty($d['url']))
            <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('englishpath.media.disk'))->url($d['url']) }}" alt="{{ $d['alt'] ?? '' }}" loading="lazy" class="rounded-2xl w-full">
        @endif
        @break

    @case('video_embed')
        <div class="aspect-video rounded-2xl overflow-hidden"><iframe src="{{ $d['url'] }}" class="w-full h-full" loading="lazy" allowfullscreen title="Video"></iframe></div>
        @break

    @case('tip')
        <div class="rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 p-4 text-sm flex gap-2"><span aria-hidden="true">💡</span><p>{{ $d['text'] ?? '' }}</p></div>
        @break

    @case('common_mistake')
        <div class="rounded-xl ring-1 ring-red-200 dark:ring-red-900 p-4 text-sm space-y-1">
            <div class="font-semibold text-red-700 dark:text-red-300">{{ __('Kesalahan umum') }}</div>
            <div><span class="text-red-600">✗</span> <s>{{ $d['wrong'] ?? '' }}</s></div>
            <div><span class="text-emerald-600">✓</span> <strong>{{ $d['right'] ?? '' }}</strong></div>
            @if (! empty($d['note']))<div class="text-slate-600 dark:text-slate-400">{{ $d['note'] }}</div>@endif
        </div>
        @break

    @case('timeline')
        <x-lesson.timeline :label="$d['label'] ?? ''" :position="$d['position'] ?? 'now'" />
        @break
@endswitch
