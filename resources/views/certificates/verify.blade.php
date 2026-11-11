<x-guest-layout>
    @if ($certificate)
        <div class="text-center space-y-2">
            <div class="text-4xl">✅</div>
            <h1 class="text-xl font-bold">{{ __('Sertifikat valid') }}</h1>
            <p class="text-sm text-slate-500 font-mono">{{ $certificate->code }}</p>
            <dl class="text-sm text-left mt-4 space-y-1">
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Nama') }}</dt><dd class="font-semibold">{{ $certificate->user->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Level CEFR') }}</dt><dd class="font-semibold">{{ $certificate->level->label() }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Tanggal') }}</dt><dd>{{ $certificate->issued_at->translatedFormat('d F Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Skor total') }}</dt><dd>{{ $certificate->score }}</dd></div>
                @foreach ($certificate->skill_scores as $skill => $score)
                    <div class="flex justify-between"><dt class="text-slate-500 capitalize">{{ $skill }}</dt><dd>{{ $score }}</dd></div>
                @endforeach
            </dl>
            <p class="text-xs text-slate-400 mt-4">{{ __('Sertifikat internal EnglishPath, bukan sertifikat resmi lembaga.') }}</p>
        </div>
    @else
        <div class="text-center space-y-2">
            <div class="text-4xl">❌</div>
            <h1 class="text-xl font-bold">{{ __('Sertifikat tidak ditemukan') }}</h1>
            <p class="text-sm text-slate-500">{{ __('Kode ":code" tidak terdaftar.', ['code' => $code]) }}</p>
        </div>
    @endif
</x-guest-layout>
