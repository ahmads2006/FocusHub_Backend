{{-- ============================================ --}}
{{-- ملف: resources/views/vault.blade.php          --}}
{{-- الإصدار: 3.0 – تصميم احترافي متميز            --}}
{{-- ============================================ --}}

@extends('layouts.premium')

@section('title', 'Photography Vault | Professional Showcase')

@section('meta')
    <meta name="description" content="استعرض وأدر أصولك البصرية بكل احترافية.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=DM+Mono:wght@300;400;500&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
@endsection

@push('styles')
<style>
  :root {
    --ink:        #0a0a0f;
    --ink-2:      #111118;
    --ink-3:      #1a1a26;
    --border:     rgba(255,255,255,0.06);
    --border-hi:  rgba(255,255,255,0.14);
    --text:       #e8e6f0;
    --text-dim:   #6b6882;
    --text-mid:   #9896b0;
    --gold:       #c8a96e;
    --gold-dim:   rgba(200,169,110,0.15);
    --teal:       #4ecdc4;
    --rose:       #e8637a;
    --amber:      #f5a623;
    --violet:     #8b7cf8;
    --r-card:     20px;
    --shadow:     0 8px 32px rgba(0,0,0,0.45), 0 2px 8px rgba(0,0,0,0.3);
  }

  body { background-color: var(--ink); }

  .vault-scroll::-webkit-scrollbar        { width: 4px; }
  .vault-scroll::-webkit-scrollbar-track  { background: transparent; }
  .vault-scroll::-webkit-scrollbar-thumb  { background: var(--border-hi); border-radius: 2px; }

  .font-display  { font-family: 'Cormorant Garamond', serif; }
  .font-mono-alt { font-family: 'DM Mono', monospace; }
  .font-body     { font-family: 'Outfit', sans-serif; }

  .surface    { background: var(--ink-2); border: 1px solid var(--border); }
  .surface-hi { background: var(--ink-3); border: 1px solid var(--border-hi); }

  /* Card */
  .vault-card {
    background: var(--ink-2);
    border: 1px solid var(--border);
    border-radius: var(--r-card);
    overflow: hidden;
    transition: border-color .35s ease, box-shadow .35s ease, transform .35s cubic-bezier(.23,1,.32,1);
    box-shadow: var(--shadow);
    will-change: transform;
  }
  .vault-card:hover {
    border-color: var(--border-hi);
    box-shadow: 0 20px 60px rgba(0,0,0,0.6), 0 4px 16px rgba(0,0,0,0.4);
    transform: translateY(-4px) scale(1.005);
  }
  .vault-card:hover .card-img { transform: scale(1.07); }

  .card-img {
    transition: transform .65s cubic-bezier(.23,1,.32,1);
    will-change: transform;
  }

  /* Hover overlay */
  .card-overlay {
    background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 55%, transparent 100%);
    opacity: 0;
    transition: opacity .3s ease;
  }
  .vault-card:hover .card-overlay { opacity: 1; }

  /* Sensitive veil */
  .sensitive-veil {
    background: rgba(8,8,14,0.72);
    backdrop-filter: blur(28px) saturate(0.4);
  }

  /* View switcher */
  .view-tab {
    padding: 6px 18px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    transition: background .2s, color .2s;
    font-family: 'DM Mono', monospace;
  }
  .view-tab.active   { background: var(--border-hi); color: var(--text); }
  .view-tab.inactive { color: var(--text-dim); }
  .view-tab.inactive:hover { color: var(--text-mid); }

  /* Status pill */
  .status-pill {
    display: inline-flex; align-items: center; gap: 5px;
    font-family: 'DM Mono', monospace;
    font-size: 9px; font-weight: 500;
    letter-spacing: .1em; text-transform: uppercase;
    padding: 4px 10px; border-radius: 100px;
    backdrop-filter: blur(12px);
  }
  .status-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }

  /* Content badge */
  .content-badge {
    font-family: 'DM Mono', monospace;
    font-size: 8px; font-weight: 700;
    letter-spacing: .14em; text-transform: uppercase;
    padding: 3px 8px; border-radius: 6px;
  }

  /* Action button */
  .action-btn {
    width: 32px; height: 32px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.06);
    transition: background .2s, border-color .2s, transform .15s;
    color: var(--text-mid);
  }
  .action-btn:hover { background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.2); transform: scale(1.1); }
  .action-btn.dl-owner   { border-color: rgba(78,205,196,0.3);   background: rgba(78,205,196,0.08);   color: var(--teal);   }
  .action-btn.dl-owner:hover   { background: rgba(78,205,196,0.18);   border-color: rgba(78,205,196,0.5);   }
  .action-btn.dl-visitor { border-color: rgba(139,124,248,0.3);  background: rgba(139,124,248,0.08);  color: var(--violet); }
  .action-btn.dl-visitor:hover { background: rgba(139,124,248,0.18);  border-color: rgba(139,124,248,0.5);  }

  /* Shimmer */
  @keyframes shimmer { from { transform:translateX(-100%); } to { transform:translateX(200%); } }
  .shimmer { position: relative; overflow: hidden; }
  .shimmer::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.04), transparent);
    animation: shimmer 2.8s infinite;
  }

  /* Fade-up stagger */
  @keyframes fadeUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
  .fade-up { animation: fadeUp .5s cubic-bezier(.23,1,.32,1) both; }
  .vault-card:nth-child(1)  { animation-delay:.00s; }
  .vault-card:nth-child(2)  { animation-delay:.04s; }
  .vault-card:nth-child(3)  { animation-delay:.08s; }
  .vault-card:nth-child(4)  { animation-delay:.12s; }
  .vault-card:nth-child(5)  { animation-delay:.16s; }
  .vault-card:nth-child(6)  { animation-delay:.20s; }
  .vault-card:nth-child(7)  { animation-delay:.24s; }
  .vault-card:nth-child(8)  { animation-delay:.28s; }

  /* Gold divider */
  .gold-line {
    height: 1px;
    background: linear-gradient(to right, transparent, var(--gold-dim), var(--gold), var(--gold-dim), transparent);
    opacity: .5;
  }

  /* Empty state float */
  @keyframes float { 0%,100% { transform:translateY(0); } 50% { transform:translateY(-8px); } }
  .float-icon { animation: float 3s ease-in-out infinite; }

  /* Appeal modal */
  .appeal-modal { background: var(--ink-2); border: 1px solid var(--border-hi); border-radius: 24px; }
  .modal-input {
    width: 100%;
    background: rgba(255,255,255,0.04); border: 1px solid var(--border-hi);
    border-radius: 12px; padding: 12px 16px;
    color: var(--text); font-family: 'Outfit',sans-serif; font-size: 13px;
    outline: none; transition: border-color .2s;
  }
  .modal-input:focus { border-color: var(--violet); }

  /* List row */
  .list-row {
    display: grid; grid-template-columns: 72px 1fr auto;
    align-items: center; gap: 16px;
    background: var(--ink-2); border: 1px solid var(--border);
    border-radius: 14px; padding: 12px 16px;
    transition: border-color .25s, background .25s;
  }
  .list-row:hover { border-color: var(--border-hi); background: var(--ink-3); }

  /* Like Button */
  .like-btn {
    font-family: 'DM Mono', monospace;
    font-size: 9px; font-weight: 700;
    letter-spacing: .1em;
    color: var(--text-dim);
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    cursor: pointer;
  }
  .like-btn:hover {
    color: #e8637a;
    border-color: rgba(232,99,122,0.3);
    background: rgba(232,99,122,0.06);
  }
  .like-btn.liked {
    color: #e8637a;
    border-color: rgba(232,99,122,0.4);
    background: rgba(232,99,122,0.1);
  }

  /* Filter Pills */
  .filter-pill {
    padding: 8px 20px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    border: 1px solid var(--border);
    background: var(--ink-2);
    color: var(--text-mid);
    transition: all .3s cubic-bezier(.23,1,.32,1);
    font-family: 'DM Mono', monospace;
    text-transform: uppercase;
    letter-spacing: .05em;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  .filter-pill:hover {
    border-color: var(--border-hi);
    color: var(--text);
    transform: translateY(-2px);
    background: var(--ink-3);
  }
  .filter-pill.active {
    background: var(--gold-dim);
    border-color: var(--gold);
    color: var(--gold);
    box-shadow: 0 0 20px rgba(200,169,110,0.15);
  }
  .no-scrollbar::-webkit-scrollbar { display: none; }
  .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
@endpush

@section('content')
<div class="font-body space-y-8 h-full flex flex-col"
     x-data="galleryPage()"
     @resize.window.debounce="() => {}">

  {{-- ── Header ── --}}
  <div class="flex justify-between items-end">
    <div>
      <p class="font-mono-alt text-[10px] tracking-[.25em] uppercase mb-2" style="color:var(--gold);">Visual Archive</p>
      <h2 class="font-display text-5xl font-light leading-none" style="color:var(--text);">
        Your <em class="not-italic font-semibold" style="background:linear-gradient(135deg,#c8a96e 0%,#f0d9a8 50%,#c8a96e 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Vault</em>
      </h2>
      <p class="mt-2 text-sm font-light" style="color:var(--text-dim);">Manage and showcase your professional assets.</p>
    </div>

    <div class="flex items-center gap-1 p-1 rounded-2xl surface" role="tablist" aria-label="Gallery view mode">
      <button @click="view = 'grid'" :class="view === 'grid' ? 'active' : 'inactive'" class="view-tab" role="tab" :aria-selected="view === 'grid'" aria-controls="gallery-grid" id="tab-grid">
        <span class="flex items-center gap-2">
          <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor"><rect x="0" y="0" width="5" height="5" rx="1.5"/><rect x="7" y="0" width="5" height="5" rx="1.5"/><rect x="0" y="7" width="5" height="5" rx="1.5"/><rect x="7" y="7" width="5" height="5" rx="1.5"/></svg>
          Grid
        </span>
      </button>
      <button @click="view = 'list'" :class="view === 'list' ? 'active' : 'inactive'" class="view-tab" role="tab" :aria-selected="view === 'list'" aria-controls="gallery-list" id="tab-list">
        <span class="flex items-center gap-2">
          <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor"><rect x="0" y="0" width="12" height="2" rx="1"/><rect x="0" y="5" width="12" height="2" rx="1"/><rect x="0" y="10" width="12" height="2" rx="1"/></svg>
          List
        </span>
      </button>
    </div>
  </div>

  <div class="gold-line"></div>

  {{-- ── Filter Pills ── --}}
  <div class="flex items-center gap-3 overflow-x-auto py-2 no-scrollbar">
    <a href="{{ route('images.gallery') }}" 
       class="filter-pill {{ !$selectedTag ? 'active' : '' }}">
      <svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor"><path d="M1 3h8M1 5h8M1 7h8"/></svg>
      All
    </a>
    @foreach($categories as $tag => $label)
      <a href="{{ route('images.gallery', ['tag' => $tag]) }}" 
         class="filter-pill {{ $selectedTag === $tag ? 'active' : '' }}">
        {{ $label }}
      </a>
    @endforeach
  </div>

  {{-- ── Grid View ── --}}
  <div class="vault-scroll flex-1 min-h-0 overflow-y-auto pr-1"
       id="gallery-grid" role="tabpanel" aria-labelledby="tab-grid"
       x-show="view === 'grid'" x-cloak>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5" id="grid-container">
      @fragment('grid-items')
      @forelse($images as $image)
        @php
          $isOwner      = auth()->id() === $image->user_id;
          $protAttrs    = !$isOwner ? 'oncontextmenu="return false;" ondragstart="return false;"' : '';
          $imgUrl       = $image->url;
          $isSensitive  = $image->is_sensitive || in_array($image->status, ['rejected', 'pending_review']);
          $reasons      = explode(',', $image->sensitivity_reason ?? '');

          if ($image->status === 'rejected') {
            $bc = '#e8637a'; $bb = 'rgba(232,99,122,0.12)'; $bt = 'Rejected';
          } elseif ($image->status === 'pending_review') {
            $bc = '#f5a623'; $bb = 'rgba(245,166,35,0.12)';  $bt = 'Pending';
          } elseif (in_array('nudity', $reasons)) {
            $bc = '#e8637a'; $bb = 'rgba(232,99,122,0.12)';  $bt = 'NSFW';
          } elseif (in_array('weapon', $reasons) || in_array('violence', $reasons)) {
            $bc = '#f5a623'; $bb = 'rgba(245,166,35,0.12)';  $bt = 'Violence';
          } elseif (in_array('alcohol', $reasons) || in_array('drugs', $reasons)) {
            $bc = '#4ecdc4'; $bb = 'rgba(78,205,196,0.12)';  $bt = 'Substances';
          } elseif (in_array('offensive_text', $reasons)) {
            $bc = '#9896b0'; $bb = 'rgba(152,150,176,0.12)'; $bt = 'Offensive';
          } elseif (in_array('pii', $reasons)) {
            $bc = '#8b7cf8'; $bb = 'rgba(139,124,248,0.12)'; $bt = 'PII';
          } else {
            $bc = '#9896b0'; $bb = 'rgba(152,150,176,0.12)'; $bt = 'Sensitive';
          }
        @endphp

        <div class="vault-card fade-up" x-data="{ revealed: false }" key="image-{{ $image->id }}">

          {{-- Image area --}}
          <div class="relative overflow-hidden shimmer cursor-pointer group" 
               style="height:220px;background:var(--ink-3);" 
               {!! $protAttrs !!}
               @click="openModal({
                            id: '{{ $image->id }}',
                            title: '{{ $image->title ?: 'Untitled' }}',
                            description: '{{ $image->description ?: 'No description' }}',
                            url: '{{ $image->url }}',
                            user: {
                                id: '{{ $image->user->id }}',
                                name: '{{ $image->user->name }}',
                                avatar: '{{ $image->user->avatar }}'
                            },
                            status: '{{ $image->status }}',
                            is_sensitive: {{ $image->is_sensitive ? 'true' : 'false' }},
                            labels: @json($image->labels ? $image->labels->labels : []),
                            likes_count: {{ $image->likes_count ?? 0 }},
                            allow_download: {{ $image->allow_download ? 'true' : 'false' }},
                            download_url: '{{ $image->allow_download ? route('images.download', $image) : '#' }}'
                          })">

            <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
                 data-src="{{ $imgUrl }}"
                 alt="{{ $image->title ?: 'Photograph' }}"
                 class="card-img w-full h-full object-cover lazy select-none transition-transform duration-700 group-hover:scale-110 {{ $isSensitive ? 'blur-xl' : '' }}"
                 :class="revealed ? '!blur-0' : ''"
                 width="400" height="300" loading="lazy">

            {{-- Hover overlay --}}
            <div class="card-overlay absolute inset-0 z-10 flex flex-col justify-end p-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              <div class="flex justify-between items-center">
                <span class="content-badge" style="background:rgba(0,0,0,0.5);color:var(--text-mid);border:1px solid var(--border-hi);">
                  {{ strtoupper($image->file_type) }}
                </span>
                <div class="flex gap-2">
                  <button class="action-btn" aria-label="Image details" 
                          @click="openModal({
                            id: '{{ $image->id }}',
                            title: '{{ $image->title ?: 'Untitled' }}',
                            description: '{{ $image->description ?: 'No description' }}',
                            url: '{{ $image->url }}',
                            user: {
                                id: '{{ $image->user->id }}',
                                name: '{{ $image->user->name }}',
                                avatar: '{{ $image->user->avatar }}'
                            },
                            status: '{{ $image->status }}',
                            is_sensitive: {{ $image->is_sensitive ? 'true' : 'false' }},
                            labels: @json($image->labels ? $image->labels->labels : []),
                            likes_count: {{ $image->likes_count ?? 0 }},
                            allow_download: {{ $image->allow_download ? 'true' : 'false' }},
                            download_url: '{{ $image->allow_download ? route('images.download', $image) : '#' }}'
                          })">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                  </button>
                  @if($isOwner)
                    <a href="{{ $image->getOriginalUrl() }}" class="action-btn dl-owner" aria-label="Download original" download>
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                      </svg>
                    </a>
                  @elseif($image->allow_download)
                    <a href="{{ $image->getOriginalUrl() }}" class="action-btn dl-visitor" aria-label="Download (watermarked)" title="Download — Watermarked" download>
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                      </svg>
                    </a>
                  @endif
                </div>
              </div>
              @if(!$isOwner && !$image->allow_download)
                <p class="font-mono-alt text-[8px] uppercase tracking-widest mt-2 text-center py-1 rounded-lg"
                   style="color:var(--rose);background:rgba(232,99,122,0.08);border:1px solid rgba(232,99,122,0.2);">
                  Download disabled by photographer
                </p>
              @endif
            </div>

            {{-- Sensitive veil --}}
            @if($isSensitive)
              <div x-show="!revealed" class="sensitive-veil absolute inset-0 z-20 flex flex-col items-center justify-center p-6 transition-all duration-500">
                @if($image->status === 'pending_review')
                  <div class="w-12 h-12 rounded-full flex items-center justify-center mb-4"
                       style="background:rgba(245,166,35,0.12);border:1px solid rgba(245,166,35,0.3);">
                    <svg class="w-5 h-5" fill="none" stroke="#f5a623" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </div>
                  <p class="font-body text-xs text-center leading-relaxed" style="color:var(--text-mid);">
                    Under review — may contain<br>sensitive content or PII.
                  </p>
                @else
                  <div class="w-12 h-12 rounded-full flex items-center justify-center mb-4"
                       style="background:rgba(232,99,122,0.1);border:1px solid rgba(232,99,122,0.25);">
                    <svg class="w-5 h-5" fill="none" stroke="#e8637a" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                  </div>
                  <p class="font-body text-xs text-center leading-relaxed" style="color:var(--text-mid);">
                    {{ $image->status === 'rejected' ? 'Content blocked — validation failed.' : 'Sensitive content detected.' }}
                  </p>
                @endif

                <div class="mt-4 flex flex-col gap-2 w-full">
                  <button @click="revealed = true"
                          class="w-full py-2 rounded-full text-[10px] font-bold font-mono-alt uppercase tracking-widest transition-all"
                          style="background:rgba(255,255,255,0.08);color:var(--text);border:1px solid var(--border-hi);"
                          onmouseover="this.style.background='rgba(255,255,255,0.14)'"
                          onmouseout="this.style.background='rgba(255,255,255,0.08)'">
                    Show anyway
                  </button>

                  @if($image->status === 'rejected' && $isOwner)
                    @php $pendingAppeal = $image->appeals()->where('status', 'pending')->first(); @endphp
                    @if($pendingAppeal)
                      <div class="w-full py-2 rounded-full text-[10px] font-bold font-mono-alt uppercase tracking-widest text-center cursor-not-allowed"
                           style="background:rgba(245,166,35,0.06);color:rgba(245,166,35,0.5);border:1px solid rgba(245,166,35,0.2);">
                        Appeal pending
                      </div>
                    @else
                      <button @click="$dispatch('open-appeal-modal', { id: '{{ $image->id }}' })"
                              class="w-full py-2 rounded-full text-[10px] font-bold font-mono-alt uppercase tracking-widest transition-all"
                              style="background:transparent;color:var(--text-mid);border:1px solid var(--border-hi);"
                              onmouseover="this.style.background='rgba(255,255,255,0.06)'"
                              onmouseout="this.style.background='transparent'">
                        Request appeal
                      </button>
                    @endif
                  @endif
                </div>
              </div>

              {{-- Appeal Modal --}}
              @if($image->status === 'rejected' && $isOwner)
                <div x-data="{ open: false }"
                     @open-appeal-modal.window="if ($event.detail.id === '{{ $image->id }}') open = true"
                     x-show="open"
                     class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     style="background:rgba(5,5,10,0.85);backdrop-filter:blur(12px);"
                     x-cloak>
                  <div @click.away="open = false" class="appeal-modal w-full max-w-md overflow-hidden relative" dir="rtl">
                    <div class="p-5 flex justify-between items-center" style="border-bottom:1px solid var(--border);">
                      <div>
                        <p class="font-mono-alt text-[9px] tracking-widest uppercase mb-1" style="color:var(--gold);">Image Review</p>
                        <h3 class="font-display text-xl font-semibold" style="color:var(--text);">طلب مراجعة الصورة</h3>
                      </div>
                      <button @click="open = false" class="action-btn" aria-label="Close modal">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                      </button>
                    </div>
                    <form action="{{ route('images.appeal', $image) }}" method="POST" class="p-6 space-y-4">
                      @csrf
                      <div>
                        <label class="block font-mono-alt text-[10px] tracking-widest uppercase mb-2" style="color:var(--text-dim);">الاسم الكامل</label>
                        <input type="text" name="contact_name" value="{{ auth()->user()->name }}" required class="modal-input">
                      </div>
                      <div>
                        <label class="block font-mono-alt text-[10px] tracking-widest uppercase mb-2" style="color:var(--text-dim);">البريد الإلكتروني للرد</label>
                        <input type="email" name="contact_email" value="{{ auth()->user()->email }}" required class="modal-input">
                      </div>
                      <div>
                        <label class="block font-mono-alt text-[10px] tracking-widest uppercase mb-2" style="color:var(--text-dim);">مبررات الطلب</label>
                        <textarea name="reason" required minlength="10" rows="4" class="modal-input resize-none" placeholder="لماذا تعتقد أن الحظر خاطئ؟"></textarea>
                      </div>
                      <button type="submit"
                              class="w-full py-3 rounded-2xl font-body font-semibold text-sm transition-all mt-2"
                              style="background:linear-gradient(135deg,#8b7cf8,#6d5ce7);color:white;box-shadow:0 8px 24px rgba(139,124,248,0.3);"
                              onmouseover="this.style.opacity='.9'" onmouseout="this.style.opacity='1'">
                        إرسال طلب المراجعة
                      </button>
                    </form>
                  </div>
                </div>
              @endif

              {{-- Content badge --}}
              <div class="absolute top-3 right-3 z-10 content-badge" style="color:{{ $bc }};background:{{ $bb }};border:1px solid {{ $bc }}33;">
                {{ $bt }}
              </div>
            @endif

            {{-- Status badge (owner / admin) --}}
            @if($isOwner || auth()->user()?->hasRole('super_admin'))
              <div class="absolute top-3 left-3 z-10">
                @if($image->status === 'approved')
                  <span class="status-pill" style="background:rgba(78,205,196,0.1);color:#4ecdc4;border:1px solid rgba(78,205,196,0.25);">
                    <span class="status-dot animate-pulse"></span> Approved
                  </span>
                @elseif($image->status === 'pending_review')
                  <span class="status-pill" title="PII or borderline content" style="background:rgba(245,166,35,0.1);color:#f5a623;border:1px solid rgba(245,166,35,0.25);">
                    <span class="status-dot"></span> Pending
                  </span>
                @elseif($image->status === 'rejected')
                  <span class="status-pill" style="background:rgba(232,99,122,0.1);color:#e8637a;border:1px solid rgba(232,99,122,0.25);">
                    <span class="status-dot"></span> Rejected
                  </span>
                @endif
              </div>
            @endif
          </div>

          {{-- Card footer --}}
          <div class="p-4 flex flex-col gap-2" style="border-top:1px solid var(--border);">
            <h4 class="font-body font-medium text-sm truncate" style="color:var(--text);" title="{{ $image->title }}">
              {{ $image->title ?: 'Untitled' }}
            </h4>

            {{-- AI Classification Label --}}
            @php
              $aiLabel = null;
              // Try inline JSON column first, fallback to relationship
              $rawLabels = $image->labels;
              if (empty($rawLabels) && $image->relationLoaded('labels')) {
                $rawLabels = optional($image->labels)->labels;
              }
              if (!empty($rawLabels)) {
                $firstLabel = is_array($rawLabels) ? ($rawLabels[0] ?? null) : null;
                if (is_array($firstLabel) && isset($firstLabel['description'])) {
                  $aiLabel = $firstLabel['description'];
                } elseif (is_string($firstLabel)) {
                  $aiLabel = $firstLabel;
                }
              }
            @endphp
            @if($aiLabel)
              <div class="flex items-center gap-1.5">
                <svg class="w-3 h-3 flex-shrink-0" style="color:var(--violet)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <span class="font-mono-alt text-[9px] tracking-wide uppercase truncate" style="color:var(--violet);">{{ $aiLabel }}</span>
              </div>
            @endif

            <div class="flex justify-between items-center">
              <time datetime="{{ $image->created_at->toIso8601String() }}" class="font-mono-alt text-[10px]" style="color:var(--text-dim);">
                {{ $image->created_at->format('M d, Y') }}
              </time>
              <div class="flex items-center gap-2">
                <span class="font-mono-alt text-[9px] tracking-widest uppercase" style="color:var(--gold);opacity:.7;">
                  {{ $image->privacy }}
                </span>
                {{-- Like Button --}}
                @auth
                <button
                  @click="toggleLike('{{ $image->id }}')"
                  class="like-btn flex items-center gap-1 px-2.5 py-1 rounded-full transition-all duration-200"
                  :class="isLiked('{{ $image->id }}') ? 'liked' : ''"
                  aria-label="Like this image"
                  title="Like"
                >
                  <svg class="w-3.5 h-3.5" :class="isLiked('{{ $image->id }}') ? 'fill-current' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                  </svg>
                  <span class="font-mono-alt text-[9px] tracking-widest" x-text="likeCount('{{ $image->id }}', {{ $image->likes_count ?? 0 }})"></span>
                </button>
                @endauth
              </div>
            </div>
          </div>
        </div>
      @empty
        @if($images->currentPage() == 1)
        <div class="col-span-full surface rounded-3xl flex flex-col items-center justify-center py-20 text-center">
          <div class="float-icon mb-6 w-20 h-20 rounded-full flex items-center justify-center"
               style="background:var(--ink-3);border:1px solid var(--border-hi);">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-dim);" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
          </div>
          <p class="font-mono-alt text-[10px] tracking-widest uppercase mb-2" style="color:var(--gold);">Empty vault</p>
          <p class="font-display text-2xl font-light mb-6" style="color:var(--text);">Begin your archive</p>
          <a href="{{ route('images.index') }}"
             class="inline-flex items-center gap-2 px-7 py-3 rounded-full font-body font-semibold text-sm transition-all"
             style="background:linear-gradient(135deg,#8b7cf8,#6d5ce7);color:white;box-shadow:0 8px 28px rgba(139,124,248,0.25);"
             onmouseover="this.style.opacity='.9'" onmouseout="this.style.opacity='1'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Upload now
          </a>
        </div>
        @endif
      @endforelse
      @endfragment
    </div>

    <div x-show="hasMore" class="my-10 flex justify-center sentinel" role="status" aria-label="Loading more images">
      <svg class="w-8 h-8 animate-spin" style="color:var(--text-mid);" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-10" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-70" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
      </svg>
    </div>
  </div>

  {{-- ── List View ── --}}
  <div id="gallery-list" role="tabpanel" aria-labelledby="tab-list"
       x-show="view === 'list'" x-cloak
       class="vault-scroll flex-1 min-h-0 overflow-y-auto pr-1">
    <div id="list-container" class="space-y-3">
    @fragment('list-items')
    @forelse($images as $image)
      @php $isOwner = auth()->id() === $image->user_id; $imgUrl = $image->url; @endphp
      <div class="list-row fade-up cursor-pointer group"
           @click="openModal({
                    id: '{{ $image->id }}',
                    title: '{{ $image->title ?: 'Untitled' }}',
                    description: '{{ $image->description ?: 'No description' }}',
                    url: '{{ $image->url }}',
                    user: {
                        id: '{{ $image->user->id }}',
                        name: '{{ $image->user->name }}',
                        avatar: '{{ $image->user->avatar }}'
                    },
                    status: '{{ $image->status }}',
                    is_sensitive: {{ $image->is_sensitive ? 'true' : 'false' }},
                    labels: @json($image->labels ? $image->labels->labels : []),
                    likes_count: {{ $image->likes_count ?? 0 }},
                    allow_download: {{ $image->allow_download ? 'true' : 'false' }},
                    download_url: '{{ $image->allow_download ? route('images.download', $image) : '#' }}'
                  })">
        <div class="rounded-xl overflow-hidden flex-shrink-0 group-hover:ring-2 ring-purple-500/50 transition-all" style="width:72px;height:56px;background:var(--ink-3);">
          <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
               data-src="{{ $imgUrl }}" alt="{{ $image->title }}"
               class="lazy w-full h-full object-cover transition-transform duration-500 group-hover:scale-110 {{ $image->is_sensitive ? 'blur-md' : '' }}"
               loading="lazy">
        </div>
        <div class="min-w-0">
          <p class="font-body font-medium text-sm truncate" style="color:var(--text);">{{ $image->title ?: 'Untitled' }}</p>
          <div class="flex items-center gap-3 mt-1">
            <time class="font-mono-alt text-[10px]" style="color:var(--text-dim);" datetime="{{ $image->created_at->toIso8601String() }}">
              {{ $image->created_at->format('M d, Y') }}
            </time>
            <span class="font-mono-alt text-[9px] uppercase tracking-widest" style="color:var(--gold);opacity:.7;">{{ $image->privacy }}</span>
            @if($isOwner || auth()->user()?->hasRole('super_admin'))
              @if($image->status === 'approved')
                <span class="status-pill" style="background:rgba(78,205,196,0.08);color:#4ecdc4;border:1px solid rgba(78,205,196,0.2);"><span class="status-dot"></span> Approved</span>
              @elseif($image->status === 'pending_review')
                <span class="status-pill" style="background:rgba(245,166,35,0.08);color:#f5a623;border:1px solid rgba(245,166,35,0.2);"><span class="status-dot"></span> Pending</span>
              @elseif($image->status === 'rejected')
                <span class="status-pill" style="background:rgba(232,99,122,0.08);color:#e8637a;border:1px solid rgba(232,99,122,0.2);"><span class="status-dot"></span> Rejected</span>
              @endif
            @endif
            {{-- AI Label (inline) --}}
            @php
              $rawLabels = $image->labels;
              $listAiLabel = null;
              if (!empty($rawLabels)) {
                $fl = is_array($rawLabels) ? ($rawLabels[0] ?? null) : null;
                $listAiLabel = is_array($fl) ? ($fl['description'] ?? null) : (is_string($fl) ? $fl : null);
              }
            @endphp
            @if($listAiLabel)
              <span class="inline-flex items-center gap-1 font-mono-alt text-[9px] tracking-wide uppercase px-2 py-0.5 rounded-full" style="color:var(--violet);background:rgba(139,124,248,0.1);border:1px solid rgba(139,124,248,0.2);">
                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                {{ $listAiLabel }}
              </span>
            @endif
          </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <button class="action-btn" aria-label="Image details" 
                  @click="openModal({
                    id: '{{ $image->id }}',
                    title: '{{ $image->title ?: 'Untitled' }}',
                    description: '{{ $image->description ?: 'No description' }}',
                    url: '{{ $image->url }}',
                    user: {
                        id: '{{ $image->user->id }}',
                        name: '{{ $image->user->name }}',
                        avatar: '{{ $image->user->avatar }}'
                    },
                    status: '{{ $image->status }}',
                    is_sensitive: {{ $image->is_sensitive ? 'true' : 'false' }},
                    labels: @json($image->labels ? $image->labels->labels : []),
                    likes_count: {{ $image->likes_count ?? 0 }},
                    allow_download: {{ $image->allow_download ? 'true' : 'false' }},
                    download_url: '{{ $image->allow_download ? route('images.download', $image) : '#' }}'
                  })">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </button>
          <span class="content-badge font-mono-alt" style="color:var(--text-dim);background:var(--ink-3);border:1px solid var(--border);">{{ strtoupper($image->file_type) }}</span>
          @auth
          <button
            @click="toggleLike('{{ $image->id }}')"
            class="like-btn flex items-center gap-1 px-2.5 py-1 rounded-full transition-all duration-200"
            :class="isLiked('{{ $image->id }}') ? 'liked' : ''"
            aria-label="Like"
          >
            <svg class="w-3 h-3" :class="isLiked('{{ $image->id }}') ? 'fill-current' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
            </svg>
            <span class="font-mono-alt text-[9px]" x-text="likeCount('{{ $image->id }}', {{ $image->likes_count ?? 0 }})"></span>
          </button>
          @endauth
          @if($isOwner)
            <a href="{{ $image->getOriginalUrl() }}" class="action-btn dl-owner" aria-label="Download original" download>
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            </a>
          @elseif($image->allow_download)
            <a href="{{ $image->getOriginalUrl() }}" class="action-btn dl-visitor" aria-label="Download" download>
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            </a>
          @endif
        </div>
      </div>
      @empty
        @if($images->currentPage() == 1)
        <div class="surface rounded-3xl flex items-center justify-center py-16">
          <p class="font-body text-sm" style="color:var(--text-dim);">No images found.</p>
        </div>
        @endif
      @endforelse
      @endfragment
    </div>

    <div x-show="hasMore" class="my-8 flex justify-center sentinel" role="status" aria-label="Loading more images">
      <svg class="w-8 h-8 animate-spin" style="color:var(--text-mid);" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-10" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-70" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
      </svg>
    </div>
  </div>

  {{-- ── Image Details Modal ── --}}
  @include('images._detail_modal')

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  if ('IntersectionObserver' in window) {
    const obs = new IntersectionObserver((entries, o) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          const img = e.target;
          img.src = img.dataset.src;
          img.classList.remove('lazy');
          o.unobserve(img);
        }
      });
    }, { rootMargin: '60px 0px', threshold: 0.01 });
    document.querySelectorAll('img.lazy').forEach(img => obs.observe(img));
  } else {
    document.querySelectorAll('img.lazy').forEach(img => { img.src = img.dataset.src; });
  }
});

