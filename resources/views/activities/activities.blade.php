@extends('layouts.premium')

@section('title', 'Activity Stream')

@section('content')
<div class="space-y-8" dir="rtl">
    
    <!-- Header -->
    <div class="flex justify-between items-end">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">سجل <span class="accent-text-gradient">النشاطات</span></h2>
            <p class="text-gray-400 mt-1">تتبع كافة العمليات والتغييرات التي تمت على حسابك.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">التتبع المباشر نشط</span>
        </div>
    </div>

    <!-- Activity Table -->
    <div class="glass rounded-[40px] overflow-hidden border border-white/5">
        <div class="overflow-x-auto">
            <table class="w-full text-right">
                <thead>
                    <tr class="border-b border-white/5">
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">العملية</th>
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">الهدف</th>
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">التفاصيل</th>
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">التوقيت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($activities as $activity)
                        @php
                            $badgeColor = match($activity->description) {
                                'created' => 'text-green-400 bg-green-400/10 border-green-400/20',
                                'updated' => 'text-blue-400 bg-blue-400/10 border-blue-400/20',
                                'deleted' => 'text-red-400 bg-red-400/10 border-red-400/20',
                                default   => 'text-gray-400 bg-gray-400/10 border-gray-400/20',
                            };
                        @endphp
                        <tr class="group hover:bg-white/5 transition-all">
                            <td class="p-8">
                                <span class="px-3 py-1 rounded-full text-[9px] font-bold uppercase italic border {{ $badgeColor }}">
                                    {{ __($activity->description) }}
                                </span>
                            </td>
                            <td class="p-8">
                                <span class="text-xs font-semibold text-gray-300">{{ basename($activity->subject_type ?? 'النظام') }}</span>
                            </td>
                            <td class="p-8">
                                @if($activity->changes())
                                    <div x-data="{ open: false }">
                                        <button @click="open = !open" class="text-[10px] font-bold text-purple-400 uppercase tracking-widest hover:text-purple-300 transition-colors">
                                            عرض التغييرات <span x-text="open ? '↑' : '↓'"></span>
                                        </button>
                                        <div x-show="open" x-cloak x-transition class="mt-4 p-4 glass-dark rounded-2xl border border-white/5 font-mono text-[10px] text-gray-500 overflow-x-auto text-left" dir="ltr">
                                            <pre>{{ json_encode($activity->changes(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-gray-700">—</span>
                                @endif
                            </td>
                            <td class="p-8">
                                <div class="text-sm font-bold text-gray-300">{{ $activity->created_at->diffForHumans() }}</div>
                                <div class="text-[10px] text-gray-600 font-mono mt-1">{{ $activity->created_at->format('Y-m-d H:i') }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-20 text-center">
                                <div class="flex flex-col items-center gap-4">
                                    <div class="w-16 h-16 rounded-3xl glass flex items-center justify-center text-gray-600">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                    <p class="text-gray-500 font-bold uppercase tracking-widest text-[10px]">لا توجد نشاطات مسجلة بعد</p>
                                </div>
                            </td>
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