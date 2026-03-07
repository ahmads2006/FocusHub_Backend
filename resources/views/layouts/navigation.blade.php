<nav x-data="{ open: false }" dir="rtl"
    style="background-color: #0d0f14; border-bottom: 1px solid #1e2130; font-family: 'IBM Plex Sans Arabic', 'Segoe UI', sans-serif; position: sticky; top: 0; z-index: 50;">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap');

        .nav-link {
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 1.5px;
            color: #4a5270;
            text-decoration: none;
            padding: 6px 2px;
            border-bottom: 2px solid transparent;
            transition: color 0.2s, border-color 0.2s;
            white-space: nowrap;
        }
        .nav-link:hover {
            color: #c8cfe0;
        }
        .nav-link.active {
            color: #d4a853;
            border-bottom-color: #d4a853;
        }

        .nav-logo-text {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 4px;
            color: #f1f3f9;
        }
        .nav-logo-dot {
            color: #d4a853;
            margin-left: 2px;
        }

        /* User dropdown trigger */
        .user-trigger {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #13151c;
            border: 1px solid #1e2130;
            border-radius: 10px;
            padding: 7px 14px;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .user-trigger:hover { border-color: #2e3450; }
        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: #c8cfe0;
        }
        .user-avatar {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: #1e2130;
            border: 1px solid #d4a85355;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            color: #d4a853;
            overflow: hidden;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }

        /* Dropdown menu */
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            background: #13151c;
            border: 1px solid #1e2130;
            border-radius: 12px;
            min-width: 180px;
            padding: 8px;
            display: none;
            box-shadow: 0 16px 40px rgba(0,0,0,0.4);
        }
        .dropdown-menu.open { display: block; }
        .dropdown-item {
            display: block;
            padding: 10px 14px;
            font-size: 13px;
            color: #8891aa;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.15s, color 0.15s;
            font-weight: 500;
        }
        .dropdown-item:hover { background: #0d0f14; color: #f1f3f9; }
        .dropdown-item.danger { color: #f87171; }
        .dropdown-item.danger:hover { background: #f8717115; color: #f87171; }
        .dropdown-divider {
            border: none;
            border-top: 1px solid #1e2130;
            margin: 6px 0;
        }

        /* Mobile menu */
        .mobile-menu {
            background: #0d0f14;
            border-top: 1px solid #1e2130;
            padding: 16px 0;
        }
        .mobile-link {
            display: block;
            padding: 12px 20px;
            font-size: 13px;
            font-weight: 500;
            color: #4a5270;
            text-decoration: none;
            letter-spacing: 1px;
            transition: color 0.15s, background 0.15s;
        }
        .mobile-link:hover { color: #c8cfe0; background: #13151c; }
        .mobile-link.active { color: #d4a853; border-right: 2px solid #d4a853; }

        .mobile-user-block {
            padding: 16px 20px 12px 20px;
            border-top: 1px solid #1e2130;
            margin-top: 8px;
            text-align: right;
        }
        .mobile-user-name { font-size: 14px; font-weight: 700; color: #f1f3f9; }
        .mobile-user-email { font-size: 12px; color: #3d4460; margin-top: 2px; }

        /* Hamburger */
        .hamburger-btn {
            background: transparent;
            border: 1px solid #1e2130;
            border-radius: 8px;
            padding: 8px;
            color: #4a5270;
            cursor: pointer;
            transition: border-color 0.2s, color 0.2s;
        }
        .hamburger-btn:hover { border-color: #2e3450; color: #c8cfe0; }
    </style>

    <!-- Main bar -->
    <div style="max-width: 1280px; margin: 0 auto; padding: 0 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; height: 64px;">

            <!-- Logo -->
            <a href="{{ route('dashboard') }}" style="text-decoration: none; display: flex; align-items: center; gap: 6px;">
                <span class="nav-logo-dot" style="font-size: 14px;">✦</span>
                <span class="nav-logo-text">OPTICVAULT</span>
            </a>

            <!-- Desktop nav links -->
            <div style="display: none; align-items: center; gap: 28px;" class="nav-desktop">
                <a href="{{ route('dashboard') }}"
                   class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    لوحة التحكم
                </a>
                <a href="{{ route('images.test.index') }}"
                   class="nav-link {{ request()->routeIs('images.test.*') ? 'active' : '' }}">
                    إدارة الصور
                </a>
                <a href="{{ route('gallery.index') }}"
                   class="nav-link {{ request()->routeIs('gallery.index') ? 'active' : '' }}">
                    المعرض العام
                </a>
                <a href="{{ route('activities.index') }}"
                   class="nav-link {{ request()->routeIs('activities.index') ? 'active' : '' }}">
                    سجل النشاطات
                </a>
            </div>

            <!-- User section (desktop) -->
            <div style="display: none; align-items: center;" class="nav-desktop">
                <div style="position: relative;" x-data="{ dropOpen: false }">
                    <button class="user-trigger" @click="dropOpen = !dropOpen" @click.outside="dropOpen = false">
                        <div class="user-avatar">
                            @if(Auth::user()->avatar)
                                <img src="{{ Auth::user()->avatar }}" alt="">
                            @else
                                {{ mb_substr(Auth::user()->name, 0, 1) }}
                            @endif
                        </div>
                        <span class="user-name">{{ Auth::user()->name }}</span>
                        <svg style="width:12px;height:12px;color:#3d4460;transition:transform 0.2s;" :style="dropOpen ? 'transform:rotate(180deg)' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <!-- Dropdown -->
                    <div class="dropdown-menu" :class="{ 'open': dropOpen }" style="left: 0; right: auto;">
                        <a href="{{ route('profile.edit') }}" class="dropdown-item">الملف الشخصي</a>
                        <form method="POST" action="{{ route('verification.send') }}">
                            @csrf
                            <button type="submit" class="dropdown-item" style="width:100%; text-align:right; background:none; border:none; cursor:pointer; font-family:inherit;">
                                إرسال رمز التحقق
                            </button>
                        </form>
                        <hr class="dropdown-divider">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item danger" style="width:100%; text-align:right; background:none; border:none; cursor:pointer; font-family:inherit;">
                                تسجيل الخروج
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Hamburger (mobile) -->
            <button @click="open = !open" class="hamburger-btn nav-mobile">
                <svg style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path :class="{'hidden': open}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path :class="{'hidden': !open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

        </div>
    </div>

    <!-- Mobile menu -->
    <div :class="{'block': open, 'hidden': !open}" class="hidden mobile-menu">
        <a href="{{ route('dashboard') }}" class="mobile-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">لوحة التحكم</a>
        <a href="{{ route('images.test.index') }}" class="mobile-link {{ request()->routeIs('images.test.*') ? 'active' : '' }}">إدارة الصور</a>
        <a href="{{ route('gallery.index') }}" class="mobile-link {{ request()->routeIs('gallery.index') ? 'active' : '' }}">المعرض العام</a>
        <a href="{{ route('activities.index') }}" class="mobile-link {{ request()->routeIs('activities.index') ? 'active' : '' }}">سجل النشاطات</a>

        <div class="mobile-user-block">
            <div class="mobile-user-name">{{ Auth::user()->name }}</div>
            <div class="mobile-user-email">{{ Auth::user()->email }}</div>
            <div style="margin-top: 12px; display: flex; flex-direction: column; gap: 4px;">
                <a href="{{ route('profile.edit') }}" class="mobile-link" style="padding: 10px 0;">الملف الشخصي</a>
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="mobile-link" style="background:none;border:none;cursor:pointer;font-family:inherit;width:100%;text-align:right;padding:10px 0;">
                        إرسال رمز التحقق
                    </button>
                </form>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="mobile-link" style="background:none;border:none;cursor:pointer;font-family:inherit;width:100%;text-align:right;color:#f87171;padding:10px 0;">
                        تسجيل الخروج
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Responsive display helper -->
    <style>
        @media (min-width: 768px) {
            .nav-desktop { display: flex !important; }
            .nav-mobile  { display: none  !important; }
        }
        @media (max-width: 767px) {
            .nav-desktop { display: none  !important; }
            .nav-mobile  { display: flex  !important; }
        }
    </style>

</nav>