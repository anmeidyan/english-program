<div class="max-w-xl mx-auto px-4 sm:px-6 py-10">
    <ol class="flex gap-2 mb-6" aria-label="{{ __('Langkah') }}">
        @foreach ([1, 2, 3] as $s)
            <li @class(['h-1.5 flex-1 rounded-full', 'bg-brand-600' => $step >= $s, 'bg-slate-200 dark:bg-slate-800' => $step < $s])><span class="sr-only">{{ __('Langkah') }} {{ $s }}</span></li>
        @endforeach
    </ol>

    <div class="card p-6 space-y-5">
        @if ($step === 1)
            <h1 class="text-xl font-bold">{{ __('Apa tujuanmu belajar bahasa Inggris?') }}</h1>
            <div class="grid gap-2">
                @foreach ($goals as $key => $label)
                    <label @class(['option', 'option-selected' => $goal === $key])>
                        <input type="radio" class="sr-only" wire:model.live="goal" value="{{ $key }}"> {{ $label }}
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('goal')" />
            <button type="button" class="btn-primary w-full" wire:click="next">{{ __('Lanjut') }}</button>
        @elseif ($step === 2)
            <h1 class="text-xl font-bold">{{ __('Target belajar harian') }}</h1>
            <div class="grid grid-cols-4 gap-2" role="radiogroup">
                @foreach ([10, 20, 30, 60] as $m)
                    <label @class(['option justify-center', 'option-selected' => $dailyGoal === $m])>
                        <input type="radio" class="sr-only" wire:model.live="dailyGoal" value="{{ $m }}"> {{ $m }}′
                    </label>
                @endforeach
            </div>
            <div class="grid sm:grid-cols-2 gap-3" x-data x-init="$wire.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || $wire.timezone">
                <label class="text-sm">{{ __('Jam pengingat') }}<input type="time" class="input mt-1" wire:model="reminderTime"></label>
                <label class="text-sm">{{ __('Bahasa antarmuka') }}
                    <select class="input mt-1" wire:model="locale"><option value="id">Bahasa Indonesia</option><option value="en">English</option></select>
                </label>
            </div>
            <p class="text-xs text-slate-500">{{ __('Zona waktu') }}: <span x-data x-text="$wire.timezone"></span></p>
            <x-input-error :messages="$errors->get('reminderTime')" />
            <div class="flex gap-2">
                <button type="button" class="btn-secondary" wire:click="back">{{ __('Kembali') }}</button>
                <button type="button" class="btn-primary flex-1" wire:click="next">{{ __('Lanjut') }}</button>
            </div>
        @else
            <h1 class="text-xl font-bold">{{ __('Mulai dari mana?') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('Placement test (±30 menit, adaptif) menentukan level awalmu. Level di bawahnya tetap bisa dibuka sebagai review.') }}</p>
            <div class="grid gap-3">
                <button type="button" class="btn-primary" wire:click="finish('placement')">🎯 {{ __('Ikuti placement test') }}</button>
                <button type="button" class="btn-secondary" wire:click="finish('start')">{{ __('Mulai dari awal (pemula)') }}</button>
            </div>
            <button type="button" class="btn-ghost w-full" wire:click="back">{{ __('Kembali') }}</button>
        @endif
    </div>
</div>
