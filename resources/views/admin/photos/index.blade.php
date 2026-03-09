<x-app-layout>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap');
        .admin-wrap * { font-family: 'IBM Plex Sans Arabic', sans-serif; }
        .admin-bg { background-color: #0d0f14; min-height: 100vh; color: #f1f3f9; }
        .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; padding: 24px 0; }
        .photo-card {
            background: #13151c; border: 1px solid #1e2130; border-radius: 16px; overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative;
        }
        .photo-card:hover { transform: translateY(-4px); border-color: #d4a853; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .photo-img { width: 100%; height: 200px; object-fit: cover; background: #000; }
        .photo-info { padding: 16px; }
        .photo-title { font-size: 14px; font-weight: 600; margin-bottom: 4px; color: #f1f3f9; }
        .photo-meta { font-size: 12px; color: #4a5270; }
        .photo-actions { padding: 12px; background: rgba(0,0,0,0.3); display: flex; gap: 8px; justify-content: center; border-top: 1px solid #1e2130; }
        .badge { font-size: 10px; font-weight: 700; padding: 4px 8px; border-radius: 6px; text-transform: uppercase; }
        .badge-public { background: #34d39922; color: #34d399; }
        .badge-private { background: #60a5fa22; color: #60a5fa; }
        .badge-hidden { background: #f8717122; color: #f87171; }
        .btn-admin { font-size: 11px; font-weight: 700; padding: 8px 12px; border-radius: 8px; transition: all 0.2s; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; border: none; }
        .btn-hide { background: #1e2130; color: #c8cfe0; }
        .btn-hide:hover { background: #2e3450; }
        .btn-delete { background: #f8717111; color: #f87171; }
        .btn-delete:hover { background: #f87171; color: #fff; }
        .btn-ban { background: #d4a85322; color: #d4a853; border: 1px solid #d4a85344; }
        .btn-ban:hover { background: #d4a853; color: #000; }
        .nav-link-admin { color: #4a5270; font-size: 13px; font-weight: 600; text-decoration: none; padding-bottom: 8px; border-bottom: 2px solid transparent; transition: all 0.3s; }
        .nav-link-admin.active { color: #d4a853; border-color: #d4a853; }
    </style>

    <div class="admin-bg admin-wrap" dir="rtl">
        <div class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-end mb-8">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-gold mb-2" style="color:#d4a853">الإدارة المركزية</p>
                        <h1 class="text-3xl font-bold">إدارة الصور والرقابة</h1>
                    </div>
                    <div class="flex gap-6">
                        <a href="{{ route('admin.photos.index') }}" class="nav-link-admin {{ request()->routeIs('admin.photos.index') ? 'active' : '' }}">جميع الصور</a>
                        <a href="{{ route('admin.banned_hashes.index') }}" class="nav-link-admin {{ request()->routeIs('admin.banned_hashes.index') ? 'active' : '' }}">البصمات المحظورة</a>
                    </div>
                </div>

                <div class="photo-grid">
                    @forelse($images as $image)
                        <div class="photo-card">
                            <img src="{{ Storage::url($image->path) }}" alt="{{ $image->title }}" class="photo-img">
                            <div class="photo-info">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="badge badge-{{ $image->privacy }}">{{ $image->privacy }}</span>
                                    <span class="photo-meta">{{ $image->created_at->format('Y/m/d') }}</span>
                                </div>
                                <h3 class="photo-title">{{ $image->title ?: 'صورة بدون عنوان' }}</h3>
                                <p class="photo-meta">بواسطة: {{ $image->user->name }}</p>
                            </div>
                            <div class="photo-actions">
                                <form action="{{ route('admin.photos.visibility', $image) }}" method="POST">
                                    @csrf
                                    <button class="btn-admin btn-hide">
                                        {{ $image->privacy === 'hidden' ? 'إظهار' : 'إخفاء' }}
                                    </button>
                                </form>
                                <form action="{{ route('admin.photos.destroy', $image) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف النهائي؟');">
                                    @csrf @method('DELETE')
                                    <button class="btn-admin btn-delete">حذف</button>
                                </form>
                                <form action="{{ route('admin.photos.ban', $image) }}" method="POST" onsubmit="return confirm('سيتم حظر بصمة هذه الصورة ومنع رفعها نهائياً من أي مستخدم. هل تتابع؟');">
                                    @csrf
                                    <button class="btn-admin btn-ban">حظر البصمة (BAN)</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-20 text-center bg-gray-900 border border-gray-800 rounded-2xl text-gray-500">
                            لا توجد صور في النظام حالياً.
                        </div>
                    @endforelse
                </div>

                <div class="mt-8">
                    {{ $images->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
