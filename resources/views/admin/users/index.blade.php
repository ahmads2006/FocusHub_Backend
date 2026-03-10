@extends('layouts.premium')

@section('title', 'User Management')

@section('content')
<div class="space-y-8" dir="rtl">
    
    <!-- Header -->
    <div class="flex justify-between items-end flex-wrap gap-6">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">إدارة <span class="accent-text-gradient">المستخدمين</span></h2>
            <p class="text-gray-400 mt-1">تحكّم في صلاحيات المستخدمين، حالات الحظر، والأدوار.</p>
        </div>
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.roles.index') }}" class="px-6 py-2 rounded-2xl glass border border-white/10 text-xs font-bold text-indigo-400 hover:bg-white/5 transition-all uppercase tracking-widest">إدارة الأدوار</a>
            <a href="{{ route('admin.dashboard') }}" class="text-[10px] font-bold text-gray-500 hover:text-white transition-colors uppercase tracking-[0.2em]">← العودة للوحة الإدارة</a>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="glass p-6 rounded-[32px] border border-white/5">
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-center gap-6">
            <div class="relative flex-1 min-w-[300px]">
                <svg class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث بالاسم أو البريد الإلكتروني..." class="w-full bg-white/5 border border-white/10 rounded-2xl py-3 pl-12 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500/50 transition-all">
            </div>
            
            <label class="flex items-center gap-3 cursor-pointer group">
                <input type="checkbox" name="banned" value="1" {{ request('banned') ? 'checked' : '' }} class="w-5 h-5 rounded-lg bg-white/5 border-white/10 text-purple-600 focus:ring-purple-500/50 focus:ring-offset-0 transition-all">
                <span class="text-sm font-semibold text-gray-400 group-hover:text-gray-200 transition-colors">المحظورون فقط</span>
            </label>

            <button type="submit" class="px-8 py-3 rounded-2xl accent-gradient text-sm font-bold tracking-wide shadow-lg shadow-purple-500/20 hover:opacity-90 transition-all">
                تصفية النتائج
            </button>
        </form>
    </div>

    <!-- Users Table -->
    <div class="glass rounded-[40px] overflow-hidden border border-white/5">
        <div class="overflow-x-auto">
            <table class="w-full text-right">
                <thead>
                    <tr class="border-b border-white/5">
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">المستخدم</th>
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">الدور</th>
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">الحالة</th>
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">التسجيل</th>
                        <th class="p-8 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($users as $u)
                        <tr class="group hover:bg-white/5 transition-all">
                            <td class="p-8">
                                <div class="font-bold text-gray-200">{{ $u->name }}</div>
                                <div class="text-[10px] text-gray-600 font-mono mt-1">{{ $u->email }}</div>
                            </td>
                            <td class="p-8">
                                <div class="flex flex-wrap gap-2">
                                    @foreach($u->roles as $r)
                                        <span class="px-3 py-1 rounded-full text-[8px] font-bold uppercase italic border border-purple-500/20 text-purple-400 bg-purple-500/5">
                                            {{ $r->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="p-8">
                                <div class="flex flex-wrap gap-2">
                                    @if($u->is_banned)
                                        <span class="px-3 py-1 rounded-full text-[8px] font-bold border border-red-500/20 text-red-500 bg-red-500/5 uppercase tracking-widest">محظور</span>
                                    @endif
                                    @if($u->is_shadow_hidden)
                                        <span class="px-3 py-1 rounded-full text-[8px] font-bold border border-orange-500/20 text-orange-400 bg-orange-500/5 uppercase tracking-widest">حجب شامل</span>
                                    @endif
                                    @if(!$u->is_banned && !$u->is_shadow_hidden)
                                        <span class="text-gray-700">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-8 text-xs font-mono text-gray-500">
                                {{ $u->created_at->format('Y-m-d') }}
                            </td>
                            <td class="p-8">
                                <div class="flex items-center gap-3">
                                    @if($u->is_banned)
                                        <form method="POST" action="{{ route('admin.users.unban', $u) }}">
                                            @csrf
                                            <button type="submit" class="text-[10px] font-bold text-green-500 hover:text-green-400 uppercase tracking-widest transition-colors">إلغاء الحظر</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.ban', $u) }}">
                                            @csrf
                                            <button type="submit" class="text-[10px] font-bold text-red-500 hover:text-red-400 uppercase tracking-widest transition-colors" onclick="return confirm('حظر هذا المستخدم؟')">حظر</button>
                                        </form>
                                    @endif
                                    
                                    <form method="POST" action="{{ route('admin.users.shadow', $u) }}">
                                        @csrf
                                        <button type="submit" class="text-[10px] font-bold text-orange-400 hover:text-orange-300 uppercase tracking-widest transition-colors">
                                            {{ $u->is_shadow_hidden ? 'إلغاء الحجب' : 'حجب شامل' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.users.role', $u) }}" class="flex items-center gap-2">
                                        @csrf
                                        <select name="role" onchange="this.form.submit()" class="bg-black/20 border border-white/5 rounded-xl px-3 py-1.5 text-[10px] font-bold text-gray-400 focus:outline-none focus:border-purple-500/50 transition-all">
                                            @foreach($roles as $r)
                                                <option value="{{ $r->name }}" {{ $u->hasRole($r->name) ? 'selected' : '' }}>{{ $r->name }}</option>
                                            @endforeach
                                        </select>
                                    </form>

                                    <a href="{{ route('admin.activities.index', ['user_id' => $u->id]) }}" class="text-[10px] font-bold text-purple-400 hover:text-purple-300 uppercase tracking-widest transition-colors">النشاط</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-20 text-center text-gray-600 font-bold uppercase tracking-widest text-[10px]">لا يوجد مستخدمين مطابقين للبحث</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="p-8 border-t border-white/5">
                {{ $users->links() }}
            </div>
        @endif
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
