<div class="max-w-2xl mx-auto px-4 sm:px-6 py-10">
    <div class="card p-6 space-y-5">
        @if ($resultLevel)
            <div class="text-center space-y-3">
                <div class="text-5xl">🎯</div>
                <h1 class="text-2xl font-bold">{{ __('Level awalmu: :level', ['level' => $resultLevel]) }}</h1>
                <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('Level di bawahnya ditandai "lulus via placement" dan tetap bisa dibuka sebagai review.') }}</p>
                <a href="{{ route('dashboard') }}" class="btn-primary">{{ __('Ke dashboard') }}</a>
            </div>
        @elseif ($blockedUntil)
            <h1 class="text-xl font-bold">{{ __('Placement test') }}</h1>
            <p>{{ __('Placement test hanya bisa diulang 1x per 30 hari. Kamu bisa mencoba lagi pada :date.', ['date' => $blockedUntil]) }}</p>
            <a href="{{ route('dashboard') }}" class="btn-secondary">{{ __('Kembali') }}</a>
        @elseif (! $started)
            <h1 class="text-xl font-bold">{{ __('Placement test') }}</h1>
            <ul class="list-disc pl-5 text-sm space-y-1 text-slate-600 dark:text-slate-300">
                <li>{{ __('Adaptif: soal naik atau turun level sesuai jawabanmu.') }}</li>
                <li>{{ __('Sekitar 5 soal per level, ±30 menit.') }}</li>
                <li>{{ __('Jawab sejujurnya — tidak apa-apa melewati soal yang tidak kamu tahu.') }}</li>
                <li>{{ __('Bisa diulang 1x per 30 hari.') }}</li>
            </ul>
            <button type="button" class="btn-primary w-full" wire:click="start">{{ __('Mulai') }}</button>
        @elseif ($question)
            <div class="flex justify-between text-xs text-slate-500">
                <span>{{ __('Soal') }} {{ $progress + 1 }}</span>
                <span>{{ __('Tahap') }} {{ count($scores) + 1 }}</span>
            </div>
            <p class="text-lg font-medium">{{ $question['prompt'] }}</p>
            <div class="grid gap-2" role="radiogroup">
                @foreach ($question['options'] ?? [] as $opt)
                    <label @class(['option', 'option-selected' => $answer === $opt['key']]) wire:key="opt-{{ $question['id'] }}-{{ $opt['key'] }}">
                        <input type="radio" class="sr-only" wire:model.live="answer" value="{{ $opt['key'] }}">
                        <span class="w-6 h-6 shrink-0 rounded-full border grid place-items-center text-xs font-bold uppercase">{{ $opt['key'] }}</span>
                        {{ $opt['text'] }}
                    </label>
                @endforeach
            </div>
            <div class="flex gap-2">
                <button type="button" class="btn-ghost" wire:click="skip">{{ __('Tidak tahu') }}</button>
                <button type="button" class="btn-primary flex-1" wire:click="submit" @disabled(! $answer)>{{ __('Jawab') }}</button>
            </div>
        @endif
    </div>
</div>
