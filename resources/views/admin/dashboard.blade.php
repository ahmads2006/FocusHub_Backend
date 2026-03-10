@extends('layouts.premium')

@section('title', 'Admin Command Center')

@section('content')
<div class="space-y-8" dir="rtl">
    
    <!-- Header -->
    <div class="flex justify-between items-end">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">لوحة <span class="accent-text-gradient">السوبر أدمن</span></h2>
            <p class="text-gray-400 mt-1">مركز التحكم الكامل وإدارة موارد النظام.</p>
        </div>
        <div class="px-4 py-2 rounded-2xl glass border border-white/5 text-[10px] font-bold text-gray-500 uppercase tracking-widest">
            حالة النظام: <span class="text-green-400">مثالية</span>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <div class="glass p-6 rounded-[32px] border border-white/5 group hover:border-purple-500/30 transition-all">
            <p class="text-[9px] font-bold text-gray-500 uppercase tracking-widest mb-4">المستخدمون</p>
            <h3 class="text-3xl font-bold text-purple-400">{{ $stats['total_users'] }}</h3>
            <p class="text-[10px] text-gray-600 mt-2">مستخدم مسجل</p>
            <a href="{{ route('admin.users.index') }}" class="mt-4 block text-[10px] font-bold text-purple-500 hover:text-purple-400 transition-colors uppercase tracking-wider">إدارة المستخدمين ←</a>
        </div>

        <div class="glass p-6 rounded-[32px] border border-white/5 group hover:border-indigo-500/30 transition-all">
            <p class="text-[9px] font-bold text-gray-500 uppercase tracking-widest mb-4">الأدوار</p>
            <h3 class="text-3xl font-bold text-indigo-400">{{ \Spatie\Permission\Models\Role::count() }}</h3>
            <p class="text-[10px] text-gray-600 mt-2">دور معرف</p>
            <a href="{{ route('admin.roles.index') }}" class="mt-4 block text-[10px] font-bold text-indigo-500 hover:text-indigo-400 transition-colors uppercase tracking-wider">إدارة الأدوار ←</a>
        </div>

        <div class="glass p-6 rounded-[32px] border border-white/5 group hover:border-blue-500/30 transition-all">
            <p class="text-[9px] font-bold text-gray-500 uppercase tracking-widest mb-4">الصور</p>
            <h3 class="text-3xl font-bold text-blue-400">{{ $stats['total_images'] }}</h3>
            <p class="text-[10px] text-gray-600 mt-2">صورة في النظام</p>
        </div>

        <div class="glass p-6 rounded-[32px] border border-white/5 group hover:border-green-500/30 transition-all">
            <p class="text-[9px] font-bold text-gray-500 uppercase tracking-widest mb-4">الألبومات</p>
            <h3 class="text-3xl font-bold text-green-400">{{ $stats['total_albums'] }}</h3>
            <p class="text-[10px] text-gray-600 mt-2">ألبوم منشأ</p>
        </div>

        <div class="glass p-6 rounded-[32px] border border-white/5 group hover:border-red-500/30 transition-all">
            <p class="text-[9px] font-bold text-gray-500 uppercase tracking-widest mb-4">المحظورون</p>
            <h3 class="text-3xl font-bold text-red-500">{{ $stats['banned_users'] }}</h3>
            <p class="text-[10px] text-gray-600 mt-2">حساب محظور</p>
        </div>

        <div class="glass p-6 rounded-[32px] border border-white/5 group hover:border-orange-500/30 transition-all">
            <p class="text-[9px] font-bold text-gray-500 uppercase tracking-widest mb-4">الرقابة</p>
            <h3 class="text-3xl font-bold text-orange-400">{{ $stats['total_images'] }}</h3>
            <p class="text-[10px] text-gray-600 mt-2">مركز الرقابة نشط</p>
            <a href="{{ route('admin.photos.index') }}" class="mt-4 block text-[10px] font-bold text-orange-500 hover:text-orange-400 transition-colors uppercase tracking-wider">فتح الرقابة ←</a>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="glass rounded-[40px] overflow-hidden border border-white/5">
        <div class="p-8 border-b border-white/5 flex justify-between items-center">
            <h4 class="text-sm font-bold uppercase tracking-widest text-gray-400">آخر النشاطات في النظام</h4>
            <a href="{{ route('admin.activities.index') }}" class="text-[10px] font-bold text-purple-400 hover:text-purple-300 transition-colors uppercase tracking-[0.2em]">عرض السجل الكامل</a>
        </div>
        <div class="divide-y divide-white/5">
            @forelse($stats['recent_activities'] as $a)
                <div class="p-6 px-8 flex justify-between items-center group hover:bg-white/5 transition-all">
                    <div>
                        <div class="text-sm font-semibold text-gray-300">{{ $a->description }}</div>
                        <div class="text-[10px] text-gray-500 mt-1">
                            بواسطة <span class="text-purple-400">{{ $a->causer?->name ?? 'النظام' }}</span> · <span>{{ $a->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-[8px] font-bold border border-white/10 text-gray-500 uppercase tracking-widest group-hover:border-purple-500/50 transition-all">
                        {{ class_basename($a->subject_type ?? 'System') }}
                    </span>
                </div>
            @empty
                <div class="p-20 text-center text-gray-600 font-bold uppercase tracking-widest text-[10px]">لا توجد نشاطات مسجلة</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
