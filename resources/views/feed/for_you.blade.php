<x-app-layout>
    <div class="py-12" x-data="forYouFeed()" x-init="fetchFeed()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-indigo-600 tracking-tight">For You</h2>
                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-widest font-bold">Personalized Discovery Feed</p>
                </div>
                <div x-show="isLoading" class="flex items-center space-x-2 text-purple-500">
                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sm font-medium">جاري تحديث الخوارزمية...</span>
                </div>
            </div>

            <!-- Feed Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6" x-show="!isLoading && images.length > 0" x-cloak>
                <template x-for="image in images" :key="image.id">
                    <div class="group relative bg-[#1a1a24] rounded-3xl overflow-hidden border border-white/5 shadow-xl hover:shadow-purple-500/20 transition-all duration-300 hover:-translate-y-1">
                        
                        <!-- Image Render -->
                        <div class="aspect-[4/5] w-full bg-gray-900 relative">
                            <img :src="`/assets/preview/${image.id}`" :alt="image.title" class="w-full h-full object-cover opacity-90 group-hover:opacity-100 transition-opacity duration-300" loading="lazy" />
                            
                            <!-- Overlay Gradient -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-60 group-hover:opacity-80 transition-opacity"></div>
                        </div>

                        <!-- Content & Actions -->
                        <div class="absolute bottom-0 left-0 right-0 p-5 flex items-end justify-between">
                            <div class="truncate pr-4">
                                <h3 class="text-white font-bold truncate text-sm" x-text="image.title || 'بدون عنوان'"></h3>
                                <p class="text-gray-400 text-xs mt-1">From Community</p>
                            </div>
                            
                            <!-- Like Button -->
                            <button 
                                @click.prevent="toggleLike(image.id)"
                                class="relative z-10 p-3 rounded-full backdrop-blur-md bg-white/10 hover:bg-white/20 transition-all transform hover:scale-110 active:scale-95 group/btn"
                                :class="{'bg-purple-500/30 border border-purple-500/50 text-purple-400': isLiked(image.id), 'text-white': !isLiked(image.id)}"
                            >
                                <svg class="w-5 h-5 transition-transform" :class="{'fill-current scale-110': isLiked(image.id)}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Empty State -->
            <div x-show="!isLoading && images.length === 0" class="text-center py-20 glass rounded-3xl border border-white/5" x-cloak>
                <div class="inline-flex p-4 rounded-full bg-purple-500/10 text-purple-400 mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                </div>
                <h3 class="text-xl font-bold text-white tracking-tight">لا يوجد محتوى لعرضه حالياً</h3>
                <p class="text-sm text-gray-400 mt-2">خوارزمية الاستكشاف تحاول جمع البيانات لك.</p>
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('forYouFeed', () => ({
                isLoading: false,
                images: [],
                likedIds: new Set(), // Store local likes temporarily

                async fetchFeed() {
                    this.isLoading = true;
                    try {
                        const res = await fetch('{{ route('feed.api.for_you') }}');
                        const json = await res.json();
                        if (json.success) {
                            this.images = json.data;
                            // Optionally, backend could return `liked_by_user` boolean per image, 
                            // but if not, we rely on immediate state for the session.
                        }
                    } catch (e) {
                        console.error('Failed to load feed:', e);
                        Swal.fire({ icon: 'error', title: 'خطأ', text: 'فشل جلب أحدث التوصيات.' });
                    } finally {
                        this.isLoading = false;
                    }
                },

                isLiked(id) {
                    return this.likedIds.has(id);
                },

                async toggleLike(id) {
                    // Optimistic UI Update
                    let wasLiked = this.likedIds.has(id);
                    if (wasLiked) {
                        this.likedIds.delete(id);
                    } else {
                        this.likedIds.add(id);
                    }
                    
                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        const res = await fetch(`/images/${id}/like`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            }
                        });
                        
                        const json = await res.json();
                        
                        if (res.status === 429) {
                            // Rate limited
                            Swal.fire({ toast: true, position: 'bottom-end', showConfirmButton: false, timer: 3000, icon: 'warning', title: 'مهلاً!', text: 'لقد قمت بإعجابات كثيرة جداً بوقت قصير.' });
                            // Revert
                            if (wasLiked) this.likedIds.add(id); else this.likedIds.delete(id);
                            return;
                        }

                        if (!json.success) throw new Error(json.message);

                        // Ensure UI matches actual action
                        if (json.action === 'like') {
                            this.likedIds.add(id);
                            Swal.fire({ toast: true, position: 'bottom-end', showConfirmButton: false, timer: 1500, icon: 'success', title: 'تم الإعجاب' });
                        } else {
                            this.likedIds.delete(id);
                        }

                    } catch (e) {
                        console.error('Like toggle failed:', e);
                        // Revert optimistic update
                        if (wasLiked) this.likedIds.add(id); else this.likedIds.delete(id);
                        Swal.fire({ toast: true, position: 'bottom-end', showConfirmButton: false, timer: 2000, icon: 'error', title: 'خطأ بالشبكة' });
                    }
                }
            }));
        });
    </script>
    @endpush
</x-app-layout>
