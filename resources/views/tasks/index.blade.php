@extends('layouts.premium')

@section('title', 'Daily Tasks')

@section('content')
<div class="h-full flex flex-col items-center justify-center space-y-8 text-center" dir="rtl">
    <div class="w-24 h-24 rounded-[32px] glass flex items-center justify-center text-4xl shadow-2xl relative">
        📝
        <div class="absolute -top-2 -right-2 w-6 h-6 bg-purple-500 rounded-full border-4 border-[#0a0a0c] animate-pulse"></div>
    </div>
    
    <div class="max-w-md space-y-4">
        <h2 class="text-3xl font-bold tracking-tight">المهام <span class="accent-text-gradient">اليومية</span></h2>
        <p class="text-gray-400 leading-relaxed">
            نحن نعمل على تطوير نظام متكامل لإدارة مهامك اليومية وجدولة عمليات المعالجة. ترقبوا التحديث القادم في النسخة الاحترافية!
        </p>
    </div>

    <div class="flex gap-4">
        <a href="{{ route('home_new') }}" class="text-xs font-bold text-purple-400 hover:text-purple-300 transition-colors uppercase tracking-widest">Back to Control Center ←</a>
        <button class="px-8 py-3 rounded-2xl accent-gradient hover:opacity-90 transition-all text-sm font-bold tracking-wide shadow-lg shadow-purple-500/20">
            تنبيهي عند الإطلاق
        </button>
    </div>

    <!-- Decorative Elements -->
    <div class="fixed bottom-0 left-0 w-64 h-64 bg-purple-600/5 blur-[120px] rounded-full -z-10"></div>
    <div class="fixed top-20 right-0 w-96 h-96 bg-indigo-600/5 blur-[120px] rounded-full -z-10"></div>
</div>
@endsection
