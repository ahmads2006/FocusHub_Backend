@extends('layouts.premium')

@section('title', $profileUser->name . "'s Profile")

@section('content')
<div class="space-y-12 pb-20" dir="rtl" x-data="galleryPage()">
    
    <!-- Profile Header / Cover -->
    <div class="relative">
        <div class="h-48 md:h-64 rounded-[40px] overflow-hidden accent-gradient opacity-20 bg-blend-overlay">
            {{-- Optional: Add background patterns or user-uploaded cover here --}}
            <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-20"></div>
        </div>
        
        <div class="absolute -bottom-12 right-8 md:right-16 flex flex-col md:flex-row items-end md:items-center gap-6">
            <div class="relative group">
                <div class="w-32 h-32 md:w-40 md:h-40 rounded-full border-4 border-[#0a0a0c] p-1 glass glow shadow-2xl overflow-hidden">
                    <img src="{{ $profileUser->avatar }}" class="w-full h-full object-cover rounded-full" alt="{{ $profileUser->name }}">
                </div>
                @if($profileUser->is_verified)
                    <div class="absolute bottom-2 left-2 bg-blue-500 text-white rounded-full p-1.5 shadow-lg border-2 border-[#0a0a0c]" title="Verified Photographer">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path></svg>
                    </div>
                @endif
            </div>
            
            <div class="mb-4 md:mb-0">
                <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight text-white flex items-center gap-3">
                    {{ $profileUser->name }}
                    @if(!$isPublic)
                        <span class="text-xs bg-white/10 px-3 py-1 rounded-full text-gray-400 font-normal">حساب خاص 🔒</span>
                    @endif
                </h1>
                <p class="text-gray-400 mt-2 max-w-lg">{{ $profileUser->bio ?? 'لا يوجد وصف متاح لهذا المصور.' }}</p>
            </div>
        </div>

        <div class="absolute -bottom-10 left-8 md:left-16 flex gap-3">
            @if($isOwner)
                <a href="{{ route('profile.edit') }}" class="glass p-3 px-6 rounded-2xl text-sm font-bold hover:bg-white/10 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    تعديل الملف الشخصي
                </a>
            @else
                @auth
                <button @click="toggleFollow()" 
                        class="p-3 px-8 rounded-2xl text-sm font-bold transition-all flex items-center gap-2 shadow-lg"
                        :class="isFollowing ? 'bg-white/10 text-white border border-white/20' : 'accent-gradient text-white shadow-purple-500/20 hover:scale-105'">
                    <svg x-show="!isFollowing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    <svg x-show="isFollowing" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                    <span x-text="isFollowing ? 'إلغاء المتابعة' : 'متابعة المصور'"></span>
                </button>
                @endauth
            @endif
        </div>
    </div>

    <!-- Stats Section -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-12">
        @if($isOwner || $isPublic)
            <div class="glass p-8 rounded-[35px] text-center group hover:bg-white/5 transition-all">
                <p class="text-[10px] text-gray-500 uppercase tracking-[0.2em] mb-2 font-bold">إجمالي الإعجابات</p>
                <h4 class="text-4xl font-extrabold accent-text-gradient">{{ number_format($stats['likes']) }}</h4>
            </div>
            <div class="glass p-8 rounded-[35px] text-center group hover:bg-white/5 transition-all">
                <p class="text-[10px] text-gray-500 uppercase tracking-[0.2em] mb-2 font-bold">الصور المعتمدة</p>
                <h4 class="text-4xl font-extrabold text-white">{{ number_format($stats['photos']) }}</h4>
            </div>
            <div class="glass p-8 rounded-[35px] text-center group hover:bg-white/5 transition-all">
                <p class="text-[10px] text-gray-500 uppercase tracking-[0.2em] mb-2 font-bold">الاتصالات</p>
                <h4 class="text-4xl font-extrabold text-white">{{ number_format($stats['connections']) }}</h4>
            </div>
        @else
            <div class="col-span-1 md:col-span-3 glass p-12 rounded-[40px] text-center border-dashed border-white/10">
                <div class="w-20 h-20 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
                <h3 class="text-2xl font-bold text-white mb-2">هذا الحساب خاص</h3>
                <p class="text-gray-400 max-w-sm mx-auto">المحتوى والإحصائيات مخفية من قبل المستخدم. فقط المتابعون/المتعاونون المقبولون يمكنهم رؤية المزيد.</p>
            </div>
        @endif
    </div>

    <!-- Gallery Section (if visible) -->
    @if(($isOwner || $isPublic) && $images->isNotEmpty())
        <div>
            <div class="flex items-center justify-between mb-8 px-2">
                <h3 class="text-2xl font-bold tracking-tight">معرض <span class="accent-text-gradient">المصور</span></h3>
                <div class="flex gap-2">
                    {{-- Filter buttons could go here --}}
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
                @foreach($images as $image)
                    <div class="group relative aspect-square rounded-[30px] overflow-hidden glass border border-white/5 animate-fade-in shadow-lg cursor-pointer"
                         @click="openModal({
                            id: '{{ $image->id }}',
                            title: '{{ $image->title ?: 'Untitled' }}',
                            description: '{{ $image->description ?: 'No description' }}',
                            url: '{{ $image->getUrlAttribute() }}',
                            user: {
                                id: '{{ $profileUser->id }}',
                                name: '{{ $profileUser->name }}',
                                avatar: '{{ $profileUser->avatar }}'
                            },
                            status: '{{ $image->status }}',
                            is_sensitive: {{ $image->is_sensitive ? 'true' : 'false' }},
                            labels: @json($image->labels ? $image->labels->labels : []),
                            likes_count: {{ $image->likes_count ?? 0 }},
                            allow_download: {{ $image->allow_download ? 'true' : 'false' }},
                            download_url: '{{ $image->allow_download ? route('images.download', $image) : '#' }}'
                         })">
                        <img src="{{ $image->getUrlAttribute() }}" 
                             alt="{{ $image->title }}" 
                             class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                             loading="lazy">
                        
                        <!-- Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-white text-xs font-bold truncate max-w-[120px]">{{ $image->title ?? 'بدون عنوان' }}</span>
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center gap-1 text-white">
                                        <svg class="w-4 h-4 text-red-500" :class="isLiked('{{ $image->id }}') ? 'fill-current' : 'fill-none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                                        <span class="text-[10px] font-bold" x-text="likeCount('{{ $image->id }}', {{ $image->likes_count ?? 0 }})"></span>
                                    </div>
                                    @if($image->allow_download)
                                        <div class="p-2 bg-white/20 rounded-full hover:bg-white/40 transition-colors">
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            
                            @if($image->tags)
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach($image->tags->take(2) as $tag)
                                        <span class="text-[8px] bg-white/10 px-2 py-0.5 rounded-full text-gray-300">#{{ $tag->name }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-12 overflow-hidden rounded-[30px] glass">
                {{ $images->links() }}
            </div>

            @include('images._detail_modal')
        </div>
    @elseif(($isOwner || $isPublic) && $images->isEmpty())
        <div class="glass p-20 rounded-[40px] text-center">
            <div class="w-20 h-20 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">لا توجد صور عامة</h3>
            <p class="text-gray-500">هذا المستخدم لم يقم بمشاركة أي صور في المعرض العام حتى الآن.</p>
        </div>
    @endif
