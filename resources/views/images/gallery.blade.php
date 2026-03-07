<x-app-layout>
 

    <style>
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap');

        .gallery-wrap * { font-family: 'IBM Plex Sans Arabic', 'Segoe UI', sans-serif; }

        .gallery-bg {
            background-color: #0d0f14;
            min-height: 100vh;
        }

        /* Page header */
        .page-eyebrow {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 5px;
            text-transform: uppercase;
            color: #d4a853;
            margin: 0 0 8px 0;
        }
        .page-title {
            font-size: 26px;
            font-weight: 700;
            color: #f1f3f9;
            margin: 0 0 6px 0;
        }
        .page-subtitle {
            font-size: 13px;
            color: #3d4460;
            margin: 0;
        }

        /* Grid */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }

        /* Image card */
        .img-card {
            position: relative;
            border-radius: 14px;
            overflow: hidden;
            aspect-ratio: 1 / 1;
            background: #13151c;
            border: 1px solid #1e2130;
            cursor: pointer;
        }
        .img-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.85;
            transition: opacity 0.4s ease, transform 0.5s ease;
            display: block;
        }
        .img-card:hover img {
            opacity: 1;
            transform: scale(1.04);
        }

        /* Gold top accent on hover */
        .img-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #d4a853, transparent);
            z-index: 3;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .img-card:hover::before { opacity: 1; }

        /* Overlay gradient */
        .img-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 55%, transparent 100%);
            z-index: 1;
            transition: opacity 0.3s;
        }

        /* Card info */
        .img-info {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            padding: 20px 16px 16px 16px;
            z-index: 2;
        }
        .img-title {
            font-size: 14px;
            font-weight: 700;
            color: #f1f3f9;
            margin: 0 0 4px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .img-author {
            font-size: 11px;
            color: #8891aa;
            margin: 0 0 2px 0;
        }
        .img-album {
            font-size: 10px;
            color: #d4a853;
            margin: 4px 0 0 0;
            font-weight: 600;
        }

        /* Tags */
        .tags-row {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 8px;
        }
        .tag-pill {
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 1px;
            color: #8891aa;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 3px 8px;
            border-radius: 4px;
            backdrop-filter: blur(4px);
        }

        /* Empty state */
        .empty-state {
            background: #13151c;
            border: 1px solid #1e2130;
            border-radius: 16px;
            padding: 80px 40px;
            text-align: center;
        }
        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
        }
        .empty-title {
            font-size: 18px;
            font-weight: 700;
            color: #3d4460;
            margin: 0 0 6px 0;
        }
        .empty-sub {
            font-size: 13px;
            color: #2a2f45;
        }

        /* Back link */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #4a5270;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: #d4a853; }

        /* Pagination wrapper */
        .pagination-wrap nav { --tw-text-opacity: 1; }
        .pagination-wrap .pagination span,
        .pagination-wrap .pagination a {
            background: #13151c !important;
            border-color: #1e2130 !important;
            color: #4a5270 !important;
            border-radius: 8px !important;
        }
        .pagination-wrap .pagination a:hover {
            background: #1e2130 !important;
            color: #c8cfe0 !important;
        }
        .pagination-wrap [aria-current="page"] span {
            background: #d4a853 !important;
            border-color: #d4a853 !important;
            color: #0d0f14 !important;
        }
    </style>

    <div class="gallery-bg gallery-wrap" dir="rtl">
        <div class="py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Header row -->
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <p class="page-eyebrow">OPTICVAULT</p>
                        <h1 class="page-title">المعرض العام</h1>
                        <p class="page-subtitle">الصور المشاركة من قِبَل جميع المستخدمين</p>
                    </div>
                    <a href="{{ route('images.test.index') }}" class="back-link">
                        <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        صوري
                    </a>
                </div>

                @if($images->isEmpty())
                    <!-- Empty state -->
                    <div class="empty-state">
                        <div class="empty-icon">🖼</div>
                        <p class="empty-title">لا توجد صور عامة بعد</p>
                        <p class="empty-sub">ستظهر هنا الصور التي يضبطها أصحابها على "عامة"</p>
                    </div>

                @else
                    <!-- Gallery grid -->
                    <div class="gallery-grid">
                        @foreach($images as $image)
                            <div class="img-card">
                                <img src="{{ $image->url }}" alt="{{ $image->title ?? 'صورة' }}" loading="lazy">
                                <div class="img-overlay"></div>
                                <div class="img-info">
                                    <p class="img-title">{{ $image->title ?? 'بدون عنوان' }}</p>
                                    <p class="img-author">{{ $image->user->name }}</p>
                                    @if($image->album)
                                        <p class="img-album">✦ {{ $image->album->title }}</p>
                                    @endif
                                    @if($image->tags->count() > 0)
                                        <div class="tags-row">
                                            @foreach($image->tags as $tag)
                                                <span class="tag-pill">#{{ $tag->name }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    @if($images->hasPages())
                        <div class="pagination-wrap" style="margin-top: 40px; display: flex; justify-content: center;">
                            {{ $images->links() }}
                        </div>
                    @endif
                @endif

            </div>
        </div>
    </div>

</x-app-layout>