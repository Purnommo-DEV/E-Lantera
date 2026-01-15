import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),

        VitePWA({
            registerType: 'autoUpdate',  // tetap bagus, auto update SW saat deploy baru

            // Penting untuk development: aktifkan SW + manifest di mode dev
            devOptions: {
                enabled: true,           // <-- tambahkan ini! Biar bisa test langsung di localhost
                // type: 'module',       // optional, kalau pakai ESM di SW custom
            },

            // Tambah ini biar cache lebih lengkap (js, css, html, images, fonts, dll)
            workbox: {
                globPatterns: [
                    '**/*.{js,css,html,ico,png,svg,webp,jpg,jpeg,gif,woff,woff2,ttf,eot}'
                ],
                // Optional: clean cache lama saat update
                cleanupOutdatedCaches: true,
                // Optional: skip waiting biar update langsung aktif (user tidak perlu close tab)
                skipWaiting: true,
                clientsClaim: true,
            },

            // Manifest kamu sudah oke, tapi tambah sedikit lengkap
            manifest: {
                name: 'E-Masjid',
                short_name: 'E-Lantera',
                description: 'Aplikasi Posyandu Taman Cipulir Estate',  // tambah ini bagus untuk SEO/PWA
                start_url: '/',
                display: 'standalone',
                background_color: '#ffffff',
                theme_color: '#1f2937',  // match dengan primary color Tailwind/DaisyUI kamu
                orientation: 'portrait-primary',  // optional, biar lebih native feel
                icons: [
                    {
                        src: '/pwa/icon-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any'  // tambah purpose biar support maskable
                    },
                    {
                        src: '/pwa/icon-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any maskable'  // penting untuk adaptive icon di Android
                    }
                ]
            },

            // Optional: injectRegister 'auto' atau 'script' kalau ingin kontrol register SW manual
            // injectRegister: 'auto',
        })
    ],
});