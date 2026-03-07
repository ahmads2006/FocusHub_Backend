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
        .btn-sm { font-size: 11px; font-weight: 600; padding: 6px 12px; border-radius: 8px; text-decoration: none; display: inline-block; border: 1px solid; cursor: pointer; transition: opacity 0.2s; }
        .btn-gold { background: transparent; color: #d4a853; border-color: #d4a85355; }
        .btn-red { background: transparent; color: #f87171; border-color: #f8717155; }
        .btn-green { background: #0a1f10; color: #34d399; border-color: #34d39955; }
        .input-role { background: #0d0f14; border: 1px solid #1e2130; border-radius: 10px; padding: 10px 16px; font-size: 14px; color: #f1f3f9; min-width: 200px; }
    </style>

    <div class="admin-bg admin-wrap" dir="rtl">
        <div class="py-10">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <p style="font-size: 10px; font-weight: 700; letter-spacing: 5px; color: #d4a853; margin: 0 0 4px 0;">إدارة الأدوار</p>
                        <h1 style="font-size: 24px; font-weight: 700; color: #f1f3f9; margin: 0;">الأدوار والمراتب</h1>
                    </div>
                    <a href="{{ route('admin.dashboard') }}" style="font-size: 13px; color: #8891aa; text-decoration: none;">← العودة للوحة</a>
                </div>

                <div class="admin-card" style="margin-bottom: 24px;">
                    <div class="admin-header">
                        <span class="admin-title">إضافة دور جديد</span>
                    </div>
                    <div style="padding: 24px;">
                        <form method="POST" action="{{ route('admin.roles.store') }}" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                            @csrf
                            <input type="text" name="name" class="input-role" placeholder="اسم الدور (مثل: moderator)" required>
                            <button type="submit" class="btn-sm btn-green">إضافة</button>
                        </form>
                        @error('name')
                            <p style="color: #f87171; font-size: 13px; margin-top: 8px;">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-header">
                        <span class="admin-title">جميع الأدوار</span>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>الدور</th>
                                    <th>عدد المستخدمين</th>
                                    <th>إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($roles as $r)
                                    <tr>
                                        <td style="font-weight: 600; color: #f1f3f9;">{{ $r->name }}</td>
                                        <td style="color: #8891aa;">{{ $r->users_count }}</td>
                                        <td>
                                            @if(!in_array($r->name, ['super-admin', 'user']))
                                                <form method="POST" action="{{ route('admin.roles.destroy', $r) }}" style="display: inline;" onsubmit="return confirm('حذف هذا الدور؟ المستخدمون الحاليون سيُزال منهم هذا الدور.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-sm btn-red">حذف</button>
                                                </form>
                                            @else
                                                <span style="font-size: 11px; color: #4a5270;">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" style="padding: 40px; text-align: center; color: #4a5270;">لا توجد أدوار.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if(session('success'))
                    <div style="margin-top: 16px; padding: 12px 20px; background: #0a1f10; border: 1px solid #34d39944; border-radius: 10px; color: #34d399; font-size: 14px;">
                        {{ session('success') }}
                    </div>
                @endif

                <div style="margin-top: 24px; padding: 20px; background: #13151c; border: 1px solid #1e2130; border-radius: 12px;">
                    <p style="font-size: 12px; font-weight: 600; color: #4a5270; margin: 0 0 8px 0;">ترقية مستخدم من قاعدة البيانات</p>
                    <p style="font-size: 13px; color: #8891aa; margin: 0 0 12px 0;">استخدم الأمر التالي لترقية مستخدم لأي دور:</p>
                    <code style="display: block; background: #0d0f14; padding: 12px 16px; border-radius: 8px; font-size: 12px; color: #34d399; direction: ltr; text-align: left;">
                        php artisan user:promote البريد@example.com super-admin
                    </code>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
