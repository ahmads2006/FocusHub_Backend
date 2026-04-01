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
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.0.1/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Initialize Laravel Echo with Reverb (using Pusher compatibility mode)
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: '{{ config('broadcasting.connections.reverb.key') }}',
            wsHost: window.location.hostname,
            wsPort: {{ config('broadcasting.connections.reverb.options.port', 8080) }},
            forceTLS: false,
            encrypted: false,
            cluster: 'mt1',
            enabledTransports: ['ws', 'wss'],
            debug: false,
        });

        // Connection monitoring (safe check)
        const monitorEcho = setInterval(() => {
            if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
                window.Echo.connector.pusher.connection.bind('connected', () => {
                    console.log('✅ Real-time notifications connected!');
                });
                clearInterval(monitorEcho);
            }
        }, 500);
    </script>

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
        <header class="h-20 border-b border-white/5 flex items-center justify-between px-8 z-10" 
                x-data="notificationSystem()" x-init="init()">
            <div class="flex items-center flex-1">
                <div class="relative w-full max-w-md">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" placeholder="Search your vault..." class="w-full bg-white/5 border border-white/10 rounded-full py-2 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500/50 transition-all">
                </div>
            </div>

            <div class="flex items-center gap-6">
                <!-- Notifications Dropdown -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open; if(open) fetchNotifications()" class="relative p-2 text-gray-400 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <template x-if="unreadCount > 0">
                            <span class="absolute top-2 right-2 w-2 h-2 bg-purple-500 rounded-full border border-[#0a0a0c]"></span>
                        </template>
                    </button>

                    <div x-show="open" @click.away="open = false" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute right-0 mt-2 w-80 glass-dark rounded-2xl shadow-2xl overflow-hidden z-50 border border-white/10">
                        <div class="p-4 border-b border-white/5 flex justify-between items-center bg-white/5">
                            <h3 class="font-bold text-sm">Notifications</h3>
                            <button @click="markAllAsRead()" x-show="unreadCount > 0" class="text-[10px] text-purple-400 hover:text-purple-300 uppercase font-bold tracking-wider">Mark all read</button>
                        </div>
                        <div class="max-h-96 overflow-y-auto custom-scrollbar">
                            <template x-if="notifications.length === 0">
                                <div class="p-8 text-center text-gray-500 italic text-sm">
                                    No notifications yet.
                                </div>
                            </template>
                            <template x-for="notif in notifications" :key="notif.id">
                                <div class="p-4 border-b border-white/5 hover:bg-white/5 transition-colors relative group"
                                     :class="!notif.read_at ? 'bg-purple-500/5' : ''">
                                    
                                    <div class="flex gap-3">
                                        <div class="flex-1">
                                            <p class="text-xs text-gray-200" x-text="notif.data.message"></p>
                                            <p class="text-[10px] text-gray-500 mt-1" x-text="formatDate(notif.created_at)"></p>
                                        </div>
                                        <button @click.stop="deleteNotification(notif.id)" class="opacity-0 group-hover:opacity-100 p-1 text-gray-500 hover:text-red-500 transition-all">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>

                                    <!-- Action Buttons for Invitations -->
                                    <template x-if="notif.data.type === 'album_invitation'">
                                        <div class="mt-3">
                                            <div class="flex gap-2" x-show="!notif.responded">
                                                <button @click.stop="respondToInvitation(notif, 'accept')" 
                                                        class="px-3 py-1 bg-purple-600 hover:bg-purple-500 text-white text-[10px] font-bold rounded-lg transition-all">
                                                    Accept
                                                </button>
                                                <button @click.stop="respondToInvitation(notif, 'decline')" 
                                                        class="px-3 py-1 bg-white/10 hover:bg-white/20 text-white text-[10px] font-bold rounded-lg transition-all">
                                                    Decline
                                                </button>
                                            </div>
                                            <div x-show="notif.responded" x-cloak>
                                                <template x-if="notif.responseStatus === 'accept'">
                                                    <span class="text-emerald-500 flex items-center gap-1 text-[11px] font-bold bg-emerald-500/10 px-2 py-1 rounded w-max">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                        تم قبول الدعوة بنجاح
                                                    </span>
                                                </template>
                                                <template x-if="notif.responseStatus === 'decline'">
                                                    <span class="text-red-500 flex items-center gap-1 text-[11px] font-bold bg-red-500/10 px-2 py-1 rounded w-max">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                        تم الرفض
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Link to action -->
                                    <template x-if="notif.data.action_url && notif.data.type !== 'album_invitation'">
                                        <a :href="notif.data.action_url" @click="markAsRead(notif.id)" class="absolute inset-0 z-0"></a>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast Container -->
            <div class="fixed top-4 right-4 z-[9999] space-y-2 pointer-events-none">
                <template x-for="toast in toasts" :key="toast.id">
                    <div x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-x-8"
                         x-transition:enter-end="opacity-100 translate-x-0"
                         x-transition:leave="transition ease-in duration-300"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="glass-dark border-l-4 border-purple-500 p-4 w-72 shadow-2xl pointer-events-auto rounded-xl flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-purple-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs font-bold text-white uppercase tracking-wider">New Notification</p>
                            <p class="text-[11px] text-gray-400" x-text="toast.message"></p>
                        </div>
                        <button @click="removeToast(toast.id)" class="text-gray-500 hover:text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </template>
            </div>
        </header>

        <!-- Main View Area -->
        <main class="flex-1 overflow-y-auto p-4 md:p-8">
            <div class="max-w-7xl mx-auto h-full">
                @yield('content')
            </div>
        </main>
        
        <script>
            function notificationSystem() {
                return {
                    notifications: [],
                    unreadCount: 0,
                    toasts: [],
                    lastShownId: null,

                    init() {
                        this.fetchNotifications(true);
                        
                        // Listen for private notifications via Reverb (wait for Echo readiness)
                        @auth
                        const checkEcho = setInterval(() => {
                            if (window.Echo && typeof window.Echo.private === 'function') {
                                window.Echo.private('App.Models.User.{{ auth()->id() }}')
                                    .notification((notification) => {
                                        console.log('New notification received:', notification);
                                        
                                        this.notifications.unshift({
                                            id: notification.id,
                                            data: notification.data,
                                            read_at: null,
                                            created_at: 'Just now'
                                        });
                                        
                                        this.unreadCount++;
                                        this.showToast(notification.data.message);
                                    });
                                clearInterval(checkEcho);
                            }
                        }, 500);
                        @endauth
                    },

                    fetchNotifications(isInitial = false) {
                        fetch('{{ route('notifications.index') }}')
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    this.notifications = data.notifications;
                                    this.unreadCount = data.unread_count;
                                }
                            });
                    },

                    markAsRead(id) {
                        fetch(`/notifications/mark-read/${id}`, {
                            method: 'POST',
                            headers: { 
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        }).then(() => {
                            this.notifications = this.notifications.map(n => n.id === id ? {...n, read_at: new Date()} : n);
                            this.unreadCount = Math.max(0, this.unreadCount - 1);
                        });
                    },

                    markAllAsRead() {
                        fetch('{{ route('notifications.mark-all-as-read') }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                        }).then(() => {
                            this.notifications = this.notifications.map(n => ({...n, read_at: new Date()}));
                            this.unreadCount = 0;
                        });
                    },

                    deleteNotification(id) {
                        fetch(`/notifications/${id}`, {
                            method: 'DELETE',
                            headers: { 
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        }).then(() => {
                            this.notifications = this.notifications.filter(n => n.id !== id);
                            this.unreadCount = this.notifications.filter(n => !n.read_at).length;
                        });
                    },

                    respondToInvitation(notif, action) {
                        const albumId = notif.data.album_id;
                        const url = action === 'accept' ? `/albums/${albumId}/invitation/accept` : `/albums/${albumId}/invitation/decline`;
                        
                        fetch(url, {
                            method: 'POST',
                            headers: { 
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        }).then(res => res.json()).then(data => {
                            if (data.success) {
                                // Show local UI state change instead of deleting
                                notif.responded = true;
                                notif.responseStatus = action;
                                this.markAsRead(notif.id);
                                
                                Swal.fire({
                                    icon: 'success',
                                    title: action === 'accept' ? 'تم القبول!' : 'تم الرفض',
                                    text: data.message,
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: true,
                                    confirmButtonText: 'OK',
                                    background: '#1a1a1c',
                                    color: '#fff'
                                });
                            }
                        });
                    },

                    showToast(message) {
                        const id = Date.now();
                        this.toasts.push({ id, message });
                    },

                    removeToast(id) {
                        this.toasts = this.toasts.filter(t => t.id !== id);
                    },

                    formatDate(dateString) {
                        if (!dateString) return '';
                        const date = new Date(dateString);
                        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    }
                }
            }
        </script>

        {{-- Scripts Stack & Hooks --}}
        @stack('scripts')
        @yield('scripts')
    </div>

</body>
</html>
