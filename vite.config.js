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
            registerType: 'autoUpdate',
            manifest: {
                name: 'E-Masjid',
                short_name: 'EMasjid',
                start_url: '/',
                display: 'standalone',
                background_color: '#ffffff',
                theme_color: '#1f2937',
                icons: [
                    { src: '/pwa/icon-192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/pwa/icon-512.png', sizes: '512x512', type: 'image/png' }
                ]
            }
        })
    ],
});