</div>

<style>
    .animate-fade-in { animation: fadeIn 0.6s ease-out forwards; }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<script>
document.addEventListener('alpine:init', () => {
    const initialLikedIds = new Set(@json($likedImageIds ?? []));

    Alpine.data('galleryPage', () => ({
        isFollowing: {{ $isFollowing ? 'true' : 'false' }},
        likedIds: new Set(initialLikedIds),
        localCounts: {},
        selectedImage: null,
        showModal: false,

        openModal(data) {
            this.selectedImage = data;
            this.showModal = true;
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

        async toggleFollow(userId = '{{ $profileUser->id }}') {
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
                        Swal.fire({ 
                            toast: true, 
                            position: 'bottom-end', 
                            timer: 2500, 
                            icon: 'success', 
                            title: data.status === 'followed' ? 'تمت المتابعة بنجاح' : 'تم إلغاء المتابعة', 
                            showConfirmButton: false 
                        });
                    }
                }
            } catch (e) {
                console.error('Follow failed:', e);
            }
        },

        isLiked(id) {
            return this.likedIds.has(id);
        },

        likeCount(id, initialCount) {
            if (this.localCounts[id] === undefined) {
                this.localCounts[id] = initialCount;
            }
            return this.localCounts[id];
        },

        async toggleLike(id) {
            const wasLiked = this.likedIds.has(id);
            if (wasLiked) {
                this.likedIds.delete(id);
                this.localCounts[id] = Math.max(0, (this.localCounts[id] || 0) - 1);
            } else {
                this.likedIds.add(id);
                this.localCounts[id] = (this.localCounts[id] || 0) + 1;
            }
            this.likedIds = new Set(this.likedIds);

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const res = await fetch(`/images/${id}/like`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.message);
                if (json.action === 'like') { this.likedIds.add(id); }
                else { this.likedIds.delete(id); }
                this.likedIds = new Set(this.likedIds);
            } catch (e) {
                if (wasLiked) { this.likedIds.add(id); this.localCounts[id]++; }
                else { this.likedIds.delete(id); this.localCounts[id]--; }
                this.likedIds = new Set(this.likedIds);
            }
        }
    }));
});
</script>
@endsection
