<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'FocusHub') }} | @yield('title', 'Professional Image Lab')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">

    <!-- CSS / JS (Using CDN for speed and consistency with mockup) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { font-family: 'Outfit', sans-serif; background: #0a0a0c; color: #e1e1e6; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-dark { background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .accent-gradient { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); }
        .accent-text-gradient { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .glow:hover { box-shadow: 0 0 25px rgba(168, 85, 247, 0.3); }
        [x-cloak] { display: none !important; }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #0a0a0c; }
        ::-webkit-scrollbar-thumb { background: #1f1f23; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #2f2f35; }

        .nav-item-active { background: rgba(168, 85, 247, 0.1); border-left: 3px solid #a855f7; color: white; }
    </style>
</head>
<body class="antialiased min-h-screen flex overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-64 glass-dark border-r border-white/5 flex flex-col hidden lg:flex">
        <div class="p-8">
            <h1 class="text-2xl font-bold tracking-tight">Focus<span class="text-purple-500">Hub</span></h1>
        </div>

        <nav class="flex-1 px-4 space-y-2 overflow-y-auto">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-4 p-3 px-4 rounded-xl text-gray-400 hover:bg-white/5 hover:text-white transition-all {{ request()->routeIs('dashboard') ? 'nav-item-active' : '' }}">
                <svg class="w-5 h-5 font-bold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                <span class="font-medium text-sm">Dashboard</span>
            </a>
            <a href="{{ route('images.index') }}" class="flex items-center gap-4 p-3 px-4 rounded-xl text-gray-400 hover:bg-white/5 hover:text-white transition-all {{ request()->routeIs('images.index') && request()->query('view') !== 'albums' ? 'nav-item-active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span class="font-medium text-sm">My Vault</span>
            </a>
          
            <a href="{{ route('images.bulk') }}" class="flex items-center gap-4 p-3 px-4 rounded-xl text-gray-400 hover:bg-white/5 hover:text-white transition-all {{ request()->routeIs('images.bulk') ? 'nav-item-active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                <span class="font-medium text-sm">Professional Bulk Lab</span>
            </a>
           
            <a href="{{ route('images.gallery') }}" class="flex items-center gap-4 p-3 px-4 rounded-xl text-gray-400 hover:bg-white/5 hover:text-white transition-all {{ request()->routeIs('images.gallery') ? 'nav-item-active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <span class="font-medium text-sm">Public Gallery</span>
            </a>

            <a href="{{ route('chat.hub') }}" class="flex items-center gap-4 p-3 px-4 rounded-xl text-gray-400 hover:bg-white/5 hover:text-white transition-all {{ request()->routeIs('chat.hub') ? 'nav-item-active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                <span class="font-medium text-sm">Photographers Hub</span>
            </a>

            <a href="{{ route('profile.show', auth()->user()) }}" class="flex items-center gap-4 p-3 px-4 rounded-xl text-gray-400 hover:bg-white/5 hover:text-white transition-all {{ request()->routeIs('profile.show') ? 'nav-item-active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                <span class="font-medium text-sm">My Public Profile</span>
            </a>
          
           
           
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-4 p-3 px-4 rounded-xl text-gray-400 hover:bg-white/5 hover:text-white transition-all {{ request()->routeIs('profile.edit') ? 'nav-item-active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span class="font-medium">Settings</span>
            </a>

            @if(auth()->user()->role === 'super_admin')
            <div class="pt-4 mt-4 border-t border-white/5">
                <p class="px-4 mb-2 text-[10px] font-bold text-gray-500 uppercase tracking-[0.2em]">Management</p>
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-4 p-3 px-4 rounded-xl text-purple-400 hover:bg-purple-500/10 hover:text-purple-300 transition-all {{ request()->routeIs('admin.dashboard') ? 'nav-item-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    <span class="font-bold text-sm tracking-tight">Admin Dash</span>
                </a>
                <a href="{{ route('admin.photos.index') }}" class="flex items-center gap-4 p-3 px-4 rounded-xl text-amber-400 hover:bg-amber-500/10 hover:text-amber-300 transition-all {{ request()->routeIs('admin.photos.*') ? 'nav-item-active' : '' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span class="font-bold text-sm tracking-tight">Moderation</span>
                </a>
            </div>
            @endif
        </nav>

        <div class="p-6 border-t border-white/5">
            <div class="flex items-center gap-3">
                <img src="{{ auth()->user()->avatar ?? 'https://ui-avatars.com/api/?name=User' }}" class="w-10 h-10 rounded-full border border-purple-500/50">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate">{{ auth()->user()->name }}</p>
                    <p class="text-[10px] text-gray-500 truncate uppercase">Premium Client</p>
                </div>
                <a href="{{ route('logout') }}" class="text-gray-500 hover:text-red-500 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden">
        
        <!-- Top Navigation -->
        <header class="h-20 border-b border-white/5 flex items-center justify-between px-8 z-10">
            <div class="flex items-center flex-1">
                <div class="relative w-full max-w-md">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" placeholder="Search your vault..." class="w-full bg-white/5 border border-white/10 rounded-full py-2 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500/50 transition-all">
                </div>
            </div>

            <div class="flex items-center gap-6">
                <button class="relative p-2 text-gray-400 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <span class="absolute top-2 right-2 w-2 h-2 bg-purple-500 rounded-full border border-[#0a0a0c]"></span>
                </button>
            </div>
        </header>

        <!-- Main View Area -->
        <main class="flex-1 overflow-y-auto p-4 md:p-8">
            <div class="max-w-7xl mx-auto h-full">
                @yield('content')
            </div>
        </main>
        {{-- Scripts Stack & Hooks --}}
        @stack('scripts')
        @yield('scripts')
    </div>

</body>
</html>
