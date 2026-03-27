@php
    $currentLocale = app()->getLocale();
    $isRtl = $currentLocale === 'ar';
@endphp
<!DOCTYPE html>
<html class="light" lang="{{ str_replace('_', '-', $currentLocale) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>OpticVault | @yield('title', __('vault.audit_center'))</title>

    {{-- Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Manrope:wght@700;800{{ $isRtl ? '&family=Cairo:wght@600;700;800' : '' }}&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

    {{-- Tailwind CSS CDN — NO Vue.js, NO Vite --}}
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#000000",
                        "surface": "#f7f9fb",
                        "on-surface": "#191c1e",
                        "on-surface-variant": "#45464d",
                        "outline": "#76777d",
                        "outline-variant": "#c6c6cd",
                        "error": "#ba1a1a",
                        "error-container": "#ffdad6",
                        "on-error-container": "#93000a",
                        "surface-container-low": "#f2f4f6",
                        "surface-container-lowest": "#ffffff",
                        "surface-container": "#eceef0",
                        "surface-container-high": "#e6e8ea",
                    },
                    fontFamily: {
                        "headline": ["{{ $isRtl ? 'Cairo' : 'Manrope' }}", "sans-serif"],
                        "body": ["{{ $isRtl ? 'Cairo' : 'Inter' }}", "sans-serif"],
                    },
                },
            },
        }
    </script>

    {{-- Alpine.js for interactivity --}}
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            display: inline-block;
            line-height: 1;
            text-transform: none;
            letter-spacing: normal;
            word-wrap: normal;
            white-space: nowrap;
            direction: ltr; /* Symbols stay LTR */
        }
        .material-symbols-outlined.filled {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #c6c6cd; border-radius: 10px; }
        
        * { transition-property: color, background-color, border-color, opacity, box-shadow, transform; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms; }
        
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .pulse-dot { animation: pulse-dot 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    </style>
</head>
<body class="bg-surface font-body text-on-surface">

    {{-- ═══════════ Sidebar Navigation ═══════════ --}}
    <aside class="fixed inset-y-0 {{ $isRtl ? 'right-0' : 'left-0' }} z-50 flex flex-col h-screen w-64 bg-slate-900 shadow-2xl shadow-slate-950/20">
        {{-- Brand --}}
        <div class="px-8 py-8">
            <div class="text-xl font-extrabold tracking-tighter text-white font-headline">{{ __('vault.brand') }}</div>
            <div class="text-xs font-medium text-slate-400 mt-1 uppercase tracking-widest">{{ __('vault.audit_center') }}</div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 mt-4">
            <a href="{{ route('vault.dashboard') }}"
               class="flex items-center px-6 py-4 transition-all
                      {{ request()->routeIs('vault.dashboard') 
                         ? 'bg-blue-600/10 text-blue-400 font-semibold ' . ($isRtl ? 'border-l-4 border-blue-500' : 'border-r-4 border-blue-500')
                         : 'text-slate-400 hover:text-white hover:bg-slate-800/50' }}">
                <span class="material-symbols-outlined {{ $isRtl ? 'ml-4' : 'mr-4' }}">dashboard</span>
                <span class="text-sm font-medium">{{ __('vault.overview') }}</span>
            </a>

            <a href="{{ route('vault.operations') }}"
               class="flex items-center px-6 py-4 transition-all
                      {{ request()->routeIs('vault.operations') 
                         ? 'bg-blue-600/10 text-blue-400 font-semibold ' . ($isRtl ? 'border-l-4 border-blue-500' : 'border-r-4 border-blue-500')
                         : 'text-slate-400 hover:text-white hover:bg-slate-800/50' }}">
                <span class="material-symbols-outlined {{ $isRtl ? 'ml-4' : 'mr-4' }}">settings_applications</span>
                <span class="text-sm font-medium">{{ __('vault.operations') }}</span>
            </a>

            <a href="{{ route('vault.users') }}"
               class="flex items-center px-6 py-4 transition-all
                      {{ request()->routeIs('vault.users') 
                         ? 'bg-blue-600/10 text-blue-400 font-semibold ' . ($isRtl ? 'border-l-4 border-blue-500' : 'border-r-4 border-blue-500')
                         : 'text-slate-400 hover:text-white hover:bg-slate-800/50' }}">
                <span class="material-symbols-outlined {{ $isRtl ? 'ml-4' : 'mr-4' }}">group</span>
                <span class="text-sm font-medium">{{ __('vault.users') }}</span>
            </a>

            <a href="{{ route('vault.security') }}"
               class="flex items-center px-6 py-4 transition-all
                      {{ request()->routeIs('vault.security') 
                         ? 'bg-blue-600/10 text-blue-400 font-semibold ' . ($isRtl ? 'border-l-4 border-blue-500' : 'border-r-4 border-blue-500')
                         : 'text-slate-400 hover:text-white hover:bg-slate-800/50' }}">
                <span class="material-symbols-outlined {{ $isRtl ? 'ml-4' : 'mr-4' }}">security</span>
                <span class="text-sm font-medium">{{ __('vault.security') }}</span>
            </a>

            {{-- Divider + Back to main admin --}}
            <div class="mt-6 mx-6 border-t border-slate-800"></div>
            <a href="{{ route('admin.dashboard') }}"
               class="flex items-center px-6 py-4 text-slate-500 hover:text-slate-300 hover:bg-slate-800/50 transition-all">
                <span class="material-symbols-outlined {{ $isRtl ? 'ml-4' : 'mr-4' }} {{ $isRtl ? 'rotate-180' : '' }}">arrow_back</span>
                <span class="text-sm font-medium">{{ __('vault.back_to_admin') }}</span>
            </a>
            <a href="{{ route('dashboard') }}"
               class="flex items-center px-6 py-4 text-slate-500 hover:text-slate-300 hover:bg-slate-800/50 transition-all">
                <span class="material-symbols-outlined {{ $isRtl ? 'ml-4' : 'mr-4' }}">home</span>
                <span class="text-sm font-medium">{{ __('vault.main_app') }}</span>
            </a>
        </nav>

        {{-- User Profile --}}
        <div class="p-6 border-t border-slate-800">
            <div class="flex items-center gap-3">
                <img alt="Admin" class="w-10 h-10 rounded-full bg-slate-700 object-cover" 
                     src="{{ auth()->user()->avatar }}"/>
                <div>
                    <div class="text-sm font-bold text-white">{{ auth()->user()->name }}</div>
                    <div class="text-[10px] text-slate-500 uppercase tracking-tighter">{{ __('vault.system_auditor') }}</div>
                </div>
            </div>
        </div>
    </aside>

    {{-- ═══════════ Main Content Area ═══════════ --}}
    <main class="{{ $isRtl ? 'mr-64' : 'ml-64' }} min-h-screen bg-surface flex flex-col">

        {{-- Top Navigation Bar --}}
        <header class="flex justify-between items-center w-full h-16 px-8 sticky top-0 bg-white/80 backdrop-blur-xl border-b border-slate-200/50 z-40">
            <div class="flex items-center flex-1 max-w-xl">
                <form method="GET" action="{{ request()->url() }}" class="relative w-full group">
                    <span class="material-symbols-outlined absolute {{ $isRtl ? 'right-3' : 'left-3' }} top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                    <input name="search" value="{{ request('search') }}"
                           class="w-full bg-surface-container-low border-none rounded-xl {{ $isRtl ? 'pr-10 pl-4' : 'pl-10 pr-4' }} py-2 text-sm focus:ring-2 focus:ring-blue-500/20 transition-all" 
                           placeholder="{{ __('vault.search_placeholder') }}" 
                           type="text"/>
                </form>
            </div>
            <div class="flex items-center gap-6">
                {{-- Language Switcher --}}
                <div class="flex items-center bg-slate-100 p-1 rounded-lg">
                    <a href="?lang=en" class="px-3 py-1 text-[10px] font-bold rounded-md transition-all {{ $currentLocale === 'en' ? 'bg-white shadow-sm text-blue-600' : 'text-slate-500 hover:text-slate-700' }}">EN</a>
                    <a href="?lang=ar" class="px-3 py-1 text-[10px] font-bold rounded-md transition-all {{ $currentLocale === 'ar' ? 'bg-white shadow-sm text-blue-600' : 'text-slate-500 hover:text-slate-700' }}">AR</a>
                </div>

                <div class="flex items-center gap-4">
                    <a href="{{ route('vault.security') }}" class="text-slate-500 hover:bg-slate-50 p-2 rounded-lg transition-colors relative">
                        <span class="material-symbols-outlined">notifications</span>
                        @if(isset($criticalCount) && $criticalCount > 0)
                            <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                        @endif
                    </a>
                </div>
                <div class="h-8 w-px bg-slate-200"></div>
                <div class="{{ $isRtl ? 'text-left' : 'text-right' }}">
                    <h1 class="text-sm font-bold font-headline text-slate-900 leading-none">@yield('page-title', __('vault.overview'))</h1>
                    <p class="text-[10px] uppercase tracking-widest text-slate-500 mt-1">@yield('page-subtitle', __('vault.health_dashboard'))</p>
                </div>
            </div>
        </header>

        {{-- Page Content --}}
        <div class="p-8 space-y-8 flex-1">
            @yield('content')
        </div>
    </main>

    @stack('scripts')
</body>
</html>

