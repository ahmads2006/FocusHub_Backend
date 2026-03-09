<x-app-layout>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap');
        .admin-wrap * { font-family: 'IBM Plex Sans Arabic', sans-serif; }
        .admin-bg { background-color: #0d0f14; min-height: 100vh; color: #f1f3f9; }
        .hash-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .hash-table th { padding: 12px 20px; text-align: right; color: #4a5270; text-transform: uppercase; font-size: 11px; letter-spacing: 2px; }
        .hash-row { background: #13151c; transition: all 0.2s; }
        .hash-row:hover { background: #1a1d26; }
        .hash-cell { padding: 16px 20px; border-top: 1px solid #1e2130; border-bottom: 1px solid #1e2130; }
        .hash-cell:first-child { border-right: 1px solid #1e2130; border-radius: 0 12px 12px 0; }
        .hash-cell:last-child { border-left: 1px solid #1e2130; border-radius: 12px 0 0 12px; }
        .hash-string { font-family: monospace; color: #d4a853; background: #00000044; padding: 4px 8px; border-radius: 4px; font-size: 13px; }
        .reason-badge { font-size: 10px; background: #f8717122; color: #f87171; padding: 4px 8px; border-radius: 6px; }
        .btn-unban { color: #34d399; font-size: 12px; font-weight: 700; background: none; border: none; cursor: pointer; }
        .btn-unban:hover { text-decoration: underline; }
    </style>

    <div class="admin-bg admin-wrap" dir="rtl">
        <div class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-end mb-8">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-gold mb-2" style="color:#d4a853">القائمة السوداء</p>
                        <h1 class="text-3xl font-bold">بصمات الصور المحظورة</h1>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="hash-table">
                        <thead>
                            <tr>
                                <th>البصمة (MD5)</th>
                                <th>سبب الحظر</th>
                                <th>التاريخ</th>
                                <th>الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($hashes as $hash)
                                <tr class="hash-row">
                                    <td class="hash-cell"><span class="hash-string">{{ $hash->hash }}</span></td>
                                    <td class="hash-cell"><span class="reason-badge">{{ $hash->reason }}</span></td>
                                    <td class="hash-cell text-xs text-gray-500">{{ $hash->created_at->format('Y/m/d H:i') }}</td>
                                    <td class="hash-cell">
                                        <form action="{{ route('admin.banned_hashes.destroy', $hash) }}" method="POST" onsubmit="return confirm('هل تريد إزالة هذه البصمة من قائمة الحظر؟');">
                                            @csrf @method('DELETE')
                                            <button class="btn-unban">إلغاء حظر</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-20 text-gray-600">لا توجد بصمات محظورة حالياً.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-8">
                    {{ $hashes->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
