<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $photo->title }} | FocusHub Shared Asset</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #0a0a0c; color: #e1e1e6; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-dark { background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .accent-gradient { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); }
    </style>
</head>
<body class="antialiased min-h-screen bg-[#0a0a0c] overflow-x-hidden" dir="rtl">
    
    <div class="fixed inset-0 pointer-events-none">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full bg-purple-600/5 blur-[120px]"></div>
    </div>

    <div class="relative min-h-screen flex flex-col p-6 lg:p-12">
        <div class="max-w-6xl mx-auto w-full flex-1 flex flex-col gap-12 justify-center">
            
            <!-- Asset Card -->
            <div class="glass-dark rounded-[40px] border border-white/5 overflow-hidden shadow-2xl flex flex-col lg:flex-row min-h-[600px]">
                <!-- Image Side -->
                <div class="lg:w-2/3 bg-black/40 flex items-center justify-center p-4 min-h-[400px]">
                    <img src="{{ $photo->url }}" alt="{{ $photo->title }}" class="max-w-full max-h-[80vh] shadow-2xl rounded-lg">
                </div>

                <!-- Info Side -->
                <div class="lg:w-1/3 p-8 lg:p-12 border-t lg:border-t-0 lg:border-r border-white/5 flex flex-col justify-between">
                    <div class="space-y-8">
                        <div>
                            <div class="inline-block px-3 py-1 rounded-full glass border-white/5 text-[8px] font-bold uppercase tracking-widest text-purple-400 mb-4">
                                Shared Asset
                            </div>
                            <h1 class="text-3xl font-bold text-white mb-4">{{ $photo->title }}</h1>
                            <p class="text-gray-400 leading-relaxed text-sm mb-6">{{ $photo->description }}</p>

                            {{-- SecureShield Status Indicator --}}
                            @php
                                $isWatermarked = $link->require_watermark ?? ($photo->user->dynamic_watermark ?? false);
                            @endphp
                            <div class="flex items-center gap-3 p-4 rounded-2xl {{ $isWatermarked ? 'bg-purple-500/10 border border-purple-500/20' : 'bg-white/5 border border-white/5' }}">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center {{ $isWatermarked ? 'bg-purple-500/20 text-purple-400' : 'bg-gray-500/20 text-gray-500' }}">
                                    @if($isWatermarked)
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    @else
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-[9px] font-bold uppercase tracking-widest {{ $isWatermarked ? 'text-purple-400' : 'text-gray-500' }}">SecureShield</p>
                                    <p class="text-[11px] font-semibold {{ $isWatermarked ? 'text-white' : 'text-gray-400' }}">
                                        {{ $isWatermarked ? 'حماية الهوية الرقمية مفعّلة' : 'الحماية غير مطلوبة لهذا الرابط' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Technical Stats -->
                        @if(!empty($photo->metadata))
                        <div class="grid grid-cols-2 gap-4">
                            @if(isset($photo->metadata['CameraModel']))
                            <div class="glass p-4 rounded-2xl border border-white/5">
                                <p class="text-[8px] font-bold text-gray-500 uppercase tracking-widest mb-1">الكاميرا</p>
                                <p class="text-xs font-semibold uppercase">{{ $photo->metadata['CameraModel'] }}</p>
                            </div>
                            @endif
                            @if(isset($photo->metadata['ApertureValue']))
                            <div class="glass p-4 rounded-2xl border border-white/5">
                                <p class="text-[8px] font-bold text-gray-500 uppercase tracking-widest mb-1">الفتحة</p>
                                <p class="text-xs font-semibold">{{ $photo->metadata['ApertureValue'] }}</p>
                            </div>
                            @endif
                            @if(isset($photo->metadata['ISO']))
                            <div class="glass p-4 rounded-2xl border border-white/5">
                                <p class="text-[8px] font-bold text-gray-500 uppercase tracking-widest mb-1">ISO</p>
                                <p class="text-xs font-semibold">{{ $photo->metadata['ISO'] }}</p>
                            </div>
                            @endif
                            <div class="glass p-4 rounded-2xl border border-white/5">
                                <p class="text-[8px] font-bold text-gray-500 uppercase tracking-widest mb-1">الأبعاد</p>
                                <p class="text-xs font-semibold">{{ $photo->width }} × {{ $photo->height }}</p>
                            </div>
                        </div>
                        @endif

                        <!-- Creator -->
                        <div class="flex items-center gap-4 p-4 glass rounded-[32px] border border-white/5">
                            <img src="{{ $photo->user->avatar }}" class="w-12 h-12 rounded-full border border-purple-500/30 p-0.5">
                            <div>
                                <p class="text-[8px] text-gray-500 font-bold uppercase tracking-widest">المصور</p>
                                <p class="text-sm font-bold">{{ $photo->user->name }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-8">
                        @if($link->permission === 'download' && $photo->allow_download)
                            <a href="{{ $photo->getOriginalUrl() }}" download class="w-full inline-flex items-center justify-center gap-3 accent-gradient p-5 rounded-3xl font-bold uppercase tracking-widest text-[10px] shadow-lg shadow-purple-500/20 hover:scale-[1.02] transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                تحميل الملف الأصلي
                            </a>
                        @elseif($link->permission === 'download' && !$photo->allow_download)
                            <div class="w-full text-center glass p-5 rounded-3xl text-yellow-500 text-[10px] font-bold uppercase tracking-widest border border-yellow-500/20">
                                <svg class="w-4 h-4 inline-block mb-1 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg><br>
                                التنزيل معطل من قبل المصور
                            </div>
                        @else
                            <div class="w-full text-center glass p-5 rounded-3xl text-gray-500 text-[10px] font-bold uppercase tracking-widest">
                                العرض فقط متاح حالياً
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <footer class="text-center pt-12">
            <p class="text-gray-500 text-xs">مشاركة آمنة عبر <span class="text-white font-bold tracking-tight">Focus<span class="text-purple-500">Hub</span></span></p>
        </footer>
    </div>
</body>
</html>
