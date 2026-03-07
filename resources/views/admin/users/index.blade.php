<x-app-layout>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap');
        .admin-wrap * { font-family: 'IBM Plex Sans Arabic', sans-serif; }
        .admin-bg { background-color: #0d0f14; min-height: 100vh; }
        .admin-card { background: #13151c; border: 1px solid #1e2130; border-radius: 16px; overflow: hidden; }
        .admin-header { padding: 24px; border-bottom: 1px solid #1e2130; }
        .admin-title { font-size: 18px; font-weight: 700; color: #f1f3f9; }
        .admin-search { background: #0d0f14; border: 1px solid #1e2130; border-radius: 10px; padding: 10px 16px; font-size: 14px; color: #f1f3f9; width: 100%; max-width: 280px; }
        .admin-search::placeholder { color: #4a5270; }
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th { font-size: 10px; font-weight: 700; letter-spacing: 2px; color: #4a5270; padding: 14px 20px; text-align: right; border-bottom: 1px solid #1e2130; }
        .admin-table td { padding: 16px 20px; border-bottom: 1px solid #0d0f14; color: #c8cfe0; font-size: 14px; }
        .admin-table tr:hover { background: #0d0f14; }
        .badge { font-size: 9px; font-weight: 700; letter-spacing: 1px; padding: 4px 10px; border-radius: 6px; }
        .badge-user { background: #0a0f1f; color: #60a5fa; }
        .badge-photographer { background: #0a1f10; color: #34d399; }
        .badge-admin { background: #1a0a0a; color: #d4a853; }
        .badge-banned { background: #1a0a0a; color: #f87171; }
        .badge-shadow { background: #1a0a1f; color: #a78bfa; }
        .btn-sm { font-size: 11px; font-weight: 600; padding: 6px 12px; border-radius: 8px; text-decoration: none; display: inline-block; margin-left: 6px; border: 1px solid; cursor: pointer; transition: opacity 0.2s; }
        .btn-sm:hover { opacity: 0.8; }
        .btn-gold { background: transparent; color: #d4a853; border-color: #d4a85355; }
        .btn-red { background: transparent; color: #f87171; border-color: #f8717155; }
        .btn-green { background: transparent; color: #34d399; border-color: #34d39955; }
    </style>

    <div class="admin-bg admin-wrap" dir="rtl">
        <div class="py-10">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <p style="font-size: 10px; font-weight: 700; letter-spacing: 5px; color: #d4a853; margin: 0 0 4px 0;">إدارة المستخدمين</p>
                        <h1 style="font-size: 24px; font-weight: 700; color: #f1f3f9; margin: 0;">المستخدمون</h1>
                    </div>
                    <div style="display: flex; gap: 16px;">
                        <a href="{{ route('admin.roles.index') }}" style="font-size: 13px; color: #d4a853; text-decoration: none;">إدارة الأدوار</a>
                        <a href="{{ route('admin.dashboard') }}" style="font-size: 13px; color: #8891aa; text-decoration: none;">← العودة للوحة</a>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                        <form method="GET" action="{{ route('admin.users.index') }}" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث بالاسم أو البريد..." class="admin-search">
                            <label style="display: flex; align-items: center; gap: 8px; color: #8891aa; font-size: 13px; cursor: pointer;">
                                <input type="checkbox" name="banned" value="1" {{ request('banned') ? 'checked' : '' }}> المحظورون فقط
                            </label>
                            <button type="submit" class="btn-sm btn-gold">بحث</button>
                        </form>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>المستخدم</th>
                                    <th>الدور</th>
                                    <th>الحالة</th>
                                    <th>التسجيل</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $u)
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: #f1f3f9;">{{ $u->name }}</div>
                                            <div style="font-size: 12px; color: #4a5270;">{{ $u->email }}</div>
                                        </td>
                                        <td>
                                            @foreach($u->roles as $r)
                                                @php
                                                    $badgeClass = match($r->name) {
                                                        'super-admin' => 'badge-admin',
                                                        'photographer' => 'badge-photographer',
                                                        'editor' => 'badge-photographer',
                                                        default => 'badge-user',
                                                    };
                                                @endphp
                                                <span class="badge {{ $badgeClass }}">{{ $r->name }}</span>
                                            @endforeach
                                        </td>
                                        <td>
                                            @if($u->is_banned) <span class="badge badge-banned">محظور</span> @endif
                                            @if($u->is_shadow_hidden) <span class="badge badge-shadow">حجب شامل</span> @endif
                                            @if(!$u->is_banned && !$u->is_shadow_hidden) <span style="color:#3d4460">—</span> @endif
                                        </td>
                                        <td style="font-size: 12px; color: #4a5270;">{{ $u->created_at->format('Y-m-d') }}</td>
                                        <td>
                                            <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                                @if($u->is_banned)
                                                    <form method="POST" action="{{ route('admin.users.unban', $u) }}" style="display: inline;">
                                                        @csrf
                                                        <button type="submit" class="btn-sm btn-green">إلغاء الحظر</button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('admin.users.ban', $u) }}" style="display: inline;">
                                                        @csrf
                                                        <button type="submit" class="btn-sm btn-red" onclick="return confirm('حظر هذا المستخدم؟')">حظر</button>
                                                    </form>
                                                @endif
                                                <form method="POST" action="{{ route('admin.users.shadow', $u) }}" style="display: inline;">
                                                    @csrf
                                                    <button type="submit" class="btn-sm btn-gold">{{ $u->is_shadow_hidden ? 'إلغاء الحجب' : 'حجب شامل' }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.users.role', $u) }}" style="display: inline;">
                                                    @csrf
                                                    <select name="role" onchange="this.form.submit()" style="background:#0d0f14;border:1px solid #1e2130;color:#c8cfe0;padding:6px 10px;border-radius:8px;font-size:12px;">
                                                        @foreach($roles as $r)
                                                            <option value="{{ $r->name }}" {{ $u->hasRole($r->name) ? 'selected' : '' }}>{{ $r->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </form>
                                                <a href="{{ route('admin.activities.index', ['user_id' => $u->id]) }}" class="btn-sm btn-gold">النشاط</a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" style="padding: 40px; text-align: center; color: #4a5270;">لا يوجد مستخدمين.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($users->hasPages())
                        <div style="padding: 20px; border-top: 1px solid #1e2130;">
                            {{ $users->links() }}
                        </div>
                    @endif
                </div>

                @if(session('success'))
                    <div style="margin-top: 16px; padding: 12px 20px; background: #0a1f10; border: 1px solid #34d39944; border-radius: 10px; color: #34d399; font-size: 14px;">
                        {{ session('success') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
