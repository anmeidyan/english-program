import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.js',
            outDir: 'public/build',
            buildBase: '/build/',
            scope: '/',
            base: '/',
            registerType: 'prompt',
            injectRegister: false,
            injectManifest: {
                globPatterns: ['**/*.{js,css,woff2}'],
                globDirectory: 'public/build',
                swDest: 'public/build/sw.js',
            },
            manifestFilename: 'manifest.webmanifest',
            manifest: {
                id: '/dashboard?source=pwa',
                name: 'EnglishPath',
                short_name: 'EnglishPath',
                description: 'Belajar bahasa Inggris dari Pre-A1 sampai C2 dengan jalur belajar terkunci.',
                lang: 'id',
                start_url: '/dashboard?source=pwa',
                scope: '/',
                display: 'standalone',
                orientation: 'portrait',
                theme_color: '#2f6fe4',
                background_color: '#f8fafc',
                categories: ['education'],
                icons: [
                    { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
                    { src: '/icons/icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
                screenshots: [
                    { src: '/screenshots/desktop.png', sizes: '1280x720', type: 'image/png', form_factor: 'wide', label: 'Dashboard' },
                    { src: '/screenshots/mobile.png', sizes: '540x1170', type: 'image/png', form_factor: 'narrow', label: 'Lesson' },
                ],
                shortcuts: [
                    { name: 'Lanjutkan belajar', short_name: 'Belajar', url: '/learn?source=shortcut', icons: [{ src: '/icons/icon-192.png', sizes: '192x192' }] },
                    { name: 'Flashcard', short_name: 'Flashcard', url: '/flashcards?source=shortcut', icons: [{ src: '/icons/icon-192.png', sizes: '192x192' }] },
                ],
            },
            devOptions: { enabled: false },
        }),
    ],
    server: {
        host: '0.0.0.0',
        hmr: { host: 'localhost' },
        watch: { ignored: ['**/storage/**', '**/vendor/**'] },
    },
});
