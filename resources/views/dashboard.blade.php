<x-app-layout>
  
    <style>
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap');

        .dash-wrap * { font-family: 'IBM Plex Sans Arabic', sans-serif; }

        /* Background */
        .dash-bg {
            background-color: #0d0f14;
            min-height: 100vh;
        }

        /* Stat card */
        .stat-card {
            background: #13151c;
            border: 1px solid #1e2130;
            border-radius: 16px;
            padding: 28px;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            border-color: #2e3450;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 60px; height: 3px;
            border-radius: 0 16px 0 0;
        }
        .stat-card.gold::before  { background: linear-gradient(90deg, transparent, #d4a853); }
        .stat-card.blue::before  { background: linear-gradient(90deg, transparent, #60a5fa); }
        .stat-card.green::before { background: linear-gradient(90deg, transparent, #34d399); }

        .stat-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #4a5270;
            margin-bottom: 12px;
        }
        .stat-number {
            font-size: 42px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 6px;
        }
        .stat-number.gold  { color: #d4a853; }
        .stat-number.blue  { color: #60a5fa; }
        .stat-number.green { color: #34d399; }
        .stat-icon {
            position: absolute;
            bottom: 20px;
            left: 24px;
            font-size: 36px;
            opacity: 0.07;
        }
        .stat-sub {
            font-size: 12px;
            color: #3d4460;
        }

        /* Activity card */
        .activity-card {
            background: #13151c;
            border: 1px solid #1e2130;
            border-radius: 16px;
            overflow: hidden;
        }
        .activity-header {
            padding: 24px 28px;
            border-bottom: 1px solid #1e2130;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .activity-title {
            font-size: 14px;
            font-weight: 700;
            color: #f1f3f9;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .view-all-link {
            font-size: 12px;
            color: #d4a853;
            text-decoration: none;
            font-weight: 600;
            letter-spacing: 1px;
            transition: opacity 0.2s;
        }
        .view-all-link:hover { opacity: 0.7; }

        .activity-row {
            padding: 18px 28px;
            border-bottom: 1px solid #0d0f14;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.15s;
        }
        .activity-row:last-of-type { border-bottom: none; }
        .activity-row:hover { background: #0d0f14; }

        .activity-dot {
            width: 7px; height: 7px;
            background: #d4a853;
            border-radius: 50%;
            flex-shrink: 0;
            margin-left: 14px;
            box-shadow: 0 0 8px rgba(212,168,83,0.5);
        }
        .activity-desc {
            font-size: 14px;
            color: #c8cfe0;
            font-weight: 500;
        }
        .activity-time {
            font-size: 11px;
            color: #3d4460;
            margin-top: 4px;
        }
        .activity-badge {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #4a5270;
            background: #0d0f14;
            border: 1px solid #1e2130;
            padding: 5px 10px;
            border-radius: 6px;
            white-space: nowrap;
            margin-right: 12px;
        }
        .no-activity {
            padding: 40px 28px;
            text-align: center;
            color: #3d4460;
            font-size: 14px;
        }

        /* Profile card */
        .profile-card {
            background: #13151c;
            border: 1px solid #1e2130;
            border-radius: 16px;
            padding: 28px;
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            overflow: hidden;
        }
        .profile-card::after {
            content: '';
            position: absolute;
            bottom: 0; right: 0;
            width: 100%; height: 2px;
            background: linear-gradient(90deg, transparent, #d4a853 70%, transparent);
        }
        .profile-avatar {
            width: 64px; height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #d4a853;
            flex-shrink: 0;
        }
        .profile-avatar-placeholder {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: #1e2130;
            border: 2px solid #d4a853;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #d4a853;
            flex-shrink: 0;
        }
        .profile-name {
            font-size: 17px;
            font-weight: 700;
            color: #f1f3f9;
            margin-bottom: 4px;
        }
        .profile-email {
            font-size: 12px;
            color: #4a5270;
            margin-bottom: 4px;
        }
        .profile-joined {
            font-size: 11px;
            color: #3d4460;
        }
        .profile-edit-btn {
            margin-right: auto;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #d4a853;
            background: transparent;
            border: 1px solid #d4a85355;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.2s, border-color 0.2s;
            white-space: nowrap;
        }
        .profile-edit-btn:hover {
            background: #d4a85315;
            border-color: #d4a853;
        }

        /* Gold accent separator */
        .gold-line {
            height: 1px;
            background: linear-gradient(90deg, transparent, #d4a85355, transparent);
            margin: 0;
        }
    </style>

    <div class="dash-bg dash-wrap" dir="rtl">
        <div class="py-10">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Page heading -->
                <div style="margin-bottom: 32px;">
                    <p style="font-size: 10px; font-weight: 700; letter-spacing: 5px; text-transform: uppercase; color: #d4a853; margin: 0 0 8px 0;">OPTICVAULT</p>
                    <h1 style="font-size: 26px; font-weight: 700; color: #f1f3f9; margin: 0;">لوحة التحكم</h1>
                </div>

                <!-- Stats Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">

                    <div class="stat-card gold">
                        <div class="stat-label">إجمالي الصور</div>
                        <div class="stat-number gold">{{ $stats['total_images'] }}</div>
                        <div class="stat-sub">صورة مرفوعة</div>
                        <div class="stat-icon">🖼</div>
                    </div>

                    <div class="stat-card blue">
                        <div class="stat-label">الألبومات</div>
                        <div class="stat-number blue">{{ $stats['total_albums'] }}</div>
                        <div class="stat-sub">ألبوم مُنشأ</div>
                        <div class="stat-icon">📁</div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-label">النشاطات الأخيرة</div>
                        <div class="stat-number green">{{ count($stats['recent_activities']) }}</div>
                        <div class="stat-sub">نشاط مسجّل</div>
                        <div class="stat-icon">⚡</div>
                    </div>

                </div>

                <!-- Profile card -->
                <div class="profile-card" style="margin-bottom: 24px;">
                    @if(Auth::user()->avatar)
                        <img src="{{ Auth::user()->avatar }}" alt="{{ Auth::user()->name }}" class="profile-avatar">
                    @else
                        <div class="profile-avatar-placeholder">{{ mb_substr(Auth::user()->name, 0, 1) }}</div>
                    @endif
                    <div>
                        <div class="profile-name">{{ Auth::user()->name }}</div>
                        <div class="profile-email">{{ Auth::user()->email }}</div>
                        <div class="profile-joined">انضم {{ Auth::user()->created_at->diffForHumans() }}</div>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="profile-edit-btn">تعديل الملف</a>
                </div>

                <!-- Recent Activity -->
                <div class="activity-card">
                    <div class="activity-header">
                        <span class="activity-title">آخر النشاطات</span>
                        <a href="{{ route('activities.index') }}" class="view-all-link">عرض الكل ←</a>
                    </div>

                    @forelse($stats['recent_activities'] as $activity)
                        <div class="activity-row">
                            <div style="display: flex; align-items: center;">
                                <div class="activity-dot"></div>
                                <div>
                                    <div class="activity-desc">{{ $activity->description }}</div>
                                    <div class="activity-time">{{ $activity->created_at->diffForHumans() }}</div>
                                </div>
                            </div>
                            <div class="activity-badge">{{ basename($activity->subject_type ?? 'System') }}</div>
                        </div>
                    @empty
                        <div class="no-activity">لا يوجد نشاطات مسجلة حالياً.</div>
                    @endforelse
                </div>

            </div>
        </div>
    </div>

</x-app-layout>