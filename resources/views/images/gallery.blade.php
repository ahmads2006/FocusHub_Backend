{{-- ============================================ --}}
{{-- ملف: resources/views/images/gallery.blade.php --}}
{{-- الإصدار: 4.0 – Cinematic Glassmorphism Masonry --}}
{{-- ============================================ --}}

@extends('layouts.premium')

@section('title', 'Photography Vault | Professional Showcase')

@section('meta')
    <meta name="description" content="استعرض وأدر أصولك البصرية بكل احترافية.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
@endsection

@push('styles')
<style>
  :root {
    --ink:        #030305;
    --border:     rgba(255,255,255,0.06);
    --border-hi:  rgba(255,255,255,0.15);
    --text:       #ffffff;
    --text-dim:   #8a8a9a;
    --rose:       #f43f5e;
    --violet:     #8b5cf6;
    --cyan:       #06b6d4;
  }
  body { background-color: var(--ink); color: var(--text); }
  
  .bg-glow {
    position: fixed; inset: 0; z-index: -1;
    background: radial-gradient(circle at 15% 50%, rgba(139,92,246,0.08) 0%, transparent 40%),
                radial-gradient(circle at 85% 30%, rgba(6,182,212,0.08) 0%, transparent 40%);
  }

  .font-display { font-family: 'Outfit', sans-serif; }
  .font-body { font-family: 'Outfit', sans-serif; }

  /* True Glassmorphism Card */
  .glass-card {
    background: rgba(255,255,255,0.03);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid var(--border);
    border-radius: 24px;
    box-shadow: 0 4px 30px rgba(0,0,0,0.1);
    transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
  }
  .glass-card:hover {
    border-color: var(--border-hi);
    background: rgba(255,255,255,0.06);
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.4);
  }

  /* Masonry Grid Setup */
  .masonry-grid {
    column-count: 1; column-gap: 1.5rem;
  }
  @media (min-width: 640px) { .masonry-grid { column-count: 2; } }
  @media (min-width: 768px) { .masonry-grid { column-count: 3; } }
  @media (min-width: 1280px){ .masonry-grid { column-count: 4; } }
  .masonry-item { break-inside: avoid; margin-bottom: 1.5rem; }
  
  /* Floating elements */
  .floating-pill { background: rgba(255,255,255,0.05); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 9999px; }

  /* Search Bar */
  .search-glass {
    background: rgba(255,255,255,0.05); backdrop-filter: blur(20px); border: 1px solid var(--border); border-radius: 99px; transition: all 0.3s ease;
  }
  .search-glass:focus-within {
    border-color: rgba(255,255,255,0.3); background: rgba(255,255,255,0.1); box-shadow: 0 0 30px rgba(255,255,255,0.05);
  }

  .like-btn { cursor:pointer; color: var(--text-dim); transition: 0.2s; }
  .like-btn:hover, .like-btn.liked { color: var(--rose); }

  .fade-up { animation: fadeUp .6s cubic-bezier(.23,1,.32,1) both; }
  @keyframes fadeUp { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }

  .hide-scrollbar::-webkit-scrollbar { display: none; }
  .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

  /* Appeal Modal (Glass) */
  .appeal-modal { background: rgba(10,10,15,0.85); backdrop-filter: blur(30px); border: 1px solid var(--border-hi); border-radius: 24px; }
  .modal-input {
    width: 100%;
    background: rgba(255,255,255,0.04); border: 1px solid var(--border);
    border-radius: 12px; padding: 12px 16px;
    color: var(--text); outline: none; transition: border-color .2s; font-family: 'Outfit', sans-serif; font-size: 13px;
  }
  .modal-input:focus { border-color: var(--cyan); }
  
  /* List Row */
  .list-row {
    display: grid; grid-template-columns: 72px 1fr auto; align-items: center; gap: 16px;
    background: rgba(255,255,255,0.03); backdrop-filter: blur(12px); border: 1px solid var(--border);
    border-radius: 16px; padding: 12px 16px; transition: all .3s;
  }
  .list-row:hover { border-color: var(--border-hi); background: rgba(255,255,255,0.06); transform: translateX(4px); }
