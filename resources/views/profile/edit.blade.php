<x-app-layout>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap');
        .profile-wrap * { font-family: 'IBM Plex Sans Arabic', 'Segoe UI', sans-serif; }
        .profile-bg { background-color: #0d0f14; min-height: 100vh; }

        /* Layout */
        .profile-grid {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 768px) {
            .profile-grid { grid-template-columns: 1fr; }
            .profile-sidebar { display: flex; flex-direction: row; overflow-x: auto; gap: 4px; }
            .sidebar-item { white-space: nowrap; }
        }

        /* Sidebar */
        .profile-sidebar {
            background: #13151c;
            border: 1px solid #1e2130;
            border-radius: 16px;
            padding: 10px;
            position: sticky;
            top: 24px;
        }
        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            color: #4a5270;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            text-decoration: none;
        }
        .sidebar-item:hover { background: #0d0f14; color: #c8cfe0; }
        .sidebar-item.active { background: #0d0f14; color: #d4a853; }
        .sidebar-item.active .sidebar-dot { background: #d4a853; box-shadow: 0 0 6px #d4a85388; }
        .sidebar-item.danger { color: #4a5270; }
        .sidebar-item.danger:hover { color: #f87171; background: #f8717110; }
        .sidebar-dot { width: 6px; height: 6px; border-radius: 50%; background: #2a2f45; flex-shrink: 0; }
        .sidebar-divider { height: 1px; background: #1e2130; margin: 6px 4px; }

        /* Panel */
        .profile-panel {
            background: #13151c;
            border: 1px solid #1e2130;
            border-radius: 16px;
            overflow: hidden;
        }
        .panel-gold-bar {
            height: 2px;
            background: linear-gradient(90deg, transparent, #d4a853 40%, transparent);
        }
        .panel-gold-bar.danger-bar {
            background: linear-gradient(90deg, transparent, #f8717155 40%, transparent);
        }
        .panel-header {
            padding: 22px 28px;
            border-bottom: 1px solid #1e2130;
        }
        .panel-eyebrow {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: #d4a853;
            margin: 0 0 6px 0;
        }
        .panel-eyebrow.danger { color: #f87171; }
        .panel-title {
            font-size: 16px;
            font-weight: 700;
            color: #f1f3f9;
            margin: 0 0 3px 0;
        }
        .panel-sub {
            font-size: 12px;
            color: #3d4460;
            margin: 0;
        }
        .panel-body { padding: 28px; }

        /* Avatar section */
        .avatar-section {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 22px 28px;
            border-bottom: 1px solid #1e2130;
            background: #0d0f1488;
        }
        .avatar-ring {
            width: 72px; height: 72px;
            border-radius: 50%;
            border: 2px solid #d4a853;
            overflow: hidden;
            flex-shrink: 0;
            background: #1e2130;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: 700;
            color: #d4a853;
        }
        .avatar-ring img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-name { font-size: 16px; font-weight: 700; color: #f1f3f9; margin: 0 0 3px 0; }
        .avatar-email { font-size: 12px; color: #3d4460; margin: 0; }

        /* Form overrides — works with Breeze's existing partials */
        .profile-panel .max-w-xl { max-width: 100% !important; }

        /* Style Breeze form elements inside panels */
        .profile-panel label,
        .profile-panel .block { color: #8891aa !important; }

        .profile-panel input[type="text"],
        .profile-panel input[type="email"],
        .profile-panel input[type="password"],
        .profile-panel input[type="url"] {
            background: #0d0f14 !important;
            border: 1px solid #1e2130 !important;
            border-radius: 10px !important;
            color: #c8cfe0 !important;
            padding: 11px 14px !important;
            font-family: 'IBM Plex Sans Arabic', sans-serif !important;
            font-size: 13px !important;
            transition: border-color 0.2s, box-shadow 0.2s !important;
            outline: none !important;
        }
        .profile-panel input:focus {
            border-color: #d4a85366 !important;
            box-shadow: 0 0 0 3px rgba(212,168,83,0.07) !important;
        }
        .profile-panel input[type="password"]:focus {
            border-color: #60a5fa44 !important;
            box-shadow: 0 0 0 3px rgba(96,165,250,0.05) !important;
        }

        /* Breeze primary button */
        .profile-panel button[type="submit"]:not(.danger-btn) {
            background: linear-gradient(135deg, #d4a853, #f0c97a) !important;
            color: #0d0f14 !important;
            font-weight: 800 !important;
            letter-spacing: 1.5px !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 12px 22px !important;
            cursor: pointer !important;
            transition: opacity 0.2s !important;
            font-family: 'IBM Plex Sans Arabic', sans-serif !important;
        }
        .profile-panel button[type="submit"]:not(.danger-btn):hover { opacity: 0.88 !important; }

        /* Danger zone */
        .danger-panel .panel-gold-bar { background: linear-gradient(90deg, transparent, #f8717155 40%, transparent); }
        .danger-panel input:focus {
            border-color: #f8717144 !important;
            box-shadow: 0 0 0 3px rgba(248,113,113,0.05) !important;
        }
        .danger-panel button[type="submit"],
        .danger-panel button[type="button"] {
            background: transparent !important;
            color: #f87171 !important;
            border: 1px solid #4a1515 !important;
            font-weight: 700 !important;
            letter-spacing: 1.5px !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            border-radius: 10px !important;
            padding: 11px 22px !important;
            cursor: pointer !important;
            transition: background 0.2s !important;
            font-family: 'IBM Plex Sans Arabic', sans-serif !important;
        }
        .danger-panel button:hover { background: #f8717115 !important; }

        /* Verified badge */
        .verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: #34d399;
            background: #0a1f10;
            border: 1px solid #1a4a22;
            padding: 4px 10px;
            border-radius: 6px;
        }

        /* Breeze error text */
        .profile-panel .text-red-600,
        .profile-panel .text-red-500 { color: #f87171 !important; font-size: 11px !important; }

        /* Breeze success text */
        .profile-panel .text-green-600 { color: #34d399 !important; font-size: 11px !important; }

        /* Breeze link */
        .profile-panel a { color: #d4a853 !important; }
    </style>

    <div class="profile-bg profile-wrap" dir="rtl">
        <div class="py-10">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Page heading -->
                <div style="margin-bottom: 28px;">
                    <p style="font-size:10px;font-weight:700;letter-spacing:5px;text-transform:uppercase;color:#d4a853;margin:0 0 6px 0;">OPTICVAULT</p>
                    <h1 style="font-size:26px;font-weight:700;color:#f1f3f9;margin:0;">الملف الشخصي</h1>
                </div>

                <div class="profile-grid">

                    <!-- Sidebar nav -->
                    <div class="profile-sidebar">
                        <a href="#info" class="sidebar-item active">
                            <span class="sidebar-dot"></span>
                            معلومات الحساب
                        </a>
                        <a href="#password" class="sidebar-item">
                            <span class="sidebar-dot"></span>
                            كلمة المرور
                        </a>
                        <div class="sidebar-divider"></div>
                        <a href="#delete" class="sidebar-item danger">
                            <span class="sidebar-dot" style="background:#4a1515;"></span>
                            حذف الحساب
                        </a>
                    </div>

                    <!-- Panels -->
                    <div style="display: flex; flex-direction: column; gap: 16px;">

                        <!-- Avatar strip -->
                        <div class="profile-panel">
                            <div class="panel-gold-bar"></div>
                            <div class="avatar-section">
                                <div class="avatar-ring">
                                    @if(Auth::user()->avatar)
                                        <img src="{{ Auth::user()->avatar }}" alt="">
                                    @else
                                        {{ mb_substr(Auth::user()->name, 0, 1) }}
                                    @endif
                                </div>
                                <div>
                                    <p class="avatar-name">{{ Auth::user()->name }}</p>
                                    <p class="avatar-email">{{ Auth::user()->email }}</p>
                                    @if(Auth::user()->email_verified_at)
                                        <span class="verified-badge" style="margin-top:8px;display:inline-flex;">✓ بريد موثّق</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Profile info -->
                        <div class="profile-panel" id="info">
                            <div class="panel-gold-bar"></div>
                            <div class="panel-header">
                                <p class="panel-eyebrow">معلومات الحساب</p>
                                <p class="panel-title">تعديل البيانات الشخصية</p>
                                <p class="panel-sub">اسمك وبريدك الإلكتروني الظاهران في المنصة</p>
                            </div>
                            <div class="panel-body">
                                @include('profile.partials.update-profile-information-form')
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="profile-panel" id="password">
                            <div class="panel-gold-bar" style="background: linear-gradient(90deg, transparent, #60a5fa55 40%, transparent);"></div>
                            <div class="panel-header">
                                <p class="panel-eyebrow" style="color:#60a5fa;">كلمة المرور</p>
                                <p class="panel-title">تغيير كلمة المرور</p>
                                <p class="panel-sub">استخدم كلمة مرور قوية وفريدة لحماية حسابك</p>
                            </div>
                            <div class="panel-body">
                                @include('profile.partials.update-password-form')
                            </div>
                        </div>

                        <!-- Delete account -->
                        <div class="profile-panel danger-panel" id="delete">
                            <div class="panel-gold-bar"></div>
                            <div class="panel-header">
                                <p class="panel-eyebrow danger">منطقة الخطر</p>
                                <p class="panel-title" style="color:#f87171;">حذف الحساب</p>
                                <p class="panel-sub">بمجرد الحذف لا يمكن استرجاع بياناتك. تصرّف بحذر.</p>
                            </div>
                            <div class="panel-body">
                                @include('profile.partials.delete-user-form')
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

</x-app-layout>