<x-app-layout>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap');

        .mgmt-wrap * { font-family: 'IBM Plex Sans Arabic', 'Segoe UI', sans-serif; }
        .mgmt-bg { background-color: #0d0f14; min-height: 100vh; }

        /* Cards */
        .panel {
            background: #13151c;
            border: 1px solid #1e2130;
            border-radius: 16px;
            padding: 28px;
        }
        .panel-title {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: #d4a853;
            margin: 0 0 20px 0;
        }

        /* Flash message */
        .flash-success {
            background: #0d1a0e;
            border: 1px solid #1a4a1e;
            border-right: 3px solid #34d399;
            border-radius: 10px;
            padding: 14px 18px;
            font-size: 13px;
            color: #34d399;
            margin-bottom: 24px;
            font-weight: 500;
        }

        /* Form elements */
        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #4a5270;
            margin-bottom: 8px;
        }
        .form-input {
            width: 100%;
            background: #0d0f14;
            border: 1px solid #1e2130;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 13px;
            color: #c8cfe0;
            font-family: 'IBM Plex Sans Arabic', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
            box-sizing: border-box;
        }
        .form-input:focus {
            border-color: #d4a85366;
            box-shadow: 0 0 0 3px rgba(212,168,83,0.07);
        }
        .form-input option { background: #13151c; }

        /* File input */
        .file-drop {
            background: #0d0f14;
            border: 1px dashed #2e3450;
            border-radius: 10px;
            padding: 28px;
            text-align: center;
            transition: border-color 0.2s;
            cursor: pointer;
            position: relative;
        }
        .file-drop:hover { border-color: #d4a85355; }
        .file-drop input[type="file"] {
            position: absolute; inset: 0;
            width: 100%; height: 100%;
            opacity: 0; cursor: pointer;
        }
        .file-drop-icon { font-size: 28px; margin-bottom: 8px; opacity: 0.4; }
        .file-drop-label { font-size: 13px; color: #3d4460; }
        .file-drop-sub { font-size: 11px; color: #2a2f45; margin-top: 4px; }

        /* Checkbox */
        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .checkbox-row input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: #d4a853;
            cursor: pointer;
        }
        .checkbox-row label {
            font-size: 13px;
            color: #8891aa;
            cursor: pointer;
        }

        /* Buttons */
        .btn-gold {
            width: 100%;
            background: linear-gradient(135deg, #d4a853, #f0c97a);
            color: #0d0f14;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 13px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.1s;
            font-family: 'IBM Plex Sans Arabic', sans-serif;
        }
        .btn-gold:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-gold:active { transform: translateY(0); }

        .btn-outline-gold {
            width: 100%;
            background: transparent;
            color: #d4a853;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 12px;
            border: 1px solid #d4a85355;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
            font-family: 'IBM Plex Sans Arabic', sans-serif;
        }
        .btn-outline-gold:hover { background: #d4a85315; border-color: #d4a853; }

        /* Gallery grid */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
        }
        .img-card {
            background: #0d0f14;
            border: 1px solid #1e2130;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: border-color 0.2s, transform 0.2s;
        }
        .img-card:hover { border-color: #2e3450; transform: translateY(-2px); }

        .img-thumb {
            width: 100%;
            aspect-ratio: 4/3;
            object-fit: cover;
            display: block;
        }

        .img-body { padding: 14px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .img-name {
            font-size: 13px;
            font-weight: 600;
            color: #c8cfe0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }
        .img-album-label {
            font-size: 10px;
            color: #d4a853;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .tags-row { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 10px; }
        .tag-pill {
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 1px;
            color: #4a5270;
            background: #13151c;
            border: 1px solid #1e2130;
            padding: 3px 7px;
            border-radius: 4px;
        }

        .img-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 6px;
        }
        .privacy-badge {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 5px;
        }
        .privacy-public { background: #0a1f10; color: #34d399; border: 1px solid #1a4a22; }
        .privacy-private { background: #1a0a0a; color: #f87171; border: 1px solid #4a1515; }

        .img-size { font-size: 10px; color: #2a2f45; }

        .btn-delete {
            width: 100%;
            margin-top: 10px;
            background: transparent;
            border: 1px solid #4a1515;
            color: #f87171;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
            font-family: 'IBM Plex Sans Arabic', sans-serif;
        }
        .btn-delete:hover { background: #f8717115; }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #2a2f45;
        }
        .empty-state-icon { font-size: 40px; margin-bottom: 12px; opacity: 0.3; }
        .empty-state p { font-size: 14px; }

        /* CTA Banner */
        .cta-banner {
            background: #13151c;
            border: 1px solid #1e2130;
            border-radius: 16px;
            padding: 32px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
        }
        .cta-banner::before {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #d4a85355, transparent);
        }
        .cta-text-label { font-size: 10px; font-weight: 700; letter-spacing: 4px; color: #d4a853; margin-bottom: 6px; }
        .cta-text-main { font-size: 18px; font-weight: 700; color: #f1f3f9; margin: 0; }
        .cta-text-sub { font-size: 13px; color: #3d4460; margin-top: 4px; }
        .btn-cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #d4a853, #f0c97a);
            color: #0d0f14;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 13px 24px;
            border-radius: 10px;
            text-decoration: none;
            white-space: nowrap;
            transition: opacity 0.2s, transform 0.1s;
        }
        .btn-cta:hover { opacity: 0.9; transform: translateY(-1px); }

        /* Two-col grid */
        .top-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 16px;
            margin-bottom: 24px;
        }
        @media (max-width: 767px) {
            .top-grid { grid-template-columns: 1fr; }
        }
        .upload-inner-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 640px) {
            .upload-inner-grid { grid-template-columns: 1fr; }
        }
    </style>

    <div class="mgmt-bg mgmt-wrap" dir="rtl">
        <div class="py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Page heading -->
                <div style="margin-bottom: 28px;">
                    <p style="font-size:10px;font-weight:700;letter-spacing:5px;text-transform:uppercase;color:#d4a853;margin:0 0 6px 0;">OPTICVAULT</p>
                    <h1 style="font-size:26px;font-weight:700;color:#f1f3f9;margin:0;">إدارة الصور والألبومات</h1>
                </div>

                <!-- Flash -->
                @if(session('success'))
                    <div class="flash-success">✓ {{ session('success') }}</div>
                @endif

                <!-- Top panels: Create album + Upload image -->
                <div class="top-grid">

                    <!-- Create album -->
                    <div class="panel">
                        <p class="panel-title">ألبوم جديد</p>
                        <form action="{{ route('albums.test.store') }}" method="POST" style="display:flex;flex-direction:column;gap:16px;">
                            @csrf
                            <div>
                                <label class="form-label">عنوان الألبوم</label>
                                <input type="text" name="title" required class="form-input" placeholder="اسم الألبوم...">
                            </div>
                            <div class="checkbox-row">
                                <input type="checkbox" name="is_private" id="is_private" value="1">
                                <label for="is_private">ألبوم خاص (Private)</label>
                            </div>
                            <button type="submit" class="btn-outline-gold">إنشاء الألبوم</button>
                        </form>
                    </div>

                    <!-- Upload image -->
                    <div class="panel">
                        <p class="panel-title">رفع صورة جديدة</p>
                        <form action="{{ route('images.test.store') }}" method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:16px;">
                            @csrf

                            <!-- File drop zone -->
                            <div class="file-drop">
                                <input type="file" name="image" accept="image/*" required>
                                <div class="file-drop-icon">📁</div>
                                <p class="file-drop-label">اسحب الصورة هنا أو انقر للاختيار</p>
                                <p class="file-drop-sub">PNG, JPG, WEBP</p>
                            </div>

                            <div class="upload-inner-grid">
                                <div>
                                    <label class="form-label">العنوان</label>
                                    <input type="text" name="title" class="form-input" placeholder="عنوان الصورة...">
                                </div>
                                <div>
                                    <label class="form-label">الألبوم</label>
                                    <select name="album_id" class="form-input">
                                        <option value="">بدون ألبوم</option>
                                        @foreach($albums as $album)
                                            <option value="{{ $album->id }}">{{ $album->title }} — {{ $album->is_private ? 'خاص' : 'عام' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">الوسوم</label>
                                    <input type="text" name="tags" class="form-input" placeholder="طبيعة, مدينة, ...">
                                </div>
                                <div>
                                    <label class="form-label">الخصوصية</label>
                                    <select name="privacy" class="form-input">
                                        <option value="public">عامة (Public)</option>
                                        <option value="private">خاصة (Private)</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="btn-gold">رفع الصورة ومعالجتها</button>
                        </form>
                    </div>

                </div>

                <!-- My Gallery -->
                <div class="panel" style="margin-bottom: 24px;">
                    <p class="panel-title">معرض صوري</p>

                    @if($images->isEmpty())
                        <div class="empty-state">
                            <div class="empty-state-icon">🖼</div>
                            <p>لا توجد صور بعد. ارفع صورتك الأولى!</p>
                        </div>
                    @else
                        <div class="gallery-grid">
                            @foreach($images as $image)
                                <div class="img-card">
                                    <img src="{{ $image->url }}" class="img-thumb" alt="{{ $image->title ?? $image->filename }}" loading="lazy">
                                    <div class="img-body">
                                        <div>
                                            <p class="img-name">{{ $image->title ?? $image->filename }}</p>
                                            <p class="img-album-label">
                                                @if($image->album)
                                                    ✦ {{ $image->album->title }}
                                                @else
                                                    <span style="color:#2a2f45;">بدون ألبوم</span>
                                                @endif
                                            </p>
                                            @if($image->tags->count() > 0)
                                                <div class="tags-row">
                                                    @foreach($image->tags as $tag)
                                                        <span class="tag-pill">#{{ $tag->name }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="img-footer">
                                                <span class="privacy-badge {{ $image->privacy === 'public' ? 'privacy-public' : 'privacy-private' }}">
                                                    {{ $image->privacy === 'public' ? 'عام' : 'خاص' }}
                                                </span>
                                                <span class="img-size">{{ number_format($image->size / 1024, 1) }} KB</span>
                                            </div>
                                            <form action="{{ route('images.test.destroy', $image) }}" method="POST"
                                                  onsubmit="return confirm('هل أنت متأكد من الحذف النهائي؟');">
                                                @csrf @method('DELETE')
                                                <button class="btn-delete" type="submit">حذف نهائي</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- CTA Banner -->
                <div class="cta-banner">
                    <div>
                        <p class="cta-text-label">اكتشف المزيد</p>
                        <p class="cta-text-main">المعرض العام</p>
                        <p class="cta-text-sub">تصفّح ما يشاركه المستخدمون الآخرون</p>
                    </div>
                    <a href="{{ route('gallery.index') }}" class="btn-cta">
                        استكشف المعرض
                        <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>