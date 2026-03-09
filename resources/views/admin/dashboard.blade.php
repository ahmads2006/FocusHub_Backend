<x-app-layout>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap');
        .admin-wrap * { font-family: 'IBM Plex Sans Arabic', sans-serif; }
        .admin-bg { background-color: #0d0f14; min-height: 100vh; }
        .admin-card {
            background: #13151c;
            border: 1px solid #1e2130;
            border-radius: 16px;
            padding: 28px;
            transition: transform 0.2s, border-color 0.2s;
        }
        .admin-card:hover { transform: translateY(-2px); border-color: #2e3450; }
        .admin-label { font-size: 11px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; color: #4a5270; margin-bottom: 8px; }
        .admin-number { font-size: 36px; font-weight: 700; }
        .admin-number.gold { color: #d4a853; }
        .admin-number.blue { color: #60a5fa; }
        .admin-number.green { color: #34d399; }
        .admin-number.red { color: #f87171; }
        .admin-number.purple { color: #a78bfa; }
        .admin-sub { font-size: 12px; color: #3d4460; margin-top: 4px; }
        .admin-link {
            display: inline-block;
            font-size: 12px; font-weight: 600; letter-spacing: 1.5px;
            color: #d4a853; text-decoration: none; margin-top: 12px;
            transition: opacity 0.2s;
        }
        .admin-link:hover { opacity: 0.8; }
        .activity-row {
            padding: 14px 20px;
            border-bottom: 1px solid #0d0f14;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .activity-row:last-child { border-bottom: none; }
        .activity-row:hover { background: #0d0f14; }
        .activity-desc { font-size: 13px; color: #c8cfe0; }
        .activity-time { font-size: 11px; color: #3d4460; margin-top: 2px; }
        .activity-badge { font-size: 9px; font-weight: 700; letter-spacing: 1px; color: #4a5270; background: #0d0f14; padding: 4px 8px; border-radius: 6px; }
        .admin-header { border-bottom: 1px solid #1e2130; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; }
        .admin-title { font-size: 14px; font-weight: 700; color: #f1f3f9; letter-spacing: 2px; text-transform: uppercase; }
    </style>

    <div class="admin-bg admin-wrap" dir="rtl">
        <div class="py-10">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div style="margin-bottom: 32px;">
                    <p style="font-size: 10px; font-weight: 700; letter-spacing: 5px; text-transform: uppercase; color: #d4a853; margin: 0 0 8px 0;">لوحة الإدارة</p>
                    <h1 style="font-size: 26px; font-weight: 700; color: #f1f3f9; margin: 0;">لوحة السوبر أدمن</h1>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    <div class="admin-card">
                        <div class="admin-label">المستخدمون</div>
                        <div class="admin-number gold">{{ $stats['total_users'] }}</div>
                        <div class="admin-sub">مستخدم مسجّل</div>
                        <a href="{{ route('admin.users.index') }}" class="admin-link">عرض الكل ←</a>
                    </div>
                    <div class="admin-card">
                        <div class="admin-label">الأدوار</div>
                        <div class="admin-number" style="color: #a78bfa;">{{ \Spatie\Permission\Models\Role::count() }}</div>
                        <div class="admin-sub">دور مُعرّف</div>
                        <a href="{{ route('admin.roles.index') }}" class="admin-link">إدارة الأدوار ←</a>
                    </div>
                    <div class="admin-card">
                        <div class="admin-label">الصور</div>
                        <div class="admin-number blue">{{ $stats['total_images'] }}</div>
                        <div class="admin-sub">صورة في النظام</div>
                    </div>
                    <div class="admin-card">
                        <div class="admin-label">الألبومات</div>
                        <div class="admin-number green">{{ $stats['total_albums'] }}</div>
                        <div class="admin-sub">ألبوم مُنشأ</div>
                    </div>
                    <div class="admin-card">
                        <div class="admin-label">المحظورون</div>
                        <div class="admin-number red">{{ $stats['banned_users'] }}</div>
                        <div class="admin-sub">حساب محظور</div>
                    </div>
                    <div class="admin-card">
                        <div class="admin-label">رقابة الصور</div>
                        <div class="admin-number" style="color: #fb923c;">{{ $stats['total_images'] }}</div>
                        <div class="admin-sub">إدارة وحظر الصور</div>
                        <a href="{{ route('admin.photos.index') }}" class="admin-link">مركز الرقابة ←</a>
                    </div>
                </div>

                <div class="admin-card" style="padding: 0; overflow: hidden;">
                    <div class="admin-header">
                        <span class="admin-title">آخر النشاطات في النظام</span>
                        <a href="{{ route('admin.activities.index') }}" class="admin-link" style="margin-top: 0;">عرض الكل ←</a>
                    </div>
                    @forelse($stats['recent_activities'] as $a)
                        <div class="activity-row">
                            <div>
                                <div class="activity-desc">{{ $a->description }}</div>
                                <div class="activity-time">
                                    {{ $a->causer?->name ?? 'غير معروف' }} · {{ $a->created_at->diffForHumans() }}
                                </div>
                            </div>
                            <span class="activity-badge">{{ class_basename($a->subject_type ?? 'System') }}</span>
                        </div>
                    @empty
                        <div style="padding: 40px; text-align: center; color: #3d4460;">لا يوجد نشاطات.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
