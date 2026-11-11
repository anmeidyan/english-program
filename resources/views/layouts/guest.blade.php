<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="font-sans antialiased text-slate-900 dark:text-slate-100">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-b from-brand-50 to-white dark:from-slate-900 dark:to-slate-950 px-4">
            <a href="/" class="flex items-center gap-2 font-bold text-xl">
                <x-application-logo class="w-12 h-12" />
                <span>EnglishPath</span>
            </a>
            <div class="w-full sm:max-w-md mt-6 px-6 py-5 bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 rounded-2xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
