@extends('layouts.premium')

@section('title', 'إدارة طلبات المراجعة')

@section('content')
    <div class="p-6 max-w-7xl mx-auto" dir="rtl">
        <!-- Header -->
        <div class="flex justify-between items-end flex-wrap gap-6 mb-8">
            <div>
                <h2 class="text-3xl font-bold tracking-tight">إدارة <span class="accent-text-gradient">طلبات المراجعة</span></h2>
                <p class="text-gray-400 mt-1">
                    إجمالي الطلبات: <span class="text-white font-bold">{{ $appeals->total() }}</span>
                </p>
            </div>
            <div class="flex items-center gap-6">
                <a href="{{ route('admin.photos.index') }}" class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-500 hover:text-gray-300 transition-colors">جميع الصور</a>
                <a href="{{ route('admin.moderation.index') }}" class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-500 hover:text-gray-300 transition-colors">قائمة المراجعة (AI)</a>
                <a href="{{ route('admin.banned_hashes.index') }}" class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-500 hover:text-gray-300 transition-colors">البصمات المحظورة</a>
                <a href="{{ route('appeals.index') }}" class="text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400 transition-colors">طلبات المراجعة</a>
                <a href="{{ route('admin.dashboard') }}" class="text-[10px] font-bold text-gray-700 hover:text-white transition-colors uppercase tracking-[0.2em]">← العودة</a>
            </div>
        </div>
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold text-white">إدارة طلبات المراجعة</h1>
            <div class="text-sm text-gray-400">
                إجمالي الطلبات: <span class="text-white font-bold">{{ $appeals->total() }}</span>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-6 p-4 bg-green-500/20 border border-green-500/30 text-green-400 rounded-xl">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right">
                    <thead>
                        <tr class="border-b border-white/10 bg-white/5">
                            <th class="p-4 text-sm font-semibold text-gray-300">الصورة</th>
                            <th class="p-4 text-sm font-semibold text-gray-300">معلومات المرسل</th>
                            <th class="p-4 text-sm font-semibold text-gray-300">سبب الاعتراض</th>
                            <th class="p-4 text-sm font-semibold text-gray-300">الحالة</th>
                            <th class="p-4 text-sm font-semibold text-gray-300">تاريخ الطلب</th>
                            <th class="p-4 text-sm font-semibold text-gray-300">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse($appeals as $appeal)
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="p-4">
                                    @if($appeal->image)
                                        <a href="{{ $appeal->image->url ?? '#' }}" target="_blank" class="block w-16 h-16 rounded overflow-hidden border border-white/10">
                                            <img src="{{ $appeal->image->url ?? '' }}" class="w-full h-full object-cover">
                                        </a>
                                    @else
                                        <span class="text-red-400 text-xs">الصورة محذوفة</span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    <p class="text-white font-medium">{{ $appeal->contact_name }}</p>
                                    <p class="text-gray-400 text-xs">{{ $appeal->contact_email }}</p>
                                    @if($appeal->user)
                                        <p class="text-purple-400 text-xs mt-1">عضو مسجل</p>
                                    @endif
                                </td>
                                <td class="p-4 max-w-xs">
                                    <p class="text-gray-300 text-sm truncate" title="{{ $appeal->reason }}">{{ $appeal->reason }}</p>
                                </td>
                                <td class="p-4">
                                    @if($appeal->status === 'pending')
                                        <span class="px-2 py-1 bg-yellow-500/20 text-yellow-400 text-xs rounded-full">بانتظار المراجعة</span>
                                    @elseif($appeal->status === 'approved')
                                        <span class="px-2 py-1 bg-green-500/20 text-green-400 text-xs rounded-full">مقبول</span>
                                    @else
                                        <span class="px-2 py-1 bg-red-500/20 text-red-400 text-xs rounded-full">مرفوض</span>
                                    @endif
                                </td>
                                <td class="p-4 text-gray-400 text-sm" dir="ltr">
                                    {{ $appeal->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="p-4">
                                    @if($appeal->status === 'pending')
                                        <div class="flex gap-2">
                                            <button onclick="document.getElementById('approve-modal-{{ $appeal->id }}').classList.remove('hidden')" class="px-3 py-1.5 bg-green-500/20 text-green-400 hover:bg-green-500/30 rounded text-xs transition">قبول</button>
                                            <button onclick="document.getElementById('reject-modal-{{ $appeal->id }}').classList.remove('hidden')" class="px-3 py-1.5 bg-red-500/20 text-red-400 hover:bg-red-500/30 rounded text-xs transition">رفض</button>
                                        </div>

                                        <!-- Approve Modal -->
                                        <div id="approve-modal-{{ $appeal->id }}" class="hidden fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
                                            <div class="bg-[#1a1a24] border border-white/10 rounded-2xl p-6 w-full max-w-md">
                                                <h3 class="text-xl font-bold text-white mb-4">قبول طلب المراجعة وإلغاء الحظر</h3>
                                                <form action="{{ route('admin.appeals.approve', $appeal) }}" method="POST">
                                                    @csrf
                                                    <div class="mb-4">
                                                        <label class="block text-sm text-gray-400 mb-2">ملاحظات للمستخدم (اختياري)</label>
                                                        <textarea name="admin_notes" class="w-full bg-white/5 border border-white/10 rounded-xl p-3 text-white focus:border-green-500 outline-none h-24"></textarea>
                                                    </div>
                                                    <div class="flex gap-3 justify-end">
                                                        <button type="button" onclick="document.getElementById('approve-modal-{{ $appeal->id }}').classList.add('hidden')" class="px-4 py-2 bg-white/5 text-white rounded-xl hover:bg-white/10">إلغاء</button>
                                                        <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-xl hover:bg-green-600 font-bold">تأكيد القبول</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <!-- Reject Modal -->
                                        <div id="reject-modal-{{ $appeal->id }}" class="hidden fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
                                            <div class="bg-[#1a1a24] border border-white/10 rounded-2xl p-6 w-full max-w-md">
                                                <h3 class="text-xl font-bold text-white mb-4">رفض طلب المراجعة (إبقاء الحظر)</h3>
                                                <form action="{{ route('admin.appeals.reject', $appeal) }}" method="POST">
                                                    @csrf
                                                    <div class="mb-4">
                                                        <label class="block text-sm text-gray-400 mb-2">سبب الرفض (سيتم إرساله للمستخدم)</label>
                                                        <textarea name="admin_notes" required class="w-full bg-white/5 border border-white/10 rounded-xl p-3 text-white focus:border-red-500 outline-none h-24"></textarea>
                                                    </div>
                                                    <div class="flex gap-3 justify-end">
                                                        <button type="button" onclick="document.getElementById('reject-modal-{{ $appeal->id }}').classList.add('hidden')" class="px-4 py-2 bg-white/5 text-white rounded-xl hover:bg-white/10">إلغاء</button>
                                                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-xl hover:bg-red-700 font-bold">تأكيد الرفض</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-500">تمت المراجعة بواسطة {{ $appeal->reviewer->name ?? 'مشرف' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-gray-500">لا توجد طلبات مراجعة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="p-4 border-t border-white/5 bg-white/[0.02]">
                {{ $appeals->links() }}
            </div>
        </div>
    </div>
@endsection