document.addEventListener('alpine:init', () => {
  // Pre-load liked image IDs from server for instant correct initial state
  const initialLikedIds = new Set(@json($likedImageIds ?? []));

  Alpine.data('galleryPage', () => ({
    view: 'grid',
    loading: false,
    likedIds: new Set(initialLikedIds),
    localCounts: {},
    selectedImage: null,
    showModal: false,
    isFollowing: false,
    
    // Infinite Scroll State
    hasMore: {{ $images->hasMorePages() ? 'true' : 'false' }},
    nextPageUrl: '{!! $images->nextPageUrl() !!}',
    isLoadingMore: false,

    init() {
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadMore();
                    }
                });
            }, { rootMargin: '400px' }); // Load early before reaching absolute bottom

            const observeSentinels = () => {
                document.querySelectorAll('.sentinel').forEach(el => observer.observe(el));
            };

            this.$watch('view', () => { setTimeout(observeSentinels, 100); });
            setTimeout(observeSentinels, 500);
        }
    },

    loadMore() {
        if (this.isLoadingMore || !this.hasMore || !this.nextPageUrl) return;
        this.isLoadingMore = true;

        fetch(this.nextPageUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.grid_html) {
                document.getElementById('grid-container').insertAdjacentHTML('beforeend', data.grid_html);
            }
            if (data.list_html) {
                document.getElementById('list-container').insertAdjacentHTML('beforeend', data.list_html);
            }
            
            this.hasMore = data.has_more;
            this.nextPageUrl = data.next_page_url;
            this.isLoadingMore = false;
            
            // Re-trigger global lazy loading setup (IntersectionObserver for images)
            document.dispatchEvent(new Event('DOMContentLoaded'));
        })
        .catch(err => {
            console.error('Infinity scroll error:', err);
            this.isLoadingMore = false;
        });
    },

    openModal(data) {
        this.selectedImage = data;
        this.showModal = true;
        this.checkFollowStatus(data.user.id);
        document.body.style.overflow = 'hidden';
    },

    closeModal() {
        this.showModal = false;
        document.body.style.overflow = 'auto';
    },

    isNature() {
        return this.selectedImage?.labels.some(l => 
            ['nature', 'mountain', 'landscape', 'forest', 'water', 'sky', 'tree', 'sea', 'ocean'].includes(l.description.toLowerCase())
        );
    },

    async checkFollowStatus(userId) {
        try {
            const res = await fetch(`/connect/${userId}/status`);
            const data = await res.json();
            this.isFollowing = data.connected && data.status === 'accepted';
        } catch (e) {
            this.isFollowing = false;
        }
    },

    async toggleFollow(userId) {
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const res = await fetch(`/connect/${userId}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                this.isFollowing = data.status === 'followed';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ toast: true, position: 'bottom-end', timer: 2500, icon: 'success', title: data.status === 'followed' ? 'تمت المتابعة' : 'تم إلغاء المتابعة', showConfirmButton: false });
                }
            }
        } catch (e) {}
    },

    isLiked(id) {
      return this.likedIds.has(id);
    },

    likeCount(id, initialCount) {
      if (this.localCounts[id] === undefined) {
        this.localCounts[id] = initialCount;
      }
      return this.localCounts[id] > 0 ? this.localCounts[id] : '';
    },

    async toggleLike(id) {
      const wasLiked = this.likedIds.has(id);

      // Optimistic Update
      if (wasLiked) {
        this.likedIds.delete(id);
        this.localCounts[id] = Math.max(0, (this.localCounts[id] || 0) - 1);
      } else {
        this.likedIds.add(id);
        this.localCounts[id] = (this.localCounts[id] || 0) + 1;
      }

      // Force Alpine to re-evaluate
      this.likedIds = new Set(this.likedIds);

      try {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const res = await fetch(`/images/${id}/like`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
        });

        if (res.status === 429) {
          // Revert optimistic update
          if (wasLiked) { this.likedIds.add(id); this.localCounts[id]++; }
          else { this.likedIds.delete(id); this.localCounts[id]--; }
          this.likedIds = new Set(this.likedIds);
          if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'bottom-end', timer: 2500, icon: 'warning', title: 'مهلاً! أنت تعجب بسرعة كبيرة.', showConfirmButton: false });
          }
          return;
        }

        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        // Correct any drift after server response
        if (json.action === 'like') { this.likedIds.add(id); }
        else { this.likedIds.delete(id); }
        this.likedIds = new Set(this.likedIds);

      } catch (e) {
        console.error('Like failed:', e);
        // Revert
        if (wasLiked) { this.likedIds.add(id); this.localCounts[id]++; }
        else { this.likedIds.delete(id); this.localCounts[id]--; }
        this.likedIds = new Set(this.likedIds);
      }
    }
  }));
});
</script>
@endpush