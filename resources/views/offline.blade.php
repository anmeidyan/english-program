<!DOCTYPE html>
<html lang="id">
<head>@include('partials.head')</head>
<body class="font-sans antialiased bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100">
    <main class="min-h-screen grid place-items-center px-4">
        <div class="text-center space-y-4 max-w-md" x-data="{ slugs: [] }" x-init="slugs = await EnglishPath.downloadedSlugs()">
            <x-application-logo class="w-14 h-14 mx-auto" />
            <h1 class="text-2xl font-bold">Kamu sedang offline</h1>
            <p class="text-slate-600 dark:text-slate-300">Halaman ini belum tersimpan untuk offline. Lesson yang sudah diunduh tetap bisa dibuka:</p>
            <ul class="space-y-1">
                <template x-for="slug in slugs" :key="slug">
                    <li><a class="text-brand-600 underline" :href="`/learn/lessons/${slug}`" x-text="slug.replace(/-/g, ' ')"></a></li>
                </template>
            </ul>
            <p x-show="slugs.length === 0" class="text-sm text-slate-500">Belum ada lesson yang diunduh. Gunakan tombol "Unduh untuk offline" di halaman Belajar saat online.</p>
            <button type="button" class="btn-primary" onclick="location.reload()">Coba lagi</button>
        </div>
    </main>
</body>
</html>
