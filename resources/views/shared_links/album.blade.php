<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $album->title }} | FocusHub Shared Vault</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #0a0a0c; color: #e1e1e6; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .accent-gradient { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); }
        .accent-text-gradient { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body class="antialiased min-h-screen bg-[#0a0a0c] selection:bg-purple-500/30 overflow-x-hidden" dir="rtl">
    
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-[25%] -left-[10%] w-[70%] h-[70%] rounded-full bg-purple-600/5 blur-[120px]"></div>
        <div class="absolute -bottom-[20%] -right-[10%] w-[60%] h-[60%] rounded-full bg-indigo-600/5 blur-[120px]"></div>
    </div>

    <div class="relative max-w-7xl mx-auto px-6 py-12 lg:py-24">
        <!-- Header Section -->
        <header class="text-center space-y-6 mb-20">
            <div class="inline-block px-4 py-1.5 rounded-full glass border-white/5 text-[10px] font-bold uppercase tracking-[0.2em] text-purple-400 mb-2">
                سلسلة مشاركة سرية
            </div>
            <h1 class="text-4xl md:text-6xl font-bold tracking-tight text-white">{{ $album->title }}</h1>
            <p class="max-w-2xl mx-auto text-gray-400 text-lg leading-relaxed">{{ $album->description }}</p>
            
            <div class="flex items-center justify-center gap-6 pt-4">
                <div class="flex items-center gap-3">
                    <img src="{{ $album->owner->avatar }}" class="w-10 h-10 rounded-full border border-purple-500/30 p-0.5">
                    <div class="text-right">
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">المصور الفوتوغرافي</p>
                        <p class="text-sm font-semibold">{{ $album->owner->name }}</p>
                    </div>
                </div>
                <div class="h-8 w-px bg-white/10"></div>
                <div class="text-right">
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">إجمالي الأصول</p>
                    <p class="text-sm font-semibold">{{ $album->photos->count() }} صورة</p>
                </div>
            </div>
        </header>

        <!-- Gallery Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($album->photos as $photo)
                <div class="group relative order-last md:order-none">
                    <div class="glass rounded-[32px] overflow-hidden border border-white/5 transition-all duration-500 hover:border-purple-500/30 hover:shadow-2xl hover:shadow-purple-500/5">
                        <div class="aspect-[4/3] overflow-hidden relative">
                            <img src="{{ $photo->url }}" alt="{{ $photo->title }}" class="w-full h-full object-cover transition-transform duration-1000 group-hover:scale-110">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            
                            @if($link->permission === 'download')
                            <div class="absolute top-4 left-4 opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-500">
                                <a href="{{ $photo->url }}" download class="glass p-3 rounded-2xl flex items-center justify-center text-white hover:bg-white/10 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                </a>
                            </div>
                            @endif
                        </div>
                        
                        <div class="p-8">
                            <h3 class="text-lg font-bold text-white mb-2">{{ $photo->title }}</h3>
                            <p class="text-sm text-gray-500 line-clamp-2 leading-relaxed">{{ $photo->description }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($album->photos->isEmpty())
            <div class="text-center py-40">
                <div class="inline-flex p-6 rounded-full bg-white/5 mb-6">
                    <svg class="w-12 h-12 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <h2 class="text-xl font-semibold text-gray-400">لا توجد صور في هذا الألبوم بعد.</h2>
            </div>
        @endif

        <!-- Footer -->
        <footer class="mt-32 text-center border-t border-white/5 pt-12">
            <p class="text-gray-500 text-sm">تم إنشاء هذا الألبوم ومشاركته عبر <span class="font-bold text-white">FocusHub</span></p>
            <p class="text-[10px] text-gray-600 uppercase tracking-widest mt-2 font-bold">Secure Asset Delivery Pipeline</p>
        </footer>
    </div>
</body>
</html>
