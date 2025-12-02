import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

document.addEventListener("DOMContentLoaded", function () {
    if (typeof window.$ === 'undefined') {
        console.warn('⚠️ jQuery tidak terdeteksi — pastikan dimuat sebelum app.js');
    } else {
        console.log('✅ jQuery aktif, siap pakai.');
    }

    // Inisialisasi ulang sidebar
    if (typeof initSidenav === 'function') {
        initSidenav();
    }

    // Scrollbar Material Dashboard
    if (typeof Scrollbar !== 'undefined') {
        const scrollbars = document.querySelectorAll('.scrollbar');
        scrollbars.forEach((el) => {
            if (el instanceof Element) Scrollbar.init(el);
        });
    }
});
