<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'OpticVault') }} | Access</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            body { font-family: 'Outfit', sans-serif; background: #050505; color: #fff; overflow-x: hidden; }
            .bg-glow { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none; overflow: hidden; }
            .glow-primary { position: absolute; top: -10%; right: -5%; width: 50%; height: 60%; background: radial-gradient(circle, rgba(168, 85, 247, 0.15) 0%, transparent 70%); }
            .glow-secondary { position: absolute; bottom: -10%; left: -5%; width: 50%; height: 60%; background: radial-gradient(circle, rgba(59, 130, 246, 0.1) 0%, transparent 70%); }
            .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
            .accent-gradient { background: linear-gradient(135deg, #a855f7 0%, #3b82f6 100%); }
        </style>
    </head>
    <body class="antialiased selection:bg-purple-500/30">
        <div class="bg-glow">
            <div class="glow-primary"></div>
            <div class="glow-secondary"></div>
        </div>

        <div class="min-h-screen flex flex-col sm:justify-center items-center p-6 sm:pt-0">
            <div class="mb-12">
                <a href="/" class="flex flex-col items-center gap-4 group">
                    <div class="w-20 h-20 rounded-[30px] accent-gradient p-[1px] shadow-2xl shadow-purple-500/20 group-hover:scale-105 transition-transform duration-500">
                        <div class="w-full h-full bg-[#050505] rounded-[29px] flex items-center justify-center">
                            <span class="text-3xl">🛡️</span>
                        </div>
                    </div>
                    <h1 class="text-2xl font-bold tracking-[0.2em] text-white italic uppercase">Optic<span class="text-purple-500">Vault</span></h1>
                </a>
            </div>

            <div class="w-full sm:max-w-md glass p-10 rounded-[48px] shadow-2xl overflow-hidden relative">
                <div class="absolute top-0 right-0 w-32 h-32 bg-purple-500/5 blur-[60px]"></div>
                {{ $slot }}
            </div>

            <div class="mt-8 text-center">
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-[0.3em]">Advanced Digital Sanctum &copy; {{ date('Y') }}</p>
            </div>
        </div>
    </body>
</html>
