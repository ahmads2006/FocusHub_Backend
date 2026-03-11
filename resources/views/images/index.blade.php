@extends('layouts.premium')

@section('title', 'Image Management')

@section('content')
<div class="space-y-8" dir="rtl" x-data="{ view: '{{ request()->query('view', 'all') }}' }">
    
    <!-- Page Header -->
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">إدارة <span class="accent-text-gradient">الأصول</span></h2>
            <p class="text-gray-400 mt-1">تنظيم وحماية مكتبتك الفوتوغرافية.</p>
        </div>
        
        @if(session('success'))
            <div class="bg-green-500/10 text-green-400 border border-green-500/20 px-6 py-2 rounded-2xl flex items-center gap-2 animate-bounce">
                 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                 <span class="text-sm font-bold">{{ session('success') }}</span>
            </div>
        @endif
    </div>

    <!-- Top Grid: Controls -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Create Album Panel -->
        <div class="glass p-8 rounded-[40px] flex flex-col">
            <h3 class="text-xs font-bold uppercase tracking-widest text-purple-400 mb-6">ألبوم جديد</h3>
            <form action="{{ route('albums.store') }}" method="POST" class="space-y-6">
                @csrf
                <div class="space-y-2">
                    <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">عنوان الألبوم</label>
                    <input type="text" name="title" required class="w-full bg-white/5 border border-white/10 rounded-2xl p-3 px-4 focus:ring-2 focus:ring-purple-500/50 outline-none transition-all" placeholder="ذكريات الرحلة...">
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">الخصوصية</label>
                    <select name="privacy" class="w-full bg-white/5 border border-white/10 rounded-2xl p-3 px-4 focus:ring-2 focus:ring-purple-500/50 outline-none transition-all">
                        <option value="public" class="bg-black">عام (Public)</option>
                        <option value="private" selected class="bg-black">خاص (Private)</option>
                        <option value="hidden" class="bg-black">مخفي (Hidden)</option>
                    </select>
                </div>
                <button type="submit" class="w-full bg-white/5 border border-white/10 hover:border-purple-500/50 hover:bg-purple-500/5 text-xs font-bold uppercase tracking-widest p-4 rounded-2xl transition-all">إنشاء الألبوم</button>
            </form>
        </div>

        <!-- Unified Upload Panel -->
        <div class="lg:col-span-2 glass p-8 rounded-[40px] border-purple-500/10 border relative overflow-hidden">
            <div class="absolute top-0 right-0 p-8 opacity-5">
                <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path></svg>
            </div>
            
            <h3 class="text-xs font-bold uppercase tracking-widest text-purple-400 mb-6">رفع أصل احترافي</h3>
            
            <form action="{{ route('images.store') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                @csrf
                
                <div class="space-y-4">
                    <div class="relative group h-40 border-2 border-dashed border-white/10 rounded-3xl hover:border-purple-500/50 transition-all flex flex-col items-center justify-center bg-white/5">
                        <input type="file" name="image" accept="image/*" required class="absolute inset-0 opacity-0 cursor-pointer">
                        <svg class="w-10 h-10 text-gray-600 group-hover:text-purple-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        <p class="text-[10px] font-bold uppercase tracking-tighter text-gray-500">انقر أو اسحب الصورة هنا</p>
                    </div>
                    @error('image') <p class="text-red-400 text-[10px] font-bold">{{ $message }}</p> @enderror
                    
                    <button type="submit" class="w-full accent-gradient p-4 rounded-2xl font-bold uppercase tracking-widest text-xs shadow-lg shadow-purple-500/20 hover:scale-[1.02] transition-transform">بدء المعالجة والرفع</button>
                </div>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">عنوان الصورة</label>
                        <input type="text" name="title" class="w-full bg-white/5 border border-white/10 rounded-2xl p-3 px-4 outline-none focus:ring-2 focus:ring-purple-500/50 transition-all" placeholder="بلا عنوان...">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">الألبوم</label>
                            <select name="album_id" class="w-full bg-white/5 border border-white/10 rounded-2xl p-3 px-4 outline-none focus:ring-2 focus:ring-purple-500/50 transition-all h-12">
                                <option value="" class="bg-black">بدون ألبوم</option>
                                @foreach($ownedAlbums as $album)
                                    <option value="{{ $album->id }}" class="bg-black">{{ $album->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">الخصوصية</label>
                            <select name="privacy" class="w-full bg-white/5 border border-white/10 rounded-2xl p-3 px-4 outline-none focus:ring-2 focus:ring-purple-500/50 transition-all h-12">
                                <option value="public" class="bg-black">عامة</option>
                                <option value="private" selected class="bg-black">خاصة</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        {{-- New Download & Watermark Toggles --}}
                        <div class="flex items-center justify-between p-3 bg-purple-500/5 rounded-2xl border border-white/5 hover:border-purple-500/30 transition-all group">
                             <div class="flex flex-col">
                                 <label class="text-[10px] uppercase tracking-widest text-purple-400 font-bold">السماح بالتنزيل</label>
                                 <span class="text-[8px] text-gray-500">Allow Download</span>
                             </div>
                             <input type="checkbox" name="allow_download" value="1" checked 
                                    class="w-4 h-4 accent-purple-500 cursor-pointer" 
                                    onchange="document.getElementById('upload_watermark_toggle').style.opacity = this.checked ? '1' : '0.4'; document.getElementById('upload_watermark_cb').disabled = !this.checked;">
                        </div>
                        <div id="upload_watermark_toggle" class="flex items-center justify-between p-3 bg-white/5 rounded-2xl border border-white/5 hover:border-purple-500/30 transition-all group">
                             <div class="flex flex-col">
                                 <label class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">إضافة علامة مائية</label>
                                 <span class="text-[8px] text-gray-500">Add Watermark</span>
                             </div>
                             <input type="checkbox" id="upload_watermark_cb" name="watermark_on_download" value="1" class="w-4 h-4 accent-purple-500 cursor-pointer">
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div class="space-y-8">
        
        <!-- Section Toggle -->
        <div class="flex items-center gap-8 border-b border-white/5 pb-2">
            <button @click="view = 'all'" :class="view === 'all' ? 'text-white border-b-2 border-purple-500' : 'text-gray-500'" class="pb-4 font-bold tracking-tight px-2 transition-all">جميع الصور</button>
            <button @click="view = 'albums'" :class="view === 'albums' ? 'text-white border-b-2 border-purple-500' : 'text-gray-500'" class="pb-4 font-bold tracking-tight px-2 transition-all">الألبومات</button>
        </div>

        <!-- Images Grid -->
        <div x-show="view === 'all'" x-transition class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($images as $image)
                <div class="glass group rounded-[32px] overflow-hidden border border-white/5 hover:border-purple-500/30 transition-all duration-500" id="image-card-{{ $image->id }}">
                    <div class="relative h-48 overflow-hidden">
                        <img src="{{ $image->url }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-1000">
                        {{-- Shield badge: shown only if a protected copy is active --}}
                        @if(isset($protectedImageIds[$image->id]))
                        <div class="absolute top-3 left-3 bg-purple-500/80 backdrop-blur-md text-white text-[9px] font-bold uppercase tracking-widest px-2 py-1 rounded-lg flex items-center gap-1" title="SecureShield Active">
                            🛡 محمية
                        </div>
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent flex flex-col justify-end p-6 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                             <div class="flex flex-col gap-3">
                                <div class="flex gap-2">
                                    <button onclick="generateShareOnceLink('{{ $image->id }}', this)" class="flex-1 bg-white/10 backdrop-blur-md rounded-xl p-2 text-[10px] font-bold uppercase tracking-widest hover:bg-white/20 transition-all">مشاركة لمرة</button>
                                    <button onclick="openShareModal('{{ $image->id }}', '{{ addslashes(App\Models\Image::class) }}')" class="p-2 bg-purple-500/20 backdrop-blur-md rounded-xl hover:bg-purple-500/40 transition-all">
                                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6a3 3 0 100-2.684m0 2.684l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                                    </button>
                                </div>
                                <form action="{{ route('images.destroy', $image) }}" method="POST" onsubmit="return confirm('حذف نهائي؟')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="w-full text-[10px] font-bold text-red-400 bg-red-400/5 p-2 rounded-xl hover:bg-red-400/20 transition-all border border-red-400/10 uppercase tracking-widest">حذف بشكل دائم</button>
                                </form>
                                {{-- SecureShield v2.0 Actions --}}
                                @if(isset($protectedImageIds[$image->id]))
                                    <button onclick="revertSecureShield('{{ $image->id }}')" class="w-full text-[10px] font-bold text-yellow-500 bg-yellow-500/10 p-2 rounded-xl hover:bg-yellow-500/20 transition-all border border-yellow-500/20 uppercase tracking-widest flex items-center justify-center gap-2">
                                        <span>🔓 إلغاء حماية SecureShield</span>
                                    </button>
                                @else
                                    <div class="flex flex-col gap-2">
                                        <button onclick="applySecureShield('{{ $image->id }}', 'signature')" class="w-full text-[10px] font-bold text-purple-400 bg-purple-400/10 p-2 rounded-xl hover:bg-purple-400/20 transition-all border border-purple-400/20 uppercase tracking-widest flex items-center justify-center gap-2 group/btn">
                                            <span>🛡 SecureShield Signature</span>
                                            <svg class="w-3 h-3 group-hover/btn:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                                        </button>
                                        <button onclick="applySecureShield('{{ $image->id }}', 'grid')" class="w-full text-[10px] font-bold text-blue-400 bg-blue-400/10 p-2 rounded-xl hover:bg-blue-400/20 transition-all border border-blue-400/10 uppercase tracking-widest flex items-center justify-center gap-2 group/btn">
                                            <span>⚡ Total Grid Overlay</span>
                                        </button>
                                    </div>
                                @endif
                             </div>
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-bold text-sm truncate max-w-[120px]">{{ $image->title ?? 'Untitled' }}</h4>
                            <span class="text-[8px] font-bold uppercase px-2 py-0.5 rounded bg-white/5 border border-white/10 text-gray-500">{{ $image->file_type }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-1.5 h-1.5 rounded-full {{ $image->privacy === 'public' ? 'bg-green-500' : 'bg-red-500' }}"></div>
                            <span class="text-[10px] text-gray-400 uppercase tracking-widest font-bold">{{ $image->privacy }}</span>
                            <span class="text-gray-700 mx-1">•</span>
                            <span class="text-[10px] text-gray-600 font-mono">{{ number_format($image->size / 1024 / 1024, 2) }} MB</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Albums View -->
        <div x-show="view === 'albums'" x-transition class="space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach($ownedAlbums as $album)
                    <div class="glass p-6 rounded-[40px] flex gap-6 items-center group hover:bg-white/5 transition-all">
                        <div class="w-20 h-20 rounded-3xl glass-dark border border-white/5 flex items-center justify-center relative overflow-hidden">
                            @if($album->images->first())
                                <img src="{{ $album->images->first()->url }}" class="w-full h-full object-cover opacity-40 group-hover:opacity-100 transition-opacity">
                            @else
                                <svg class="w-8 h-8 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold truncate text-lg">{{ $album->title }}</h4>
                            <p class="text-xs text-gray-500 uppercase tracking-widest mt-1">{{ $album->images->count() }} Assets</p>
                            <a href="{{ route('albums.show', $album) }}" class="inline-block mt-3 text-[10px] font-bold text-purple-400 hover:text-purple-300 transition-colors uppercase tracking-widest">إدارة المحتوى ←</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

</div>

@push('scripts')
<script>
    async function generateShareOnceLink(imageId, btn) {
        const originalText = btn.innerText;
        btn.innerText = 'جاري النسخ...';
        btn.disabled = true;

        try {
            const response = await fetch(`/images/${imageId}/share-once`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (response.ok && data.success) {
                await navigator.clipboard.writeText(data.url);
                Swal.fire({
                    title: 'تم النسخ!',
                    text: 'رابط العرض لمرة واحدة جاهز الآن. سيتدمر فور مشاهدته.',
                    icon: 'success',
                    confirmButtonColor: '#a855f7'
                });
            }
        } catch (error) {
            Swal.fire('خطأ', 'فشل إنشاء الرابط', 'error');
        } finally {
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }

    async function applySecureShield(imageId, mode) {
        // Step 1: Identity Detection Prompt
        const { value: formValues } = await Swal.fire({
            title: '<h3 class="text-xl font-bold tracking-tight">إعداد <span class="accent-text-gradient">الهوية الرقمية</span></h3>',
            html: `
                <div class="space-y-4 p-2 text-right dir-rtl">
                    <div class="space-y-2">
                        <label class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">اسم العلامة المائية (Brand Name)</label>
                        <input id="swal-input-text" class="w-full bg-white/5 border border-white/10 rounded-2xl p-3 outline-none focus:border-purple-500/50 transition-all text-sm" value="{{ addslashes(auth()->user()->name) }}">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">لون النص</label>
                            <input type="color" id="swal-input-text-color" class="w-full h-10 bg-white/5 border border-white/10 rounded-xl cursor-pointer" value="{{ auth()->user()->watermark_text_color ?? '#ffffff' }}">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">لون التوهج (Neon)</label>
                            <input type="color" id="swal-input-neon-color" class="w-full h-10 bg-white/5 border border-white/10 rounded-xl cursor-pointer" value="{{ auth()->user()->watermark_neon_color ?? '#800080' }}">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">قوة التأثير (Opacity): <span id="opacity-val">80</span>%</label>
                        <input type="range" id="swal-input-opacity" min="10" max="100" step="5" class="w-full accent-purple-500" value="{{ (auth()->user()->watermark_opacity ?? 0.8) * 100 }}" oninput="document.getElementById('opacity-val').innerText = this.value">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">شعار الهوية (اختياري - Logo)</label>
                        <input type="file" id="swal-input-logo" class="w-full bg-white/5 border border-white/10 rounded-2xl p-3 outline-none focus:border-purple-500/50 transition-all text-xs" accept="image/*">
                    </div>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'تطبيق الحماية',
            cancelButtonText: 'تراجع',
            customClass: {
                popup: 'glass rounded-[32px] border border-white/10',
                confirmButton: 'accent-gradient px-8 py-3 rounded-xl font-bold uppercase tracking-widest text-xs',
                cancelButton: 'bg-white/5 px-8 py-3 rounded-xl font-bold'
            },
            preConfirm: () => {
                return {
                    text: document.getElementById('swal-input-text').value,
                    textColor: document.getElementById('swal-input-text-color').value,
                    neonColor: document.getElementById('swal-input-neon-color').value,
                    opacity: document.getElementById('swal-input-opacity').value / 100,
                    logoFile: document.getElementById('swal-input-logo').files[0]
                }
            }
        });

        if (!formValues) return;

        // Step 2: Show Processing Dialog
        Swal.fire({
            title: 'SecureShield is analyzing your asset...',
            html: '<p class="text-gray-400 text-sm">Identity detected. Applying Glass-Neon protection layers.</p>',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const formData = new FormData();
        formData.append('mode', mode);
        formData.append('watermark_text', formValues.text);
        formData.append('watermark_text_color', formValues.textColor);
        formData.append('watermark_neon_color', formValues.neonColor);
        formData.append('watermark_opacity', formValues.opacity);
        if (formValues.logoFile) {
            formData.append('watermark_logo', formValues.logoFile);
        }
        formData.append('_token', '{{ csrf_token() }}');

        try {
            const response = await fetch(`/images/${imageId}/protect`, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            });
            const data = await response.json();

            if (response.ok && data.success) {
                await Swal.fire({
                    title: 'الحماية مكتملة ✅',
                    text: 'تم تأمين الأصل وأرشفة النسخة المحمية بنجاح.',
                    icon: 'success',
                    timer: 3000,
                    showConfirmButton: false
                });
                location.reload();
            } else {
                Swal.fire('خطأ', data.message || 'فشل تطبيق الحماية', 'error');
            }
        } catch (err) {
            console.error(err);
            Swal.fire('خطأ', 'حدث خطأ غير متوقع أثناء المعالجة', 'error');
        }
    }

    async function revertSecureShield(imageId) {
        const confirm = await Swal.fire({
            title: 'إلغاء الحماية؟',
            text: 'سيتم إلغاء حماية SecureShield. سيحمّل الزوار النسخة الأصلية عند التنزيل.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، إلغاء الحماية',
            cancelButtonText: 'تراجع',
            confirmButtonColor: '#eab308',
        });
        if (!confirm.isConfirmed) return;

        try {
            const response = await fetch(`/images/${imageId}/protection`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                }
            });
            const data = await response.json();
            if (response.ok && data.success) {
                Swal.fire({ title: 'تم', text: 'تم إلغاء الحماية بنجاح.', icon: 'success', timer: 2000, showConfirmButton: false });
                setTimeout(() => location.reload(), 1800);
            } else {
                Swal.fire('خطأ', data.message || 'فشل إلغاء الحماية', 'error');
            }
        } catch (err) {
            Swal.fire('خطأ', 'حدث خطأ غير متوقع', 'error');
        }
    }
</script>
@endpush

@include('shared_links._generate_modal')
@endsection