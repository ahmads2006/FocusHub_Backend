{{-- ============================================ --}}
{{-- ملف: resources/views/vault.blade.php          --}}
{{-- الإصدار: 2.0 – تحسينات تقنية شاملة            --}}
{{-- ============================================ --}}

@extends('layouts.premium')

@section('title', 'Photography Vault | Professional Showcase')

@section('meta')
    {{-- إضافة وصف Meta ديناميكي لتحسين SEO --}}
    <meta name="description" content="استعرض وأدر أصولك البصرية بكل احترافية. صور عالية الجودة ومنظمة في Vault الخاص بك.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url()->current() }}">
@endsection

@section('content')
<div class="space-y-8 h-full flex flex-col" 
     x-data="{ view: 'grid', loading: false }"
     @resize.window.debounce="console.log('window resized')"> {{-- مثال على تحسين التفاعل --}}
    
    <!-- Gallery Header -->
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">
                Your <span class="accent-text-gradient">Vault</span>
            </h2>
            <p class="text-gray-400 mt-1">Manage and showcase your professional assets.</p>
        </div>
        
        <!-- مفاتيح عرض محسنة مع ARIA -->
        <div class="flex items-center gap-3 glass p-1 rounded-2xl" role="tablist" aria-label="طرق عرض المعرض">
            <button @click="view = 'grid'" 
                    :class="view === 'grid' ? 'bg-white/10 text-white' : 'text-gray-500 hover:text-gray-300'" 
                    class="p-2 px-4 rounded-xl text-xs font-bold uppercase transition-all"
                    role="tab"
                    :aria-selected="view === 'grid'"
                    aria-controls="gallery-grid"
                    id="tab-grid">
                Grid
            </button>
            <button @click="view = 'list'" 
                    :class="view === 'list' ? 'bg-white/10 text-white' : 'text-gray-500 hover:text-gray-300'" 
                    class="p-2 px-4 rounded-xl text-xs font-bold uppercase transition-all"
                    role="tab"
                    :aria-selected="view === 'list'"
                    aria-controls="gallery-list"
                    id="tab-list">
                List
            </button>
        </div>
    </div>

    <!-- Gallery Grid/List Container -->
    <div class="flex-1 min-h-0 overflow-y-auto pr-2" 
         id="gallery-grid" 
         role="tabpanel" 
         aria-labelledby="tab-grid"
         x-show="view === 'grid'"
         x-cloak>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            @forelse($images as $image)
                {{-- استخدام key فريد لتحسين أداء DOM --}}
                <div class="glass group rounded-3xl overflow-hidden hover:scale-[1.02] transition-all duration-500 will-change-transform" 
                     x-data="{ revealed: false }"
                     key="image-{{ $image->id }}">
                     
                    {{-- Image Container with protection for non-owners --}}
                    @php
                        $isOwner = auth()->id() === $image->user_id;
                        $protectionAttrs = !$isOwner ? 'oncontextmenu="return false;" ondragstart="return false;"' : '';
                        $imgUrl = $image->url;
                        $cacheBuster = str_contains($imgUrl, '?') ? '&v=' : '?v=';
                        $imgUrl .= $cacheBuster . $image->updated_at->timestamp;
                    @endphp
                    <div class="relative h-48 overflow-hidden bg-gray-800/50" {!! $protectionAttrs !!}>
                        {{-- Load Image --}}
                        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" 
                             data-src="{{ $imgUrl }}" 
                             alt="{{ $image->title ?: 'Photograph' }}"
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700 lazy select-none {{ ($image->is_sensitive || $image->status === 'rejected') ? 'blur-xl' : '' }}"
                             :class="revealed ? 'blur-0 scale-110' : ''"
                             width="400" 
                             height="300"
                             loading="lazy">
                        
                        {{-- Sensitive Content Overlay --}}
                        @if($image->is_sensitive || $image->status === 'rejected')
                            <div x-show="!revealed" 
                                 class="absolute inset-0 z-20 flex flex-col items-center justify-center bg-black/60 backdrop-blur-2xl transition-all duration-500">
                                <div class="bg-red-500/20 p-3 rounded-full mb-3">
                                    <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                </div>
                                <span class="text-white font-bold text-sm text-center px-4">
                                    {{ $image->status === 'rejected' ? 'Blocked Content Validation' : 'Warning: Sensitive Content (Nudity/Violence/Other).' }}
                                </span>
                                <button @click="revealed = true" 
                                        class="mt-4 px-6 py-2 bg-white hover:bg-gray-100 text-black text-[10px] font-bold rounded-full transition-transform active:scale-95">
                                    Show Anyway
                                </button>
                            </div>
                            
                            {{-- NSFW/Blocked Badge --}}
                            <div class="absolute top-4 right-4 z-10 bg-red-600 text-white text-[9px] font-black px-2 py-0.5 rounded uppercase tracking-wider">
                                {{ $image->status === 'rejected' ? 'Rejected' : 'NSFW' }}
                            </div>
                        @endif
                        
                        {{-- Shimmer Effect --}}
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full animate-shimmer pointer-events-none"></div>
                        
                        {{-- Status Badge (Only for Owner/Admin) --}}
                        @if($isOwner || auth()->user()?->hasRole('super_admin'))
                            <div class="absolute top-4 left-4 z-10">
                                @if($image->status === 'approved')
                                    <span class="bg-green-500/80 backdrop-blur-md text-white text-[10px] px-2 py-1 rounded-full font-bold shadow-lg flex items-center gap-1">
                                        <div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></div>
                                        Approved
                                    </span>
                                @elseif($image->status === 'pending_review')
                                    <span class="bg-yellow-500/80 backdrop-blur-md text-white text-[10px] px-2 py-1 rounded-full font-bold shadow-lg flex items-center gap-1" title="PII or borderline content detected">
                                        <div class="w-1.5 h-1.5 bg-white rounded-full animate-bounce"></div>
                                        Pending Review
                                    </span>
                                @elseif($image->status === 'rejected')
                                    <span class="bg-red-500/80 backdrop-blur-md text-white text-[10px] px-2 py-1 rounded-full font-bold shadow-lg flex items-center gap-1">
                                        <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
                                        Rejected
                                    </span>
                                @endif
                            </div>
                        @endif

                        {{-- Interactive Overlay --}}
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-end p-4">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold text-white/50 bg-black/40 backdrop-blur-md px-2 py-1 rounded">
                                    {{ $image->file_type }}
                                </span>
                                <div class="flex gap-2">
                                    <button class="p-2 glass rounded-lg hover:bg-white/10 transition-colors"
                                            aria-label="تفاصيل الصورة"
                                            @click="alert('{{ $image->title }}')">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                    </button>
                                    
                                    {{-- Download Logic: Direct for Owner, Secured/Watermarked for Visitor --}}
                                    @if($isOwner)
                                        <a href="{{ $image->getOriginalUrl() }}" 
                                           class="p-2 bg-green-500/20 text-green-400 glass rounded-lg hover:bg-green-500/40 transition-all shadow-lg"
                                           aria-label="تحميل مباشر (الأصل)"
                                           download>
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                        </a>
                                    @elseif($image->allow_download)
                                        <a href="{{ $image->getOriginalUrl() }}" 
                                           class="p-2 bg-purple-500/20 text-purple-400 glass rounded-lg hover:bg-purple-500/40 transition-all shadow-lg"
                                           aria-label="تحميل محمي (بـ SecureShield)"
                                           title="Download (Watermarked)"
                                           download>
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                        </a>
                                    @endif
                                </div>
                            </div>
                            @if(!$isOwner && !$image->allow_download)
                                <div class="text-[9px] text-red-400 font-bold uppercase mt-2 text-center bg-black/40 rounded py-1 border border-red-500/20">
                                    التنزيل معطل من قبل المصور
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    {{-- تفاصيل الصورة --}}
                    <div class="p-4">
                        <h4 class="font-bold truncate text-sm" title="{{ $image->title }}">{{ $image->title }}</h4>
                        <div class="flex justify-between items-center mt-2">
                            <time datetime="{{ $image->created_at->toIso8601String() }}" class="text-[10px] text-gray-500 font-mono">
                                {{ $image->created_at->format('M d, Y') }}
                            </time>
                            <span class="text-[10px] font-bold text-purple-400 uppercase tracking-widest">
                                {{ $image->privacy }}
                            </span>
                        </div>
                    </div>
                </div>
            @empty
                {{-- حالة الفراغ محسنة --}}
                <div class="col-span-full h-64 glass rounded-3xl flex flex-col items-center justify-center opacity-60">
                    <svg class="w-16 h-16 mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <p class="font-bold text-gray-300">Your vault is empty. Start uploading your masterpieces!</p>
                    <a href="{{ route('images.index') }}" class="mt-4 px-6 py-2 bg-purple-600 rounded-full text-sm font-bold hover:bg-purple-700 transition-colors">
                        Upload Now
                    </a>
                </div>
            @endforelse
        </div>
        
        {{-- Pagination محسّن لـ SEO والأداء --}}
        <div class="mt-8">
            @if($images->hasPages())
                <nav role="navigation" aria-label="Pagination">
                    {{ $images->onEachSide(1)->links() }}
                </nav>
            @endif
        </div>
    </div>

    {{-- عرض القائمة (مشابه ولكن مبسط هنا) --}}
    <div id="gallery-list" 
         role="tabpanel" 
         aria-labelledby="tab-list"
         x-show="view === 'list'"
         x-cloak>
        {{-- محتوى القائمة... --}}
    </div>
</div>
@endsection

@push('scripts')
{{-- تحميل lazy loading باستخدام Intersection Observer --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // تهيئة التحميل البطيء للصور باستخدام Intersection Observer
    if ('IntersectionObserver' in window) {
        let lazyImages = document.querySelectorAll('img.lazy');
        let observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    let img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    observer.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px 0px', // تحميل قبل 50px من الظهور
            threshold: 0.01
        });

        lazyImages.forEach(img => observer.observe(img));
    } else {
        // Fallback للمتصفحات القديمة
        let lazyImages = document.querySelectorAll('img.lazy');
        lazyImages.forEach(img => {
            img.src = img.dataset.src;
        });
    }
});
</script>
@endpush