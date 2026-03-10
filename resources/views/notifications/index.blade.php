@extends('layouts.premium')

@section('title', 'Notifications Center')

@section('content')
<div class="h-full flex flex-col items-center justify-center space-y-8 text-center" dir="rtl">
    <div class="w-24 h-24 rounded-[32px] glass flex items-center justify-center text-4xl shadow-2xl relative">
        🔔
        <div class="absolute -top-2 -right-2 w-6 h-6 bg-purple-500 rounded-full border-4 border-[#0a0a0c]"></div>
    </div>
    
    <div class="max-w-md space-y-4">
        <h2 class="text-3xl font-bold tracking-tight">مركز <span class="accent-text-gradient">التنبيهات</span></h2>
        <p class="text-gray-400 leading-relaxed">
            حسابك محدث بالكامل! ستظهر هنا كافة التنبيهات المتعلقة بنشاطات الألبومات المشتركة ومعالجة الصور الذكية.
        </p>
    </div>

    <div class="flex gap-4">
        <a href="{{ route('home_new') }}" class="text-xs font-bold text-purple-400 hover:text-purple-300 transition-colors uppercase tracking-widest">Back to Control Center ←</a>
        <button class="px-8 py-3 rounded-2xl glass border border-white/10 hover:text-white transition-all text-sm font-bold tracking-wide">
            إعدادات التنبيه
        </button>
    </div>

    <!-- Decorative Elements -->
    <div class="fixed bottom-10 left-10 w-48 h-48 bg-purple-500/5 blur-[100px] rounded-full -z-10"></div>
    <div class="fixed top-40 right-10 w-72 h-72 bg-indigo-500/5 blur-[100px] rounded-full -z-10"></div>
</div>
@endsection
