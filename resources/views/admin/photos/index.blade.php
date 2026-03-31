@extends('layouts.premium')

@section('title', 'Content Moderation')

@section('content')
<div class="space-y-8" dir="rtl">
    
    <!-- Header -->
    <div class="flex justify-between items-end flex-wrap gap-6">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">مركز <span class="accent-text-gradient">الرقابة والتحكم</span></h2>
            <p class="text-gray-400 mt-1">إدارة المحتوى المرفوع، مراجعة الخصوصية، وحظر الملفات المشبوهة.</p>
        </div>
        <div class="flex items-center gap-6">
            <a href="{{ route('admin.photos.index') }}" class="text-[10px] font-bold uppercase tracking-[0.2em] {{ request()->routeIs('admin.photos.index') ? 'text-purple-400' : 'text-gray-500 hover:text-gray-300' }} transition-colors">جميع الصور</a>
            <a href="{{ route('admin.moderation.index') }}" class="text-[10px] font-bold uppercase tracking-[0.2em] {{ request()->routeIs('admin.moderation.index') ? 'text-purple-400' : 'text-gray-500 hover:text-gray-300' }} transition-colors">قائمة المراجعة (AI)</a>
            <a href="{{ route('admin.banned_hashes.index') }}" class="text-[10px] font-bold uppercase tracking-[0.2em] {{ request()->routeIs('admin.banned_hashes.index') ? 'text-purple-400' : 'text-gray-500 hover:text-gray-300' }} transition-colors">البصمات المحظورة</a>
            <a href="{{ route('admin.dashboard') }}" class="text-[10px] font-bold text-gray-700 hover:text-white transition-colors uppercase tracking-[0.2em]">← العودة</a>
        </div>
    </div>

    <!-- Media Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @forelse($images as $image)
            <div class="glass group rounded-[32px] overflow-hidden border border-white/5 hover:border-purple-500/30 transition-all duration-500">
                <!-- Image Preview -->
                <div class="aspect-square relative overflow-hidden bg-black/40">
                    <img src="{{ $image->url }}" alt="{{ $image->title }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    
                    <!-- Status Badge -->
                    <div class="absolute top-4 right-4">
                        <span class="px-3 py-1 rounded-full text-[8px] font-bold tracking-widest uppercase italic border 
                            {{ $image->privacy === 'public' ? 'border-green-500/20 text-green-400 bg-green-500/10' : 
                               ($image->privacy === 'private' ? 'border-blue-500/20 text-blue-400 bg-blue-500/10' : 
                               'border-red-500/20 text-red-500 bg-red-500/10') }}">
                            {{ $image->privacy }}
                        </span>
                    </div>
                </div>

                <!-- Info -->
                <div class="p-6 space-y-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-200 truncate">{{ $image->title ?: 'صورة بدون عنوان' }}</h3>
                        <p class="text-[10px] text-gray-500 mt-1">بواسطة: <span class="text-purple-400 font-bold uppercase">{{ $image->user->name }}</span></p>
                    </div>

                    <div class="flex items-center justify-between text-[9px] font-mono text-gray-600">
                        <span>{{ $image->created_at->format('Y.m.d') }}</span>
                        <span>{{ strtoupper(pathinfo($image->filename, PATHINFO_EXTENSION)) }}</span>
                    </div>

                    <!-- Actions -->
                    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-white/5">
                        <form action="{{ route('admin.photos.visibility', $image) }}" method="POST">
                            @csrf
                            <button class="w-full py-2 rounded-xl border border-white/10 text-[9px] font-bold uppercase tracking-widest hover:bg-white/5 transition-all">
                                {{ $image->privacy === 'hidden' ? 'إظهار' : 'إخفاء' }}
                            </button>
                        </form>
                        <form action="{{ route('admin.photos.destroy', $image) }}" method="POST" onsubmit="return confirm('حذف نهائي؟')">
                            @csrf @method('DELETE')
                            <button class="w-full py-2 rounded-xl border border-red-500/20 text-red-500 text-[9px] font-bold uppercase tracking-widest hover:bg-red-500/10 transition-all">
                                حذف
                            </button>
                        </form>
                        <form action="{{ route('admin.photos.ban', $image) }}" method="POST" class="col-span-2" onsubmit="return confirm('حظر بصمة هذه الصورة نهائياً؟')">
                            @csrf
                            <button class="w-full py-2 rounded-xl accent-gradient text-[9px] font-bold uppercase tracking-[0.2em] shadow-lg shadow-purple-500/20 hover:opacity-90 transition-all">
                                حظر البصمة (BAN)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-32 text-center">
                <div class="glass w-20 h-20 rounded-[32px] mx-auto flex items-center justify-center text-3xl mb-6">📸</div>
                <h4 class="text-lg font-bold text-gray-400">لا توجد صور حالياً في النظام</h4>
                <p class="text-sm text-gray-600 mt-2">كافة الأرشيفات الرقمية محدثة تماماً.</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($images->hasPages())
        <div class="pt-8 border-t border-white/5">
            {{ $images->links() }}
        </div>
    @endif
</div>
@endsection
