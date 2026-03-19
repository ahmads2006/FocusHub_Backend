<!-- Premium Secret Link Modal (Tailwind + Alpine.js) -->
<div 
    x-data="sharedLinkModal()"
    x-init="window.openShareModal = (id, type) => openModal(id, type)"
    x-show="isOpen"
    @keydown.escape.window="isOpen = false"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
    x-cloak
>
    <!-- Backdrop -->
    <div 
        x-show="isOpen" 
        x-transition:enter="ease-out duration-300" 
        x-transition:enter-start="opacity-0" 
        x-transition:enter-end="opacity-100" 
        x-transition:leave="ease-in duration-200" 
        x-transition:leave-start="opacity-100" 
        x-transition:leave-end="opacity-0" 
        class="fixed inset-0 bg-black/80 backdrop-blur-sm"
        @click="isOpen = false"
    ></div>

    <!-- Modal Content -->
    <div 
        x-show="isOpen" 
        x-transition:enter="ease-out duration-300" 
        x-transition:enter-start="opacity-0 scale-95" 
        x-transition:enter-end="opacity-100 scale-100" 
        x-transition:leave="ease-in duration-200" 
        x-transition:leave-start="opacity-100 scale-100" 
        x-transition:leave-end="opacity-0 scale-95" 
        class="glass rounded-[40px] w-full max-w-lg overflow-hidden relative border border-white/10 shadow-2xl"
    >
        <div class="accent-gradient h-2"></div>
        
        <div class="p-8 sm:p-10 space-y-8">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold tracking-tight">إنشاء <span class="accent-text-gradient">رابط سري</span></h3>
                    <p class="text-xs text-gray-500 mt-1 uppercase tracking-widest font-bold">Secure Access Management</p>
                </div>
                <button @click="isOpen = false" class="text-gray-500 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Form -->
            <form id="premium-share-form" class="space-y-6" @submit.prevent="generateLink">
                
                <div class="grid grid-cols-1 gap-6">
                    <!-- Allow Download Toggle -->
                    <div class="glass-dark p-6 rounded-3xl border border-white/5">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-bold tracking-tight">السماح بالتنزيل</h4>
                                <p class="text-[10px] text-gray-500 mt-1 uppercase">Allow Download</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="allow_download" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                            </label>
                        </div>
                    </div>

                    <!-- Enforce Watermark Toggle -->
                    <div class="glass-dark p-6 rounded-3xl border border-white/5">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-bold tracking-tight">إلزامية العلامة المائية (SecureShield)</h4>
                                <p class="text-[10px] text-gray-500 mt-1 uppercase">Enforce Glass-Neon Protection</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="require_watermark" class="sr-only peer">
                                <div class="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">الصلاحية (بالساعات)</label>
                        <input type="number" name="expires_in" placeholder="دائم..." class="w-full bg-white/5 border border-white/10 rounded-2xl p-3 outline-none focus:border-purple-500/50 transition-all text-sm">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">مرات الدخول</label>
                        <input type="number" min="1" name="max_access" placeholder="غير محدود" class="w-full bg-white/5 border border-white/10 rounded-2xl p-3 outline-none focus:border-purple-500/50 transition-all text-sm">
                    </div>
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <label class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">كلمة السر (اختياري)</label>
                    <input type="text" name="password" placeholder="اتركه فارغاً لرابط مفتوح..." autocomplete="off" class="w-full bg-white/5 border border-white/10 rounded-2xl p-3 outline-none focus:border-purple-500/50 transition-all text-sm font-mono placeholder-gray-500">
                </div>

                <!-- Auto Rotate Toggle -->
                <div class="glass-dark p-4 rounded-3xl border border-white/5 flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-bold">قفل الجهاز (Auto-Rotate)</h4>
                        <p class="text-[9px] text-gray-500">يمنع المشاركة بعد الفتح الأول.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer scale-75">
                        <input type="checkbox" name="auto_rotate" class="sr-only peer">
                        <div class="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit" 
                    :disabled="isGenerating || resultUrl !== ''"
                    class="w-full accent-gradient p-4 rounded-2xl font-bold uppercase tracking-widest text-xs shadow-lg shadow-purple-500/20 hover:scale-[1.02] transition-all disabled:opacity-50 disabled:grayscale"
                >
                    <span x-show="!isGenerating">توليد الرابط السري</span>
                    <span x-show="isGenerating">جاري التوليد...</span>
                </button>
            </form>

            <!-- Result Section -->
            <div x-show="resultUrl" x-transition class="space-y-4 pt-4 border-t border-white/5 animate-in fade-in slide-in-from-top-4">
                <div class="flex items-center gap-2 text-green-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span class="text-xs font-bold uppercase tracking-widest">جاهز للمشاركة</span>
                </div>
                <div class="flex gap-2">
                    <input 
                        type="text" 
                        readonly 
                        :value="resultUrl" 
                        id="secret-link-input"
                        class="flex-1 bg-white/5 border border-white/10 rounded-2xl p-3 text-xs font-mono outline-none"
                    >
                    <button 
                        @click="
                            const el = document.getElementById('secret-link-input');
                            el.select();
                            navigator.clipboard.writeText(resultUrl);
                            Swal.fire({ title: 'تم النسخ!', text: 'تم حفظ الرابط في الحافظة.', icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
                        "
                        class="bg-purple-500/20 text-purple-400 p-3 px-6 rounded-2xl text-[10px] font-bold uppercase tracking-widest hover:bg-purple-500/40 transition-all border border-purple-500/20"
                    >
                        نسخ
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('sharedLinkModal', () => ({
            isOpen: false,
            isGenerating: false,
            resultUrl: '',
            shareableId: '',
            shareableType: '',
            message: '',
            openModal(id, type) {
                this.shareableId = id;
                this.shareableType = type;
                this.isOpen = true;
                this.resultUrl = '';
                this.message = '';
            },
            async generateLink(e) {
                this.isGenerating = true;
                const form = e.target;
                const body = {
                    shareable_id: this.shareableId,
                    shareable_type: this.shareableType,
                    auto_rotate: form.auto_rotate && form.auto_rotate.checked ? 1 : 0,
                    require_watermark: form.require_watermark ? (form.require_watermark.checked ? 1 : 0) : null,
                    permission: form.allow_download && form.allow_download.checked ? 'download' : 'view',
                    expires_in: form.expires_in ? (form.expires_in.value || null) : null,
                    max_access: form.max_access ? (form.max_access.value || null) : null,
                    password: form.password ? (form.password.value || null) : null,
                    _token: '{{ csrf_token() }}'
                };

                try {
                    const response = await fetch('{{ route('share.generate') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify(body)
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.resultUrl = data.url;
                        this.message = data.message;
                    } else {
                        Swal.fire('خطأ', data.message || 'فشل إنشاء الرابط', 'error');
                    }
                } catch (err) {
                    console.error(err);
                    Swal.fire('خطأ', 'حدث خطأ غير متوقع أثناء الاتصال بالخادم.', 'error');
                } finally {
                    this.isGenerating = false;
                }
            }
        }));
    });
</script>
@endpush
