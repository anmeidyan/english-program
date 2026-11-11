<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-bold">{{ __('Sertifikat') }}</h1></x-slot>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 space-y-3">
        @forelse ($certificates as $cert)
            <div class="card p-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold">{{ $cert->level->name }}</h2>
                    <p class="text-sm text-slate-500">{{ $cert->issued_at->translatedFormat('d F Y') }} · {{ __('Skor') }} {{ $cert->score }} · <span class="font-mono">{{ $cert->code }}</span></p>
                </div>
                <div class="flex gap-2">
                    <a class="btn-secondary" href="{{ route('certificates.verify', $cert->code) }}">{{ __('Halaman verifikasi') }}</a>
                    <a class="btn-primary" href="{{ route('certificates.download', $cert) }}">PDF</a>
                </div>
            </div>
        @empty
            <div class="card p-8 text-center text-slate-500">{{ __('Lulus Level Exam untuk mendapatkan sertifikat internal EnglishPath.') }}</div>
        @endforelse
    </div>
</x-app-layout>
