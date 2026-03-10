@extends('layouts.premium')

@section('title', 'Roles Management')

@section('content')
<div class="space-y-8" dir="rtl">
    
    <!-- Header -->
    <div class="flex justify-between items-end flex-wrap gap-6">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">إدارة <span class="accent-text-gradient">الأدوار</span></h2>
            <p class="text-gray-400 mt-1">تحديد المراتب الإدارية والصلاحيات الأساسية للنظام.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="text-[10px] font-bold text-gray-500 hover:text-white transition-colors uppercase tracking-[0.2em]">← العودة للوحة الإدارة</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        <!-- Add Role Form -->
        <div class="lg:col-span-1">
            <div class="glass p-8 rounded-[40px] border border-white/5 space-y-6">
                <h3 class="text-sm font-bold uppercase tracking-widest text-gray-400">إضافة دور جديد</h3>
                
                <form method="POST" action="{{ route('admin.roles.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <input type="text" name="name" placeholder="اسم الدور (مثل: moderator)" class="w-full bg-white/5 border border-white/10 rounded-2xl py-3 px-4 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500/50 transition-all @error('name') border-red-500/50 @enderror" required>
                        @error('name')
                            <p class="text-[10px] text-red-500 mt-2 font-bold px-1 uppercase tracking-wider">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="w-full py-3 rounded-2xl accent-gradient text-sm font-bold tracking-wide shadow-lg shadow-purple-500/20 hover:opacity-90 transition-all">
                        تأكيد الإضافة
                    </button>
                </form>

                <div class="p-4 glass-dark rounded-2xl border border-white/5 space-y-2">
                    <p class="text-[9px] font-bold text-gray-600 uppercase tracking-widest">تلميح تقني</p>
                    <p class="text-[10px] text-gray-500 leading-relaxed font-mono">
                        php artisan user:promote [email] [role]
                    </p>
                </div>
            </div>
        </div>

        <!-- Roles Table -->
        <div class="lg:col-span-2">
            <div class="glass rounded-[40px] overflow-hidden border border-white/5">
                <div class="overflow-x-auto">
                    <table class="w-full text-right">
                        <thead>
                            <tr class="border-b border-white/5">
                                <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">الدور / المرتبة</th>
                                <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">عدد المستخدمين</th>
                                <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($roles as $r)
                                <tr class="group hover:bg-white/5 transition-all">
                                    <td class="p-8">
                                        <div class="flex items-center gap-3">
                                            <div class="w-2 h-2 rounded-full bg-purple-500 shadow-[0_0_10px_rgba(168,85,247,0.5)]"></div>
                                            <span class="font-bold text-gray-200 tracking-wide">{{ strtoupper($r->name) }}</span>
                                        </div>
                                    </td>
                                    <td class="p-8 text-sm font-mono text-gray-400">
                                        {{ str_pad($r->users_count, 2, '0', STR_PAD_LEFT) }} <span class="text-[10px] text-gray-600 uppercase">مستخدم</span>
                                    </td>
                                    <td class="p-8">
                                        @if(!in_array($r->name, ['super_admin', 'user', 'photographer']))
                                            <form method="POST" action="{{ route('admin.roles.destroy', $r) }}" onsubmit="return confirm('حذف هذا الدور؟')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-[10px] font-bold text-red-500 hover:text-red-400 uppercase tracking-widest transition-colors">حذف الدور</button>
                                            </form>
                                        @else
                                            <span class="text-[10px] font-bold text-gray-700 uppercase tracking-widest">محمي بواسطة النظام</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="p-20 text-center text-gray-600 font-bold uppercase tracking-widest text-[10px]">لا توجد أدوار إضافية</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    @if(session('success'))
        <div class="fixed bottom-8 right-8 animate-slide-up">
            <div class="glass border border-green-500/20 bg-green-500/5 px-6 py-4 rounded-3xl flex items-center gap-4">
                <div class="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center text-green-500">✓</div>
                <p class="text-sm font-semibold text-green-500">{{ session('success') }}</p>
            </div>
        </div>
    @endif
</div>
@endsection
