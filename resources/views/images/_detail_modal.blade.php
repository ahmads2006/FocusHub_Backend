{{-- resources/views/images/_detail_modal.blade.php --}}
<div x-show="showModal" 
     class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 scale-95"
     x-transition:enter-end="opacity-100 scale-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 scale-100"
     x-transition:leave-end="opacity-0 scale-95"
     x-cloak>
    
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/90 backdrop-blur-xl" @click="closeModal()"></div>
    
    {{-- Modal Content --}}
    <div class="relative w-full max-w-6xl glass-dark rounded-[40px] overflow-hidden flex flex-col lg:flex-row h-full max-h-[90vh] shadow-2xl border border-white/10" dir="rtl">
        
        {{-- Image Section --}}
        <div class="lg:flex-1 bg-black/20 flex items-center justify-center relative overflow-hidden group">
            <template x-if="selectedImage">
                <img :src="selectedImage.url" 
                     class="max-w-full max-h-full object-contain shadow-2xl transition-all duration-700 group-hover:scale-105 is-loaded" 
                     alt="">
            </template>
            
            <button @click="closeModal()" class="absolute top-6 right-6 lg:hidden action-btn bg-black/40 border-white/10 hover:bg-black/60 shadow-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Info Section --}}
        <div class="w-full lg:w-[450px] p-8 flex flex-col overflow-y-auto vault-scroll border-r border-white/5 bg-white/[0.02] text-right">
            {{-- Close Button (Desktop) --}}
            <button @click="closeModal()" class="hidden lg:flex self-end action-btn hover:bg-white/10 transition-all mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            {{-- Photographer Info --}}
            <div class="flex items-center justify-between mb-8 p-4 rounded-3xl bg-white/5 border border-white/5 hover:bg-white/10 transition-all">
                <div class="flex items-center gap-4">
                    <img :src="selectedImage?.user.avatar" class="w-12 h-12 rounded-full border border-purple-500/30 glow p-0.5" alt="">
                    <div class="text-right">
                        <h4 class="font-bold text-white text-base" x-text="selectedImage?.user.name"></h4>
                        <p class="text-[10px] text-gray-500 uppercase tracking-widest">مصور محترف</p>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <button @click="toggleFollow(selectedImage?.user.id)" 
                            class="px-4 py-1.5 rounded-full text-[10px] font-bold transition-all border border-purple-500/30"
                            :class="isFollowing ? 'bg-purple-500 text-white shadow-lg shadow-purple-500/20' : 'text-purple-400 hover:bg-purple-500/10'"
                            x-text="isFollowing ? 'متابع ✓' : 'متابعة +'">
                    </button>
                    <a :href="'/photographer/' + selectedImage?.user.id" class="text-[9px] text-gray-400 hover:text-white text-center underline decoration-gray-700 underline-offset-4">زيارة الحساب</a>
                </div>
            </div>

            {{-- Image Details --}}
            <div class="space-y-6">
                <div>
                    <h3 class="text-2xl font-bold text-white italic font-display" x-text="selectedImage?.title"></h3>
                    <p class="text-gray-400 text-sm mt-2 leading-relaxed" x-text="selectedImage?.description"></p>
                </div>

                {{-- Status & Classification --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="glass p-4 rounded-[25px] flex flex-col items-center">
                        <span class="text-[9px] text-gray-500 uppercase tracking-widest mb-1">الحالة</span>
                        <div class="flex items-center gap-2">
                           <div class="w-2 h-2 rounded-full shadow-sm" :class="selectedImage?.status === 'approved' ? 'bg-green-500 shadow-green-500/50' : 'bg-amber-500 shadow-amber-500/50'"></div>
                           <span class="text-xs font-bold" :class="selectedImage?.status === 'approved' ? 'text-green-500' : 'text-amber-500'" x-text="selectedImage?.status === 'approved' ? 'آمنة جداً (خضراء)' : 'تحت المراجعة (صفراء)'"></span>
                        </div>
                    </div>
                    <div class="glass p-4 rounded-[25px] flex flex-col items-center">
                        <span class="text-[9px] text-gray-500 uppercase tracking-widest mb-1">النوع</span>
                        <span class="text-xs font-bold text-violet-400" x-text="isNature() ? 'طبيعة ⛰️' : 'تصنيف عام 🖼️'"></span>
                    </div>
                </div>

                {{-- Tags --}}
                <div class="flex flex-wrap gap-2 pt-2">
                    <template x-for="label in selectedImage?.labels || []" :key="label.description">
                        <span class="text-[10px] bg-white/5 border border-white/5 px-3 py-1 rounded-full text-gray-400 transition-colors hover:border-violet-500/30" x-text="'#' + label.description"></span>
                    </template>
                </div>

                {{-- Actions --}}
                <div class="pt-8 mt-auto flex flex-wrap items-center gap-4">
                    <button @click="toggleLike(selectedImage?.id)" 
                            class="flex-1 flex items-center justify-center gap-2 py-4 rounded-2xl glass hover:bg-white/10 group transition-all"
                            :class="isLiked(selectedImage?.id) ? 'text-rose-500 border-rose-500/30' : 'text-gray-400'">
                        <svg class="w-5 h-5 transition-transform group-hover:scale-110" :class="isLiked(selectedImage?.id) ? 'fill-current' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        <span class="font-bold text-sm" x-text="likeCount(selectedImage?.id, selectedImage?.likes_count)"></span>
                    </button>

                    <button @click="toggleBookmark(selectedImage?.id)" 
                            class="flex-1 flex items-center justify-center gap-2 py-4 rounded-2xl glass hover:bg-white/10 group transition-all"
                            :class="isBookmarked(selectedImage?.id) ? 'text-yellow-400 border-yellow-400/30' : 'text-gray-400'">
                        <svg class="w-5 h-5 transition-transform group-hover:scale-110" :class="isBookmarked(selectedImage?.id) ? 'fill-current' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                        <span class="font-bold text-sm" x-text="isBookmarked(selectedImage?.id) ? 'محفوظ' : 'حفظ'"></span>
                    </button>

                    <button @click="openShareModal()" 
                            class="flex-full w-full mt-2 flex items-center justify-center gap-2 py-3 rounded-2xl border border-white/10 hover:bg-white/5 transition-all text-gray-300">
                        <svg class="w-5 h-5 bg-transparent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        <span class="font-bold text-sm">إرسال لصديق في المحادثة</span>
                    </button>

                    <template x-if="selectedImage?.allow_download">
                        <a :href="selectedImage?.download_url" 
                           class="w-full accent-gradient flex items-center justify-center gap-2 py-4 rounded-2xl font-bold text-sm text-white shadow-lg shadow-purple-500/20 hover:scale-105 transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            تنزيل الصورة
                        </a>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

    {{-- Share to Chat Modal Overlay --}}
    <div x-show="showShareModal" 
         class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         x-cloak>
        <div class="glass w-full max-w-sm rounded-[32px] overflow-hidden flex flex-col max-h-[70vh] shadow-2xl border border-white/10" @click.away="closeShareModal()">
            <div class="p-6 border-b border-white/5 flex justify-between items-center bg-white/[0.02]">
                <h3 class="text-xl font-bold font-display" dir="rtl">إرسال الصورة</h3>
                <button @click="closeShareModal()" class="p-2 rounded-full hover:bg-white/10 text-gray-500 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-4 custom-scrollbar bg-black/20" dir="rtl">
                <template x-if="loadingConnections">
                    <div class="flex items-center justify-center py-10">
                        <svg class="animate-spin w-8 h-8 text-purple-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </div>
                </template>
                <template x-if="!loadingConnections && connections.length > 0">
                    <div class="space-y-3">
                        <template x-for="conn in connections" :key="conn.id">
                            <button @click="sendToPartner(conn.id)" 
                                    class="w-full flex items-center justify-between p-3 rounded-2xl hover:bg-white/5 transition-colors border border-transparent hover:border-white/10"
                                    :disabled="sendingShareTo !== null">
                                <div class="flex items-center gap-3">
                                    <img :src="conn.avatar" class="w-10 h-10 rounded-full object-cover">
                                    <span class="font-semibold text-sm text-gray-200" x-text="conn.name"></span>
                                </div>
                                <div x-show="sendingShareTo === conn.id" class="px-2">
                                    <svg class="animate-spin w-4 h-4 text-purple-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                </div>
                                <div x-show="sendingShareTo !== conn.id">
                                    <svg class="w-5 h-5 text-gray-500 hover:text-purple-400 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                </div>
                            </button>
                        </template>
                    </div>
                </template>
                <template x-if="!loadingConnections && connections.length === 0">
                    <div class="py-10 text-center">
                        <p class="text-gray-500 text-sm">ليس لديك أي اتصالات بعد. تابع مصورين آخرين ليتابعوك لبدء مشاركة الصور.</p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
