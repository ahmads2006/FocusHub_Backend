@extends('layouts.premium')

@section('title', 'Banned Hash Registry')

@section('content')
<div class="space-y-8" dir="rtl">
    
    <!-- Header -->
    <div class="flex justify-between items-end flex-wrap gap-6">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">قائمة <span class="accent-text-gradient">البصمات المحظورة</span></h2>
            <p class="text-gray-400 mt-1">السجل الأسود لبصمات الملفات (MD5) الممنوعة من الرفع دولياً.</p>
        </div>
        <div class="flex items-center gap-6">
            <a href="{{ route('admin.photos.index') }}" class="text-[10px] font-bold uppercase tracking-[0.2em] {{ request()->routeIs('admin.photos.index') ? 'text-purple-400' : 'text-gray-500 hover:text-gray-300' }} transition-colors">جميع الصور</a>
            <a href="{{ route('admin.banned_hashes.index') }}" class="text-[10px] font-bold uppercase tracking-[0.2em] {{ request()->routeIs('admin.banned_hashes.index') ? 'text-purple-400' : 'text-gray-500 hover:text-gray-300' }} transition-colors">البصمات المحظورة</a>
            <a href="{{ route('admin.dashboard') }}" class="text-[10px] font-bold text-gray-700 hover:text-white transition-colors uppercase tracking-[0.2em]">← العودة</a>
        </div>
    </div>

    <!-- Banned Hashes Table -->
    <div class="glass rounded-[40px] overflow-hidden border border-white/5">
        <div class="overflow-x-auto">
            <table class="w-full text-right">
                <thead>
                    <tr class="border-b border-white/5">
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">البصمة الرقمية (MD5)</th>
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">سبب الحظر القاطع</th>
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">تاريخ الإدراج</th>
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($hashes as $hash)
                        <tr class="group hover:bg-white/5 transition-all">
                            <td class="p-8">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></div>
                                    <code class="text-xs font-mono text-purple-400 bg-purple-500/5 px-3 py-1.5 rounded-xl border border-purple-500/10 tracking-widest">{{ $hash->hash }}</code>
                                </div>
                            </td>
                            <td class="p-8">
                                <span class="px-3 py-1 rounded-full text-[8px] font-bold border border-red-500/20 text-red-500 bg-red-500/5 uppercase tracking-widest italic">
                                    {{ $hash->reason }}
                                </span>
                            </td>
                            <td class="p-8">
                                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ $hash->created_at->format('Y.m.d') }}</div>
                                <div class="text-[9px] text-gray-700 font-mono mt-1">{{ $hash->created_at->format('H:i') }}</div>
                            </td>
                            <td class="p-8">
                                <form action="{{ route('admin.banned_hashes.destroy', $hash) }}" method="POST" onsubmit="return confirm('إلغاء حظر هذه البصمة؟');">
                                    @csrf @method('DELETE')
                                    <button class="text-[10px] font-bold text-green-500 hover:text-green-400 uppercase tracking-widest transition-colors">إعادة السماح</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-20 text-center text-gray-600 font-bold uppercase tracking-widest text-[10px]">لا توجد بصمات في القائمة السوداء حالياً</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($hashes->hasPages())
            <div class="p-8 border-t border-white/5">
                {{ $hashes->links() }}
            </div>
        @endif
    </div>

    <!-- Security Panel -->
    <div class="p-8 glass-dark rounded-[40px] border border-white/5 flex items-center justify-between gap-8">
        <div class="space-y-2">
            <h4 class="text-sm font-bold text-gray-300">أمن البيانات وحماية الأرشفة</h4>
            <p class="text-xs text-gray-500 leading-relaxed max-w-xl">
                يتم استخدام بصمات MD5 الممنوعة كطبقة حماية نهائية لمنع إعادة رفع المحتوى المخالف للسياسات أو المحتوى الضار. بمجرد حظر البصمة، لن يتمكن أي مستخدم من رفع نفس الملف حتى لو قام بتغيير اسمه.
            </p>
        </div>
        <div class="text-right">
            <div class="text-3xl font-bold accent-text-gradient">{{ str_pad($hashes->total(), 2, '0', STR_PAD_LEFT) }}</div>
            <div class="text-[9px] font-bold text-gray-600 uppercase tracking-widest mt-1">إجمالي المحظورات</div>
        </div>
    </div>
</div>
@endsection
