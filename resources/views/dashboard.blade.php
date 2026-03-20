@extends('layouts.premium')

@section('title', 'Control Center')

@section('content')
<div class="space-y-8" x-data="{ }">
    
    <!-- Welcome Header -->
    <div class="flex justify-between items-end">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">مرحباً <span class="accent-text-gradient">{{ auth()->user()->name }}</span></h2>
            <p class="text-gray-400 mt-1">نظرة عامة على نشاط مستودعك الرقمي اليوم.</p>
        </div>
        <div class="flex items-center gap-4 bg-white/5 p-2 rounded-full px-4 border border-white/10">
            <span class="text-[10px] font-bold text-purple-400 uppercase tracking-widest">Premium Account</span>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="glass p-8 rounded-[40px] flex flex-col gap-2 group hover:bg-white/5 transition-all">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">إجمالي الصور</p>
            <h3 class="text-4xl font-bold italic">{{ auth()->user()->images->count() }}</h3>
            <div class="mt-4 flex items-center gap-2">
                <div class="w-full h-1 bg-white/5 rounded-full overflow-hidden">
                    <div class="h-full bg-purple-500 w-[65%]"></div>
                </div>
                <span class="text-[8px] text-purple-400 font-bold font-mono">65%</span>
            </div>
        </div>
        <div class="glass p-8 rounded-[40px] flex flex-col gap-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">الألبومات</p>
            <h3 class="text-4xl font-bold italic">{{ auth()->user()->ownedAlbums->count() }}</h3>
            <p class="text-[8px] text-gray-600 mt-2 uppercase tracking-tighter">Photography Collections</p>
        </div>
        <div class="glass p-8 rounded-[40px] flex flex-col gap-2 border-green-500/10 border">
            <p class="text-[10px] font-bold text-green-500 uppercase tracking-widest">التوفير بالضغط</p>
            <h3 class="text-4xl font-bold italic">-64%</h3>
            <p class="text-[8px] text-green-500/50 mt-2 uppercase tracking-tighter">Bandwidth Saved</p>
        </div>
        <div class="glass p-8 rounded-[40px] flex flex-col gap-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">الروابط النشطة</p>
            <h3 class="text-4xl font-bold italic">12</h3>
            <p class="text-[8px] text-gray-600 mt-2 uppercase tracking-tighter">Shared Assets</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Quick Actions Panel -->
        <div class="lg:col-span-4 glass p-8 rounded-[40px] space-y-8">
            <h3 class="text-xs font-bold uppercase tracking-widest text-purple-400">الوصول السريع</h3>
            
            <div class="grid grid-cols-2 gap-4">
                <a href="{{ route('images.index') }}" class="glass-dark hover:bg-white/5 border border-white/5 p-6 rounded-3xl flex flex-col items-center gap-3 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-purple-500/10 flex items-center justify-center text-purple-400 group-hover:bg-purple-500 group-hover:text-white transition-all">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-widest">رفع صور</span>
                </a>
                <a href="{{ route('images.gallery') }}" class="glass-dark hover:bg-white/5 border border-white/5 p-6 rounded-3xl flex flex-col items-center gap-3 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-400 group-hover:bg-blue-500 group-hover:text-white transition-all">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-widest">المعرض</span>
                </a>
                <a href="{{ route('lab.index') }}" class="glass-dark hover:bg-white/5 border border-white/5 p-6 rounded-3xl flex flex-col items-center gap-3 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/10 flex items-center justify-center text-amber-400 group-hover:bg-amber-500 group-hover:text-white transition-all">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-widest">المختبر</span>
                </a>
                <a href="{{ route('profile.edit') }}" class="glass-dark hover:bg-white/5 border border-white/5 p-6 rounded-3xl flex flex-col items-center gap-3 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 flex items-center justify-center text-indigo-400 group-hover:bg-indigo-500 group-hover:text-white transition-all">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-widest">الإعدادات</span>
                </a>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="lg:col-span-8 glass p-8 rounded-[40px]">
             <div class="flex justify-between items-center mb-8">
                <h3 class="text-xs font-bold uppercase tracking-widest text-purple-400">النشاط الأخير</h3>
                <a href="{{ route('activities.index') }}" class="text-[10px] uppercase font-bold text-gray-500 hover:text-white transition-colors">عرض الكل ←</a>
             </div>

             <div class="space-y-6">
                @foreach(auth()->user()->images()->latest()->take(5)->get() as $activity)
                    <div class="flex items-center gap-6 p-4 rounded-3xl hover:bg-white/5 transition-all border border-transparent hover:border-white/5">
                        <div class="w-12 h-12 rounded-2xl overflow-hidden glass border border-white/10">
                            <img src="{{ $activity->url }}" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-bold truncate">تم رفع وإكمال معالجة صورة "{{ $activity->title ?? $activity->filename }}"</p>
                            <p class="text-[10px] text-gray-500 font-mono mt-1 uppercase">{{ $activity->created_at->diffForHumans() }} • Success</p>
                        </div>
                        <div class="px-3 py-1 bg-green-500/10 text-green-500 text-[8px] font-bold uppercase italic rounded-full">Optimized</div>
                    </div>
                @endforeach
             </div>
        </div>

    </div>

</div>
@endsection