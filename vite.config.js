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
      // Register service worker dan strategi update
      registerType: 'autoUpdate',

      // Aktifkan PWA di development mode (penting untuk testing)
      devOptions: {
        enabled: true,
      },

      // Konfigurasi Workbox untuk caching
      workbox: {
        globPatterns: [
          '**/*.{js,css,html,ico,png,svg,webp,jpg,jpeg,gif,woff,woff2,ttf,eot}',
        ],
        cleanupOutdatedCaches: true,
        skipWaiting: true,
        clientsClaim: true,
      },

      // Manifest PWA
      manifest: {
        name: 'E-Lantera',
        short_name: 'E-Lantera',
        description: 'Aplikasi Posyandu Taman Cipulir Estate',
        theme_color: '#1f2937',
        background_color: '#ffffff',
        display: 'standalone',
        start_url: '/',
        scope: '/',
        orientation: 'portrait-primary',
        icons: [
          {
            src: '/pwa/icon-192.png',
            sizes: '192x192',
            type: 'image/png',
            purpose: 'any',
          },
          {
            src: '/pwa/icon-512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'any maskable',
          },
        ],
      },

      // Uncomment jika ingin custom register SW (opsional)
      // injectRegister: 'auto',
    }),
  ],
});