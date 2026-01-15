<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'E-Lantera') — Posyandu Taman Cipulir Estate</title>

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('elantera.png') }}" type="image/png">

    <!-- Font Poppins (sama persis seperti sebelumnya) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind + DaisyUI + AlpineJS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet">

    <!-- SweetAlert2 (yang Anda pakai sebelumnya) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- DataTables + Buttons (tetap pakai yang Anda suka) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.tailwindcss.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.tailwindcss.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    @include('components.pwa-head')

    <style>
        body { font-family: 'Poppins', sans-serif; }
        .swal2-popup { font-family: 'Poppins', sans-serif !important; }

        /* Tables: make rows slightly tighter and text smaller */
        .table.table-zebra {
            font-size: 0.875rem; /* 14px */
        }
        .table.table-zebra th, .table.table-zebra td {
            padding: 0.45rem 0.6rem; /* reduce cell padding */
            vertical-align: middle;
        }

        /* Table header: slightly smaller */
        .table.table-zebra thead th {
            font-size: 0.86rem;
            padding: 0.5rem 0.65rem;
        }

        /* Reduce zebra stripe height visually */
        .table.table-zebra tbody tr { line-height: 1.1; }

        /* Buttons: smaller defaults for "dense" layout (non-destructive) */
        .btn, button.btn {
            padding: 0.38rem 0.7rem;
            font-size: 0.88rem;
            border-radius: 0.5rem;
        }

        /* Specific: large buttons used in forms/tables — reduce while keeping variant */
        .btn.btn-lg, .btn-lg {
            padding: 0.45rem 0.9rem;
            font-size: 0.92rem;
        }
        .btn.btn-sm, .btn-sm {
            padding: 0.28rem 0.5rem;
            font-size: 0.78rem;
        }

        /* Reduce top header padding across pages for compact look */
        .bg-gradient-to-r.p-6, .bg-gradient-to-r.p-8, .bg-gradient-to-r.p-4 {
            padding: 0.75rem 1rem !important;
        }

        /* Modal boxes: slightly smaller max width & less padding */
        .modal-box {
            padding: 0.9rem !important;
        }
        .modal-box .text-3xl { font-size: 1.15rem !important; }

        /* Dialog content scroll area */
        .modal-box .max-h-screen { max-height: 70vh !important; }

        /* Badges / small labels */
        .badge, .badge-outline, .badge-success, .badge-warning, .badge-error {
            font-size: 0.72rem;
            padding: 0.2rem 0.45rem;
        }

        /* Inputs / selects / textarea: slightly smaller and less padding */
        .input, .select, .textarea, .input-bordered, .select-bordered, .textarea-bordered {
            padding: 0.42rem 0.6rem;
            font-size: 0.9rem;
        }

        /* Reduce form heading sizes */
        #ajaxForm h2, .modal-box h2, .modal-box h3 {
            font-size: 1.05rem;
        }

        /* Card spacing reductions used in many blades */
        .p-6, .p-8 {
            padding: 0.75rem !important;
        }

        /* Utility: make action column content wrap tighter */
        .whitespace-nowrap { white-space: nowrap; }
        .text-center.whitespace-nowrap .btn { margin: 0 0.2rem; }

        /* Small screens: even more compact */
        @media (max-width: 768px) {
            .table.table-zebra { font-size: 0.82rem; }
            .btn { padding: 0.28rem 0.5rem; font-size: 0.82rem; }
            .modal-box { padding: 0.7rem !important; }
            .input, .select, .textarea { font-size: 0.82rem; padding: 0.32rem 0.45rem; }
        }

        /* Optional helper class: apply denser spacing to any container by adding .dense */
        .dense .table.table-zebra th,
        .dense .table.table-zebra td { padding: 0.35rem 0.45rem; }
        .dense .btn { padding: 0.28rem 0.5rem; font-size: 0.82rem; }
        .dense .input, .dense .select, .dense .textarea { padding: 0.32rem 0.45rem; font-size: 0.82rem; }

    </style>
</head>

<body class="h-full bg-gray-100" x-data="{ sidebarOpen: false }">

<div class="flex h-screen overflow-hidden">

    {{-- SIDEBAR — Hanya muncul kalau login --}}
    @auth

