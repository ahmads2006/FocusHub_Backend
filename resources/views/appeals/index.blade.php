@extends('layouts.premium')

@section('title', 'سجل طلبات المراجعة')

@section('content')
<div class="max-w-7xl mx-auto space-y-8" dir="rtl">
    <!-- Header -->
    <div class="flex justify-between items-end flex-wrap gap-4 mb-4">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">سجل <span class="accent-text-gradient">طلبات المراجعة</span></h2>
            <p class="text-gray-400 mt-1">تابع حالة الطلبات التي قمت بتقديمها لفك الحظر عن صورك.</p>
        </div>
        <div class="flex items-center gap-4">
            <a href="{{ route('images.index') }}" class="p-3 bg-white/5 hover:bg-white/10 rounded-2xl border border-white/10 transition-all font-bold uppercase tracking-widest text-[10px] text-gray-400">
                العودة للمكتبة
            </a>
        </div>
    </div>

    @if($appeals->isEmpty())
        <div class="glass flex flex-col items-center justify-center py-20 rounded-[40px] border border-white/5">
            <svg class="w-20 h-20 text-gray-600 mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            <h3 class="text-xl font-bold text-white mb-2 tracking-tight">لا توجد طلبات مراجعة</h3>
            <p class="text-gray-500 text-sm">لم تقم بتقديم أي طلبات مراجعة لصور محظورة حتى الآن.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($appeals as $appeal)
                <div class="glass p-6 rounded-[32px] border border-white/5 hover:border-white/20 transition-all flex flex-col h-full relative">
                    
                    <!-- Status Badge -->
                    <div class="absolute top-6 left-6 z-10">
                        @if($appeal->status === 'pending')
                            <span class="bg-yellow-500/20 text-yellow-400 border border-yellow-500/30 text-[9px] font-bold uppercase tracking-widest px-3 py-1.5 rounded-xl flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-400 animate-pulse"></span>
                                قيد المراجعة
                            </span>
                        @elseif($appeal->status === 'approved')
                            <span class="bg-green-500/20 text-green-400 border border-green-500/30 text-[9px] font-bold uppercase tracking-widest px-3 py-1.5 rounded-xl flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                                مقبول (تم فك الحظر)
                            </span>
                        @elseif($appeal->status === 'rejected')
                            <span class="bg-red-500/20 text-red-400 border border-red-500/30 text-[9px] font-bold uppercase tracking-widest px-3 py-1.5 rounded-xl flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                مرفوض (نهائي)
                            </span>
                        @endif
                    </div>

                    <div class="flex items-start gap-4 mb-6">
                        @if($appeal->image)
                            <div class="w-20 h-20 rounded-2xl overflow-hidden bg-black/50 border border-white/10 shrink-0 relative group">
                                <img src="{{ $appeal->image->url }}" class="w-full h-full object-cover select-none {{ $appeal->image->status === 'rejected' ? 'blur-md' : '' }}" alt="صورة">
                                @if($appeal->image->status === 'approved')
                                    <a href="{{ $appeal->image->url }}" target="_blank" class="absolute inset-0 bg-black/60 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    </a>
                                @endif
                            </div>
                        @else
                            <div class="w-20 h-20 rounded-2xl bg-white/5 border border-white/10 shrink-0 flex items-center justify-center text-gray-500 text-[10px] font-bold">
                                محذوفة
                            </div>
                        @endif
                        <div class="flex-1 min-w-0 pt-1">
                            <h4 class="text-sm font-bold text-white truncate">{{ $appeal->image->title ?? 'صورة بدون عنوان' }}</h4>
                            <p class="text-[10px] text-gray-500 font-mono mt-1" dir="ltr">{{ $appeal->created_at->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>

                    <div class="flex-1 space-y-4">
                        <div class="bg-black/20 rounded-2xl p-4 border border-white/5">
                            <p class="text-[10px] uppercase tracking-widest font-bold text-gray-500 mb-2">رسالتك للإدارة:</p>
                            <p class="text-sm text-gray-300 leading-relaxed">{{ $appeal->reason }}</p>
                        </div>
                        
                        @if($appeal->admin_notes && $appeal->status !== 'pending')
                            <div class="bg-{{ $appeal->status === 'approved' ? 'green' : 'red' }}-500/10 rounded-2xl p-4 border border-{{ $appeal->status === 'approved' ? 'green' : 'red' }}-500/20 relative">
                                <p class="text-[10px] uppercase tracking-widest font-bold text-{{ $appeal->status === 'approved' ? 'green' : 'red' }}-400 mb-2">رد الإدارة:</p>
                                <p class="text-sm text-white leading-relaxed">{{ $appeal->admin_notes }}</p>
                                <div class="mt-3 text-[9px] text-gray-500 font-bold" dir="ltr">
                                    {{ $appeal->reviewed_at ? $appeal->reviewed_at->format('Y-m-d H:i') : '' }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8 flex justify-center">
            {{ $appeals->links() }}
        </div>
    @endif
</div>
@endsection
