<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>@include('partials.head')</head>
<body class="font-sans antialiased bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100">
    <header class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <a href="/" class="flex items-center gap-2 font-bold text-lg"><x-application-logo class="w-9 h-9" /> EnglishPath</a>
        <nav class="flex gap-2">
            <a href="{{ route('login') }}" class="btn-ghost">{{ __('Masuk') }}</a>
            <a href="{{ route('register') }}" class="btn-primary">{{ __('Daftar gratis') }}</a>
        </nav>
    </header>
    <main>
        <section class="max-w-5xl mx-auto px-4 sm:px-6 py-16 sm:py-24 grid md:grid-cols-2 gap-10 items-center">
            <div>
                <p class="chip bg-brand-50 text-brand-700 mb-4">Pre-A1 → C2 · CEFR</p>
                <h1 class="text-4xl sm:text-5xl font-extrabold leading-tight">{{ __('Belajar bahasa Inggris dengan fondasi yang kuat.') }}</h1>
                <p class="mt-4 text-lg text-slate-600 dark:text-slate-300">{{ __('Jalur belajar terkunci: kamu lanjut setelah benar-benar menguasai materi. Grammar, tenses, vocabulary, reading, listening, speaking, writing, dan pronunciation dalam satu aplikasi.') }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="btn-primary text-base px-6 py-3">{{ __('Mulai sekarang') }}</a>
                    <a href="{{ route('login') }}" class="btn-secondary text-base px-6 py-3">{{ __('Saya sudah punya akun') }}</a>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                @foreach ([['🔒', __('Mastery gate'), __('Lulus quiz ≥80% untuk membuka lesson berikutnya.')], ['🎯', __('Placement test'), __('Mulai dari level yang tepat dalam ±30 menit.')], ['🃏', __('Flashcard SM-2'), __('Kosakata diulang tepat sebelum lupa.')], ['📴', __('Offline'), __('Unduh lesson dan belajar tanpa internet.')]] as [$i, $t, $d])
                    <div class="card p-5"><div class="text-2xl">{{ $i }}</div><h2 class="font-bold mt-2">{{ $t }}</h2><p class="text-sm text-slate-500 mt-1">{{ $d }}</p></div>
                @endforeach
            </div>
        </section>
    </main>
    <footer class="text-center text-xs text-slate-400 py-8">© {{ date('Y') }} EnglishPath</footer>
</body>
</html>