{{-- SIDEBAR (Premium theme: stone + amber) --}}
<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-50 w-60 lg:w-78
              bg-gradient-to-b from-emerald-950 to-emerald-800
              text-emerald-100
              transform transition-all duration-300
              lg:translate-x-0 lg:static lg:inset-0 shadow-2xl">

    {{-- HEADER LOGO --}}
    <div class="flex items-center justify-between p-6 border-b border-emerald-700">
        <div class="flex items-center space-x-4">
            <div class="w-12 h-12 bg-amber-400 rounded-full flex items-center justify-center
                        text-lg font-extrabold text-emerald-950 shadow-lg">
                EL
            </div>
            <div>
                <h1 class="text-lg font-bold text-emerald-50">E-Lantera</h1>
                <p class="text-xs opacity-80 text-emerald-200">Posyandu Taman Cipulir Estate</p>
            </div>
        </div>

        <button @click="sidebarOpen = false" class="lg:hidden text-emerald-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- MENU --}}
    <nav class="mt-10 px-6">
        <ul class="space-y-3">

            {{-- DASHBOARD --}}
            <li>
                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center space-x-4 px-5 py-3 rounded-xl transition-all
                          {{ request()->routeIs('admin.dashboard')
                              ? 'bg-amber-400 text-emerald-950 font-semibold shadow-md'
                              : 'hover:bg-emerald-700 hover:translate-x-1 hover:shadow' }}">

                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3"/>
                    </svg>

                    <span class="text-xs lg:text-sm">Dashboard</span>
                </a>
            </li>

            {{-- USER --}}
            <li>
                <a href="{{ route('user.index') }}"
                   class="flex items-center space-x-4 px-5 py-3 rounded-xl transition-all
                          {{ request()->routeIs('user.*')
                              ? 'bg-amber-400 text-emerald-950 font-semibold shadow-md'
                              : 'hover:bg-emerald-700 hover:translate-x-1 hover:shadow' }}">

                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M5.121 17.804A7 7 0 0112 15a7 7 0 016.879 2.804M12 7a4 4 0 110-8 4 4 0 010 8z"/>
                    </svg>

                    <span class="text-xs lg:text-sm">Data User</span>
                </a>
            </li>

            {{-- ROLE --}}
            <li>
                <a href="{{ route('role.index') }}"
                   class="flex items-center space-x-4 px-5 py-3 rounded-xl transition-all
                          {{ request()->routeIs('role.*')
                              ? 'bg-amber-400 text-emerald-950 font-semibold shadow-md'
                              : 'hover:bg-emerald-700 hover:translate-x-1 hover:shadow' }}">

                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8c1.657 0 3-1.567 3-3.5S13.657 1 12 1 9 2.567 9 4.5 10.343 8 12 8zm0 2C9.239 10 5 11.567 5 14v3h14v-3c0-2.433-4.239-4-7-4z"/>
                    </svg>

                    <span class="text-xs lg:text-sm">Role & Permission</span>
                </a>
            </li>

            {{-- WARGA --}}
            <li>
                <a href="{{ route('warga.index') }}"
                   class="flex items-center space-x-4 px-5 py-3 rounded-xl transition-all
                          {{ request()->routeIs('warga.*')
                              ? 'bg-amber-400 text-emerald-950 font-semibold shadow-md'
                              : 'hover:bg-emerald-700 hover:translate-x-1 hover:shadow' }}">

                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4a4 4 0 110 8 4 4 0 010-8zm6 8a6 6 0 00-12 0v5h12v-5z"/>
                    </svg>

                    <span class="text-xs lg:text-sm">Data Warga</span>
                </a>
            </li>

            {{-- PEMERIKSAAN DEWASA --}}
            <li>
                <a href="{{ route('dewasa.index') }}"
                   class="flex items-center space-x-4 px-5 py-3 rounded-xl transition-all
                          {{ request()->routeIs('dewasa.*')
                              ? 'bg-amber-400 text-emerald-950 font-semibold shadow-md'
                              : 'hover:bg-emerald-700 hover:translate-x-1 hover:shadow' }}">

                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                              d="M9 12h6m-6-4h6m2 5a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>

                    <span class="text-xs lg:text-sm">Pemeriksaan Dewasa</span>
                </a>
            </li>

            {{-- PEMERIKSAAN LANSIA --}}
            <li>
                <a href="{{ route('lansia.index') }}"
                   class="flex items-center space-x-4 px-5 py-3 rounded-xl transition-all
                          {{ request()->routeIs('lansia.*')
                              ? 'bg-amber-400 text-emerald-950 font-semibold shadow-md'
                              : 'hover:bg-emerald-700 hover:translate-x-1 hover:shadow' }}">

                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                              d="M5.121 17.804A7 7 0 0112 15a7 7 0 016.879 2.804M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>

                    <span class="text-xs lg:text-sm">Pemeriksaan Lansia</span>
                </a>
            </li>

            {{-- REKAP --}}
            <li>
                <a href="{{ route('rekap.index') }}"
                   class="flex items-center space-x-4 px-5 py-3 rounded-xl transition-all
                          {{ request()->routeIs('rekap.*')
                              ? 'bg-amber-400 text-emerald-950 font-semibold shadow-md'
                              : 'hover:bg-emerald-700 hover:translate-x-1 hover:shadow' }}">

                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                              d="M9 19V9m4 10V5m4 14v-6"/>
                    </svg>

                    <span class="text-xs lg:text-sm">Rekap Bulanan</span>
                </a>
            </li>
            
            {{-- REGISTER DEWASA & LANSIA --}}
            <li>
                <a href="{{ route('register.dewasa-lansia.index') }}"
                   class="flex items-center space-x-4 px-5 py-3 rounded-xl transition-all
                          {{ request()->routeIs('register.dewasa-lansia.*')
                              ? 'bg-amber-400 text-emerald-950 font-semibold shadow-md'
                              : 'hover:bg-emerald-700 hover:translate-x-1 hover:shadow' }}">

                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                              d="M9 5h6m-6 4h6m-6 4h6m-7 6h8"/>
                    </svg>

                    <span class="text-xs lg:text-sm">Register Dewasa & Lansia</span>
                </a>
            </li>

        </ul>
    </nav>

    {{-- LOGOUT --}}
    <div class="absolute bottom-0 w-full p-6 border-t border-emerald-700">
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit"
                    class="w-full flex items-center justify-center space-x-3 px-5 py-3
                           bg-red-600 hover:bg-red-700 rounded-xl transition-all font-medium
                           text-white shadow-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                          d="M17 16l4-4m0 0l-4-4m4 4H7"/>
                </svg>
                <span class="text-xs lg:text-sm">Keluar</span>
            </button>
        </form>
    </div>

