@extends('layouts.premium')

@section('title', 'Smart Timer')

@section('content')
<div class="h-full flex flex-col items-center justify-center space-y-8 text-center" dir="rtl">
    <div class="w-24 h-24 rounded-[32px] glass flex items-center justify-center text-4xl shadow-2xl relative">
        ⏱️
        <div class="absolute -top-2 -right-2 w-6 h-6 bg-purple-500 rounded-full border-4 border-[#0a0a0c] animate-pulse"></div>
    </div>
    
    <div class="max-w-md space-y-4">
        <h2 class="text-3xl font-bold tracking-tight">المؤقت <span class="accent-text-gradient">الذكي</span></h2>
        <p class="text-gray-400 leading-relaxed">
            قريباً ستتمكن من تتبع وقتك وإدارة جلسات التركيز ومعالجة الصور بشكل مؤتمت. ميزة حصرية لمشتركي الفئة الممتازة!
        </p>
    </div>

    <div class="flex gap-4">
        <a href="{{ route('home_new') }}" class="text-xs font-bold text-purple-400 hover:text-purple-300 transition-colors uppercase tracking-widest">Back to Control Center ←</a>
        <button class="px-8 py-3 rounded-2xl accent-gradient hover:opacity-90 transition-all text-sm font-bold tracking-wide shadow-lg shadow-purple-500/20">
            تفعيل الإشعارات
        </button>
    </div>

    <!-- Decorative Elements -->
    <div class="fixed bottom-0 right-0 w-64 h-64 bg-indigo-600/5 blur-[120px] rounded-full -z-10"></div>
    <div class="fixed top-20 left-0 w-96 h-96 bg-purple-600/5 blur-[120px] rounded-full -z-10"></div>
</div>
@endsection