</style>
@endpush

@section('content')
<div class="bg-glow"></div>
<div class="font-body space-y-10 h-full flex flex-col relative z-10" x-data="galleryPage()" @resize.window.debounce="() => {}">

  {{-- ── Cinematic Header ── --}}
  <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 pt-6">
    <div class="space-y-4">
      <div class="inline-flex items-center gap-2 px-3 py-1.5 floating-pill">
        <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
        <span class="text-[10px] tracking-widest uppercase font-semibold text-cyan-100">OpticVault Archive</span>
      </div>
      <h2 class="font-display text-5xl md:text-6xl font-light tracking-tight leading-tight">
        Visual <span class="font-semibold text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-violet-400">Masterpieces</span>
      </h2>
      <p class="text-sm md:text-base font-light text-gray-400 max-w-xl">
        Discover highly curated, AI-analyzed photography. Scroll freely through a world of premium visual assets.
      </p>
    </div>

    <div class="floating-pill p-1 flex items-center gap-1 self-start md:self-end">
      <button @click="view = 'grid'" :class="view === 'grid' ? 'bg-white/10 text-white' : 'text-gray-500 hover:text-gray-300'" class="px-4 py-2 rounded-full text-[10px] font-bold tracking-widest uppercase transition-all">
        Masonry
      </button>
      <button @click="view = 'list'" :class="view === 'list' ? 'bg-white/10 text-white' : 'text-gray-500 hover:text-gray-300'" class="px-4 py-2 rounded-full text-[10px] font-bold tracking-widest uppercase transition-all">
        List
      </button>
    </div>
  </div>

  {{-- ── Search & Filters Row ── --}}
  <div class="flex flex-col lg:flex-row gap-4 items-center justify-between" x-data="{ focused: false }">
    <form action="{{ route('images.gallery') }}" method="GET" class="w-full lg:w-1/3 block">
      <div class="search-glass flex items-center gap-3 px-6 py-3.5">
        <svg class="w-5 h-5 transition-colors" :class="focused ? 'text-white' : 'text-gray-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" name="q" value="{{ $searchQuery ?? '' }}" placeholder="AI Search (e.g., mountains, nature)..."
               class="flex-1 bg-transparent border-none outline-none font-body text-sm text-white placeholder-gray-500"
               @focus="focused = true" @blur="focused = false" dir="auto">
        @if($searchQuery)
          <a href="{{ route('images.gallery') }}" class="text-rose-400 hover:text-rose-300 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </a>
        @endif
        <button type="submit" class="hidden"></button> 
      </div>
      @if($searchQuery)
      <div class="mt-2 pl-4 text-[10px] uppercase tracking-widest text-gray-500 font-bold">
        Results for: <span class="text-cyan-400">"{{ $searchQuery }}"</span> ({{ $images->total() }})
      </div>
      @endif
    </form>

    <div class="flex items-center gap-2 overflow-x-auto w-full lg:w-auto pb-2 lg:pb-0 hide-scrollbar scroll-smooth">
      <a href="{{ route('images.gallery') }}" class="px-5 py-2.5 rounded-full text-[10px] font-bold uppercase tracking-widest whitespace-nowrap transition-all {{ !$selectedTag && !$searchQuery ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'bg-white/5 text-gray-400 hover:bg-white/10' }}">
        All
      </a>
      @foreach($categories as $tag => $label)
        <a href="{{ route('images.gallery', ['tag' => $tag]) }}" class="px-5 py-2.5 rounded-full text-[10px] font-bold uppercase tracking-widest whitespace-nowrap transition-all {{ $selectedTag === $tag ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'bg-white/5 text-gray-400 hover:bg-white/10' }}">
          {{ $label }}
        </a>
      @endforeach
    </div>
  </div>

  {{-- ── Grid View (Masonry) ── --}}
  <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden hide-scrollbar" id="gallery-grid" x-show="view === 'grid'" x-cloak>
    <div class="masonry-grid" id="grid-container">
      @fragment('grid-items')
      @forelse($images as $image)
        @php
          $isOwner      = auth()->id() === $image->user_id;
          $protAttrs    = !$isOwner ? 'oncontextmenu="return false;" ondragstart="return false;"' : '';
          $imgUrl       = app(\App\Services\Core\AssetDeliveryService::class)->getUrl($image, 'card');
          $isSensitive  = $image->is_sensitive || in_array($image->status, ['rejected', 'pending_review']);
        @endphp

        <div class="glass-card masonry-item fade-up cursor-pointer group relative overflow-hidden" x-data="{ revealed: false }" {!! $protAttrs !!}
             @click="openModal({
                  id: '{{ $image->id }}',
                  title: '{{ addslashes($image->title ?: 'Untitled') }}',
                  description: '{{ addslashes($image->description ?: 'No description') }}',
                  url: '{{ $image->url }}',
                  user: { id: '{{ $image->user->id }}', name: '{{ addslashes($image->user->name) }}', avatar: '{{ $image->user->avatar }}' },
                  status: '{{ $image->status }}',
                  is_sensitive: {{ $image->is_sensitive ? 'true' : 'false' }},
                  labels: @json($image->labelData ? $image->labelData->labels : ($image->labels ?? [])),
                  likes_count: {{ $image->likes_count ?? 0 }},
                  allow_download: {{ $image->allow_download ? 'true' : 'false' }},
                  download_url: '{{ $image->allow_download ? route('images.download', $image) : '#' }}'
             })">
          {{-- Image Content --}}
          <div class="relative w-full overflow-hidden">
            <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
                 data-src="{{ $imgUrl }}" alt="{{ htmlspecialchars($image->title ?: ($image->aiMetadata?->caption ?: 'Photograph')) }}"
                 class="{{ $loop->index < 6 ? '' : 'lazy' }} w-full h-auto object-cover transition-transform duration-700 group-hover:scale-105"
                 onload="this.classList.add('is-loaded')" @if($loop->index < 6) src="{{ $imgUrl }}" @endif loading="{{ $loop->index < 6 ? 'eager' : 'lazy' }}">
            
            {{-- Dark cinematic gradient overlay --}}
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

            {{-- Hover UI Controls --}}
            <div class="absolute inset-0 flex flex-col justify-between p-5 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10 pointer-events-none">
                <div class="self-end px-3 py-1 rounded-full bg-black/50 backdrop-blur text-[9px] font-bold tracking-widest text-white uppercase border border-white/20">
                  {{ strtoupper($image->file_type) }}
                </div>
                
                <div class="pointer-events-auto mt-auto flex flex-col gap-2">
                  <h3 class="text-white text-lg font-bold truncate leading-tight">{{ $image->title ?: 'Untitled' }}</h3>
                  @if($image->aiMetadata?->caption)
                    <p class="text-gray-300 text-[11px] line-clamp-2 leading-relaxed">{{ $image->aiMetadata->caption }}</p>
                  @endif
                  <div class="flex justify-between items-center mt-2 pt-3 border-t border-white/10">
                    <div class="flex gap-2">
                      @auth
                        <button @click.stop="toggleLike('{{ $image->id }}')" class="p-2 rounded-full bg-white/10 hover:bg-white/20 transition backdrop-blur border border-white/10 text-white flex items-center gap-1.5" :class="isLiked('{{ $image->id }}') ? 'text-rose-400 border-rose-500/30' : ''">
                          <svg class="w-4 h-4" :class="isLiked('{{ $image->id }}') ? 'fill-current' : 'fill-none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                          <span class="text-[10px] font-bold" x-text="likeCount('{{ $image->id }}', {{ $image->likes_count ?? 0 }})"></span>
                        </button>
                        <button @click.stop="toggleBookmark('{{ $image->id }}')" class="p-2 rounded-full bg-white/10 hover:bg-white/20 transition backdrop-blur border border-white/10 text-white" :class="isBookmarked('{{ $image->id }}') ? 'text-yellow-400 border-yellow-500/30' : ''">
                          <svg class="w-4 h-4" :class="isBookmarked('{{ $image->id }}') ? 'fill-current' : 'fill-none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                        </button>
                      @endauth
                    </div>
                    @if($isOwner || $image->allow_download)
                      <a href="{{ $image->getOriginalUrl() }}" @click.stop="" class="p-2 rounded-full bg-white/10 hover:bg-cyan-500/50 transition backdrop-blur border border-white/10 text-white flex items-center justify-center" download title="Download">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                      </a>
                    @endif
                  </div>
                </div>
            </div>

            {{-- Status Badges --}}
            @if($isOwner || auth()->user()?->hasRole('super_admin'))
              <div class="absolute top-4 left-4 z-10 flex flex-col gap-2 pointer-events-none">
                @if($image->status === 'approved')
                  <span class="px-2 py-1 rounded bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 text-[9px] uppercase tracking-widest font-bold backdrop-blur">Approved</span>
                @elseif($image->status === 'pending_review')
                  <span class="px-2 py-1 rounded bg-amber-500/20 border border-amber-500/30 text-amber-300 text-[9px] uppercase tracking-widest font-bold backdrop-blur">Pending</span>
                @elseif($image->status === 'rejected')
                  <span class="px-2 py-1 rounded bg-rose-500/20 border border-rose-500/30 text-rose-300 text-[9px] uppercase tracking-widest font-bold backdrop-blur">Rejected</span>
                @endif
              </div>
            @endif

            {{-- Sensitive Veil --}}
            @if($isSensitive)
              <div x-show="!revealed" class="absolute inset-0 z-20 flex flex-col items-center justify-center p-6 bg-black/80 backdrop-blur-2xl">
                  <div class="w-12 h-12 rounded-full flex items-center justify-center mb-4 bg-white/10 border border-white/20">
                    <svg class="w-5 h-5 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                  </div>
                  <p class="text-[10px] text-center text-gray-400 mb-4 max-w-[80%] font-medium">Sensitive content detected.</p>
                  <button @click.stop="revealed = true" class="px-4 py-2 rounded-full bg-white/10 hover:bg-white/20 transition border border-white/20 text-white text-[10px] font-bold tracking-widest uppercase">Show anyway</button>
                  @if($image->status === 'rejected' && $isOwner)
                    @if($image->appeals()->where('status', 'pending')->exists())
                      <div class="mt-3 text-[9px] text-amber-500 uppercase tracking-widest font-bold">Appeal Pending</div>
                    @else
                      <button @click.stop="$dispatch('open-appeal-modal', { id: '{{ $image->id }}' })" class="mt-3 text-[9px] text-gray-400 hover:text-white uppercase tracking-widest font-bold">Request Appeal</button>
                    @endif
                  @endif
              </div>
            @endif
          </div>
          
          {{-- Appeal Modal Fragment for this image --}}
          @if($image->status === 'rejected' && $isOwner)
            <div x-data="{ open: false }" @open-appeal-modal.window="if ($event.detail.id === '{{ $image->id }}') open = true" x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" x-cloak>
              <div @click.away="open = false" class="appeal-modal w-full max-w-md p-6" dir="rtl" @click.stop>
                <div class="flex justify-between items-center mb-6">
                  <h3 class="font-display text-white text-xl">طلب مراجعة الصورة</h3>
                  <button @click="open = false" class="text-white hover:text-gray-300"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
                <form action="{{ route('images.appeal', $image) }}" method="POST" class="space-y-4">
                  @csrf
                  <div><input type="text" name="contact_name" value="{{ auth()->user()->name }}" required class="modal-input" placeholder="الاسم الكامل"></div>
                  <div><input type="email" name="contact_email" value="{{ auth()->user()->email }}" required class="modal-input" placeholder="البريد الإلكتروني للرد"></div>
                  <div><textarea name="reason" required minlength="10" rows="3" class="modal-input resize-none" placeholder="لماذا تعتقد أن الحظر خاطئ؟"></textarea></div>
                  <button type="submit" class="w-full py-3 rounded-xl bg-violet-600 hover:bg-violet-500 text-white font-bold text-sm transition">إرسال طلب المراجعة</button>
                </form>
              </div>
            </div>
          @endif
        </div>
      @empty
        @if($images->currentPage() == 1)
        <div class="col-span-full py-32 flex flex-col items-center justify-center text-center">
            <div class="w-24 h-24 mb-6 bg-white/5 rounded-full flex items-center justify-center border border-white/10">
              <svg class="w-10 h-10 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="text-3xl text-white font-light tracking-tight mb-2">No masterpieces yet</h3>
            <p class="text-gray-400 mb-8 max-w-sm text-sm">Upload your first high-quality asset to the vault.</p>
        </div>
        @endif
      @endforelse
      @endfragment
    </div>
    
    {{-- Infinity Scroll Sentinel --}}
    <div x-show="hasMore" class="my-10 flex justify-center sentinel text-cyan-400">
      <svg class="w-8 h-8 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
    </div>
  </div>

  {{-- ── List View ── --}}
  <div id="gallery-list" role="tabpanel" x-show="view === 'list'" x-cloak class="flex-1 min-h-0 overflow-y-auto pr-1 hide-scrollbar">
    <div id="list-container" class="space-y-4 max-w-5xl mx-auto">
    @fragment('list-items')
    @forelse($images as $image)
      @php $isOwner = auth()->id() === $image->user_id; $imgUrl = app(\App\Services\Core\AssetDeliveryService::class)->getUrl($image, 'list'); @endphp
      <div class="list-row fade-up cursor-pointer group"
            @click="openModal({
                id: '{{ $image->id }}', title: '{{ addslashes($image->title ?: 'Untitled') }}', description: '{{ addslashes($image->description ?: 'No description') }}',
                url: '{{ $image->url }}', user: { id: '{{ $image->user->id }}', name: '{{ addslashes($image->user->name) }}', avatar: '{{ $image->user->avatar }}' },
                status: '{{ $image->status }}', is_sensitive: {{ $image->is_sensitive ? 'true' : 'false' }},
                labels: @json($image->labelData ? $image->labelData->labels : ($image->labels ?? [])), likes_count: {{ $image->likes_count ?? 0 }},
                allow_download: {{ $image->allow_download ? 'true' : 'false' }}, download_url: '{{ $image->allow_download ? route('images.download', $image) : '#' }}'
            })">
        <div class="rounded-xl overflow-hidden flex-shrink-0 group-hover:ring-2 ring-cyan-500/50 transition-all w-[72px] h-[56px] relative">
          <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="{{ $imgUrl }}" class="w-full h-full object-cover lazy">
        </div>
        <div class="min-w-0">
          <p class="font-bold text-sm text-white truncate">{{ $image->title ?: 'Untitled' }}</p>
          <div class="flex items-center gap-3 mt-1">
            <time class="text-[10px] text-gray-500 font-mono">{{ $image->created_at->format('M d, Y') }}</time>
            <span class="text-[9px] uppercase tracking-widest text-cyan-500 font-bold opacity-80">{{ $image->privacy }}</span>
            @if($image->aiMetadata?->caption)
              <span class="hidden sm:inline text-xs text-gray-400 truncate w-48">{{ $image->aiMetadata->caption }}</span>
            @endif
          </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            @auth
              <button @click.stop="toggleBookmark('{{ $image->id }}')" class="p-2 rounded-full hover:bg-white/10 text-gray-400" :class="isBookmarked('{{ $image->id }}') ? 'text-yellow-400' : ''">
                <svg class="w-4 h-4" :class="isBookmarked('{{ $image->id }}') ? 'fill-current' : 'fill-none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
              </button>
              <button @click.stop="toggleLike('{{ $image->id }}')" class="p-2 rounded-full hover:bg-white/10 text-gray-400" :class="isLiked('{{ $image->id }}') ? 'text-rose-400' : ''">
                <svg class="w-4 h-4" :class="isLiked('{{ $image->id }}') ? 'fill-current' : 'fill-none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
              </button>
            @endauth
        </div>
      </div>
    @empty
      @if($images->currentPage() == 1)
      <div class="py-16 text-center text-gray-500 text-sm">No images found.</div>
      @endif
    @endforelse
    @endfragment
    </div>
    <div x-show="hasMore" class="my-8 flex justify-center sentinel text-cyan-400">
      <svg class="w-8 h-8 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
    </div>
  </div>

  @include('images._detail_modal')
</div>
@endsection

@include('images._gallery_scripts')