</aside>


    @endauth

    <!-- MAIN CONTENT -->
    <div class="flex-1 flex flex-col overflow-hidden">
        @auth
        <div class="lg:hidden bg-amber-800 p-4">
            <button @click="sidebarOpen = true" class="text-white">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
        @endauth

        <header class="bg-white shadow-sm border-b">
            <div class="w-full flex justify-between items-center px-4 sm:px-6 lg:px-8 py-5">
                <h1 class="text-2xl font-bold text-amber-800">@yield('title', 'E-Lantera')</h1>

                @auth
                <div class="text-gray-700">
                    Halo, <span class="font-bold text-amber-700">{{ auth()->user()->name }}</span>
                </div>
                @endauth
            </div>
        </header>


        <main class="flex-1 overflow-y-auto bg-gray-50">
            <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
                @include('sweetalert::alert')
                @yield('content')
            </div>
        </main>

        <!-- Footer Simple -->
        <footer class="bg-white border-t border-gray-200 py-6 mt-auto">
            <div class="max-w-7xl mx-auto px-6 text-center text-sm text-gray-600">
                © {{ date('Y') }} E-Lantera — Digitalisasi Posyandu Taman Cipulir Estate<br>
                Dibuat dengan <span class="text-red-600">♥</span> oleh Developer MRJ TCE
            </div>
        </footer>
    </div>
</div>

<!-- Scripts Global (hanya yang penting) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.tailwindcss.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.html5.min.js"></script>
<script>
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });
</script>
@stack('scripts')
</body>
</html>