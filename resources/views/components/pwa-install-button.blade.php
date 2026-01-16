<!-- Floating Install Button PWA - Gradient Keren, Ukuran Responsif -->
<div id="pwa-install-container" class="fixed bottom-6 right-5 sm:right-6 z-50 hidden">
    <button
        id="install-button"
        class="relative flex items-center justify-center
               bg-gradient-to-r from-orange-500 to-yellow-500
               text-white shadow-xl hover:shadow-2xl hover:scale-110 hover:brightness-110
               rounded-full transition-all duration-300 ease-out
               active:scale-95 focus:outline-none focus:ring-4 focus:ring-orange-300/50
               w-10 h-10 sm:w-14 sm:h-14" aria-label="Install aplikasi ke layar beranda">
        <!-- Icon download ukuran responsif -->
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 sm:h-7 sm:w-7 drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
        </svg>
    </button>

    <!-- Tooltip gradient matching, lebih kecil di mobile -->
    <div class="absolute bottom-full right-0 mb-2 sm:mb-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
        <div class="bg-gradient-to-r from-orange-600 to-yellow-600 text-white text-xs sm:text-sm font-semibold px-3 py-1 sm:px-3.5 sm:py-1.5 rounded-xl shadow-lg whitespace-nowrap">
            Install ke Layar Beranda
        </div>
    </div>
</div>

<!-- Script Handler PWA Install dengan SweetAlert2 (tetap sama) -->
<script>
    if (!window.pwaInstallInitialized) {
        window.pwaInstallInitialized = true;

        let deferredPrompt = null;
        const container = document.getElementById('pwa-install-container');
        const button = document.getElementById('install-button');

        function checkStandalone() {
            if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
                if (container) container.classList.add('hidden');
                return true;
            }
            return false;
        }

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;

            if (!checkStandalone() && container) {
                container.classList.remove('hidden');
            }
        });

        if (button) {
            button.addEventListener('click', async () => {
                if (!deferredPrompt) {
                    Swal.fire({
                        title: 'Tidak Tersedia',
                        text: 'Fitur install tidak tersedia di browser ini. Gunakan Chrome di HP.',
                        icon: 'warning',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#f97316' // orange-500
                    });
                    return;
                }

                const result = await Swal.fire({
                    title: 'Install Aplikasi?',
                    text: 'Install E-Lantera ke layar beranda untuk akses cepat & offline.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Install',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#f97316',
                    cancelButtonColor: '#6b7280',
                    reverseButtons: true,
                    backdrop: 'rgba(0,0,0,0.7)',
                    allowOutsideClick: false
                });

                if (result.isConfirmed) {
                    deferredPrompt.prompt();

                    const { outcome } = await deferredPrompt.userChoice;
                    console.log(`User memilih: ${outcome}`);

                    deferredPrompt = null;

                    if (outcome === 'accepted') {
                        container.classList.add('hidden');
                        Swal.fire({
                            title: 'Berhasil!',
                            text: 'Aplikasi berhasil diinstall. Buka dari layar beranda ya!',
                            icon: 'success',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#f97316',
                            timer: 4000,
                            timerProgressBar: true
                        });
                    } else {
                        Swal.fire({
                            title: 'Dibatalkan',
                            text: 'Install dibatalkan. Bisa dicoba lagi kapan saja.',
                            icon: 'info',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#f97316'
                        });
                    }
                }
            });
        }

        window.addEventListener('appinstalled', () => {
            console.log('App berhasil diinstall!');
            if (container) container.classList.add('hidden');
        });

        window.addEventListener('load', checkStandalone);
    }
</script>