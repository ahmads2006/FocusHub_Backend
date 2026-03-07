<x-app-layout>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap');
        .admin-wrap * { font-family: 'IBM Plex Sans Arabic', sans-serif; }
        .admin-bg { background-color: #0d0f14; min-height: 100vh; }
        .admin-card { background: #13151c; border: 1px solid #1e2130; border-radius: 16px; overflow: hidden; }
        .admin-header { padding: 24px; border-bottom: 1px solid #1e2130; }
        .admin-title { font-size: 18px; font-weight: 700; color: #f1f3f9; }
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th { font-size: 10px; font-weight: 700; letter-spacing: 2px; color: #4a5270; padding: 14px 20px; text-align: right; border-bottom: 1px solid #1e2130; }
        .admin-table td { padding: 16px 20px; border-bottom: 1px solid #0d0f14; color: #c8cfe0; font-size: 14px; }
        .admin-table tr:hover { background: #0d0f14; }
        .activity-badge { font-size: 9px; font-weight: 700; letter-spacing: 1px; padding: 4px 10px; border-radius: 6px; background: #0d0f14; color: #4a5270; }
    </style>

    <div class="admin-bg admin-wrap" dir="rtl">
        <div class="py-10">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <p style="font-size: 10px; font-weight: 700; letter-spacing: 5px; color: #d4a853; margin: 0 0 4px 0;">سجلات النشاط</p>
                        <h1 style="font-size: 24px; font-weight: 700; color: #f1f3f9; margin: 0;">النشاطات في النظام</h1>
                    </div>
                    <a href="{{ route('admin.dashboard') }}" style="font-size: 13px; color: #8891aa; text-decoration: none;">← العودة للوحة</a>
                </div>

                <div class="admin-card">
                    <div class="admin-header">
                        <form method="GET" action="{{ route('admin.activities.index') }}" style="display: flex; gap: 12px; align-items: center;">
                            @if(request('user_id'))
                                <input type="hidden" name="user_id" value="{{ request('user_id') }}">
                            @endif
                            <input type="text" name="user_id" value="{{ request('user_id') }}" placeholder="معرف المستخدم (اختياري)" style="background:#0d0f14;border:1px solid #1e2130;border-radius:10px;padding:10px 16px;font-size:14px;color:#f1f3f9;width:200px;">
                            <button type="submit" style="font-size:11px;font-weight:600;padding:8px 16px;border-radius:8px;background:transparent;color:#d4a853;border:1px solid #d4a85355;cursor:pointer;">تصفية</button>
                        </form>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>الوصف</th>
                                    <th>المستخدم</th>
                                    <th>النوع</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($activities as $a)
                                    <tr>
                                        <td>
                                            <div style="font-weight: 500;">{{ $a->description }}</div>
                                        </td>
                                        <td style="font-size: 13px; color: #8891aa;">{{ $a->causer?->name ?? '—' }}</td>
                                        <td><span class="activity-badge">{{ class_basename($a->subject_type ?? '—') }}</span></td>
                                        <td style="font-size: 12px; color: #4a5270;">{{ $a->created_at->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" style="padding: 40px; text-align: center; color: #4a5270;">لا يوجد نشاطات.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($activities->hasPages())
                        <div style="padding: 20px; border-top: 1px solid #1e2130;">
                            {{ $activities->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
