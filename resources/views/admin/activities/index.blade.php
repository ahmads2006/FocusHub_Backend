@extends('layouts.premium')

@section('title', 'System Activity Audit')

@section('content')
<div class="space-y-8" dir="rtl">
    
    <!-- Header -->
    <div class="flex justify-between items-end flex-wrap gap-6">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">سجل <span class="accent-text-gradient">نشاطات النظام</span></h2>
            <p class="text-gray-400 mt-1">تتبع كافة التحركات والعمليات الإدارية في OpticVault.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="text-[10px] font-bold text-gray-500 hover:text-white transition-colors uppercase tracking-[0.2em]">← العودة للوحة الإدارة</a>
    </div>

    <!-- Filters -->
    <div class="glass p-6 rounded-[32px] border border-white/5">
        <form method="GET" action="{{ route('admin.activities.index') }}" class="flex flex-wrap items-center gap-6">
            <div class="relative flex-1 min-w-[300px]">
                <svg class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                <input type="text" name="user_id" value="{{ request('user_id') }}" placeholder="البحث بواسطة معرف المستخدم (ID)..." class="w-full bg-white/5 border border-white/10 rounded-2xl py-3 pl-12 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500/50 transition-all">
            </div>

            <button type="submit" class="px-8 py-3 rounded-2xl accent-gradient text-sm font-bold tracking-wide shadow-lg shadow-purple-500/20 hover:opacity-90 transition-all">
                تصفية السجل
            </button>
        </form>
    </div>

    <!-- Activity Audit Table -->
    <div class="glass rounded-[40px] overflow-hidden border border-white/5">
        <div class="overflow-x-auto">
            <table class="w-full text-right">
                <thead>
                    <tr class="border-b border-white/5">
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">العملية / الوصف</th>
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">المستخدم</th>
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">توع الكائن</th>
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">التوقيت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($activities as $a)
                        <tr class="group hover:bg-white/5 transition-all">
                            <td class="p-8">
                                <div class="font-bold text-gray-200 tracking-wide">{{ $a->description }}</div>
                            </td>
                            <td class="p-8">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-[10px] font-bold text-purple-400 uppercase">
                                        {{ substr($a->causer?->name ?? 'S', 0, 1) }}
                                    </div>
                                    <div class="text-xs font-semibold text-gray-400">{{ $a->causer?->name ?? 'النظام' }}</div>
                                </div>
                            </td>
                            <td class="p-8">
                                <span class="px-3 py-1 rounded-full text-[8px] font-bold border border-white/10 text-gray-500 uppercase tracking-widest group-hover:border-purple-500/50 group-hover:text-purple-400 transition-all">
                                    {{ class_basename($a->subject_type ?? 'System') }}
                                </span>
                            </td>
                            <td class="p-8">
                                <div class="text-xs font-bold text-gray-300">{{ $a->created_at->diffForHumans() }}</div>
                                <div class="text-[9px] text-gray-600 font-mono mt-1">{{ $a->created_at->format('Y-m-d H:i') }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-20 text-center text-gray-600 font-bold uppercase tracking-widest text-[10px]">لا توجد بيانات نشاط مسجلة</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($activities->hasPages())
            <div class="p-8 border-t border-white/5">
                {{ $activities->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
