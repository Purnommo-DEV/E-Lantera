<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Posyandu TCE</title>

    @vite('resources/css/app.css')

    <style>
        /* Animasi halus */
        .fade-in {
            animation: fadeIn 0.6s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-yellow-400 via-orange-500 to-rose-500 flex items-center justify-center px-4">

    <div class="w-full max-w-md">
        
        <!-- Logo + Judul -->
        <div class="text-center mb-8 fade-in">
            <div
                class="bg-white/90 w-20 h-20 rounded-2xl flex items-center justify-center mx-auto backdrop-blur-lg shadow-lg">
                <img
                    src="{{ asset('elantera.png') }}"
                    alt="E-Lantera"
                    class="w-24 h-24 object-contain">
            </div>

            <h2 class="text-3xl font-bold text-white mt-4 drop-shadow">
                Posyandu Taman Cipulir Estate
            </h2>

            <p class="text-white/80 mt-1 text-sm">
                Akses dashboard pemeriksaan warga
            </p>

            {{-- Optional tagline --}}
            <p class="text-white/70 mt-1 text-xs italic">
                Lansia Sehat di Era Digital
            </p>
        </div>

        <!-- Card Login -->
        <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-2xl p-8 fade-in">

            <!-- Session Status -->
            @if (session('status'))
                <div class="mb-4 p-3 rounded-lg bg-green-100 text-green-700 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Email -->
                <label class="block text-gray-700 font-semibold">Email</label>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    class="mt-1 w-full rounded-xl border-gray-300 focus:border-orange-500 focus:ring-orange-500"
                    required
                    autofocus
                >
                @error('email')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror

                <!-- Password -->
                <label class="block mt-4 text-gray-700 font-semibold">Password</label>
                <input
                    type="password"
                    name="password"
                    class="mt-1 w-full rounded-xl border-gray-300 focus:border-orange-500 focus:ring-orange-500"
                    required
                >
                @error('password')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror

                <!-- Remember -->
                <div class="flex items-center mt-4">
                    <input id="remember_me" type="checkbox"
                        class="rounded border-gray-300 text-orange-500 focus:ring-orange-500"
                        name="remember">

                    <label for="remember_me" class="ml-2 text-gray-700 text-sm">
                        Ingat saya
                    </label>
                </div>

                <!-- Tombol Login -->
                <button
                    type="submit"
                    class="w-full mt-6 py-3 rounded-xl bg-gradient-to-r from-orange-500 to-yellow-500 text-white font-bold shadow-lg hover:shadow-xl hover:scale-[1.02] transition"
                >
                    Masuk
                </button>

                <!-- Lupa Password -->
                @if (Route::has('password.request'))
                    <div class="text-right mt-3">
                        <a href="{{ route('password.request') }}"
                           class="text-sm text-orange-600 hover:text-orange-700 font-semibold">
                            Lupa password?
                        </a>
                    </div>
                @endif

            </form>
        </div>

        <!-- Footer -->
        <p class="text-center text-white/70 text-sm mt-6 fade-in">
            &copy; {{ date('Y') }} Posyandu TCE • Sistem Pemeriksaan Warga
        </p>

    </div>

    <script src="https://kit.fontawesome.com/a2d9d6c66f.js" crossorigin="anonymous"></script>

</body>
</html>
