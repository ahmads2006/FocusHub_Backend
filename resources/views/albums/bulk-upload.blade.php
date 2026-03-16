@extends('layouts.premium')

@section('title', 'Professional Bulk Upload Lab')

@section('content')
<div class="space-y-8" dir="rtl">
    
    <!-- Page Header -->
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">مختبر <span class="accent-text-gradient">الرفع الاحترافي</span></h2>
            <p class="text-gray-400 mt-1">قم برفع ألبومات كاملة ومجلدات ضخمة مع معالجة ذكية في الخلفية.</p>
        </div>
        
        <div class="flex gap-4">
            <div class="glass px-6 py-2 rounded-2xl flex items-center gap-2">
                 <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                 <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">خادم المعالجة: متصل</span>
            </div>
        </div>
    </div>

    <!-- Main Grid based on User Drawing -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- TOP BOX: Single/Multi Image Upload Section (امكانيات رفع صور) -->
        <div class="glass p-8 rounded-[40px] border-purple-500/10 border relative overflow-hidden flex flex-col h-full">
            <div class="absolute top-0 right-0 p-8 opacity-5">
                <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path></svg>
            </div>
            
            <div class="mb-8">
                <h3 class="text-sm font-bold uppercase tracking-widest text-purple-400 mb-2">امكانيات رفع الصور</h3>
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-tighter">Professional Image Uplift</p>
            </div>

            <form action="{{ route('images.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6 flex-1 flex flex-col">
                @csrf
                <div class="space-y-4 flex-1">
                    <div class="relative group h-64 border-2 border-dashed border-white/10 rounded-[32px] hover:border-purple-500/50 transition-all flex flex-col items-center justify-center bg-white/5">
                        <input type="file" name="image" accept="image/*" required class="absolute inset-0 opacity-0 cursor-pointer">
                        <svg class="w-16 h-16 text-gray-600 group-hover:text-purple-400 mb-4 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-500">اسحب الصور الفردية هنا</p>
                        <p class="text-[10px] text-gray-600 mt-2 italic">يدعم JPG, PNG, WEBP حتى 50 ميجابايت</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">استهداف الألبوم</label>
                            <select name="album_id" class="w-full bg-white/5 border border-white/10 rounded-2xl p-3 px-4 outline-none focus:ring-2 focus:ring-purple-500/50 transition-all text-xs">
                                <option value="" class="bg-black">بدون ألبوم</option>
                                @foreach($ownedAlbums as $album)
                                    <option value="{{ $album->id }}" class="bg-black">{{ $album->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">الخصوصية الافتراضية</label>
                            <select name="privacy" class="w-full bg-white/5 border border-white/10 rounded-2xl p-3 px-4 outline-none focus:ring-2 focus:ring-purple-500/50 transition-all text-xs">
                                <option value="public" class="bg-black">عامة</option>
                                <option value="private" selected class="bg-black">خاصة</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full accent-gradient p-5 rounded-[24px] font-bold uppercase tracking-widest text-xs shadow-lg shadow-purple-500/20 hover:scale-[1.02] transition-transform flex items-center justify-center gap-3">
                    <span>بدء المعالجة الفورية</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                </button>
            </form>
        </div>

        <!-- BOTTOM BOX: Full Album Upload Section (امكانيات رفع البوم كامل) -->
        <div class="glass p-8 rounded-[40px] border-blue-500/20 border relative overflow-hidden flex flex-col h-full bg-blue-500/5">
            <div class="absolute top-0 right-0 p-8 opacity-5">
                <svg class="w-24 h-24 text-blue-400" fill="currentColor" viewBox="0 0 24 24"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
            </div>

            <div class="mb-8">
                <h3 class="text-sm font-bold uppercase tracking-widest text-blue-400 mb-2">امكانيات رفع ألبوم كامل</h3>
                <p class="text-[10px] text-blue-400/60 font-bold uppercase tracking-tighter">Bulk Directory Synchronization</p>
            </div>

            <div class="space-y-6 flex-1 flex flex-col">
                <div class="space-y-4 flex-1">
                    <div id="bulk-folder-dropzone" class="relative group h-64 border-2 border-dashed border-blue-500/20 rounded-[32px] hover:border-blue-500/50 transition-all flex flex-col items-center justify-center bg-blue-500/5">
                        <input type="file" id="bulk_folder_input" webkitdirectory directory multiple class="absolute inset-0 opacity-0 cursor-pointer" onchange="handleFolderSelection(event)">
                        <div class="p-6 rounded-full bg-blue-500/10 mb-4 group-hover:scale-110 transition-transform">
                            <svg class="w-12 h-12 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path></svg>
                        </div>
                        <p class="text-xs font-bold uppercase tracking-widest text-blue-400">اختر مجلد ألبوم كامل</p>
                        <p class="text-[10px] text-blue-400/50 mt-2 text-center px-8 leading-relaxed">سيتم فحص الصور تلقائياً وضغطها في الخلفية لضمان استقرار جلسة الرفع.</p>
                    </div>

                    <div class="bg-white/5 rounded-[24px] p-6 border border-white/5">
                         <div class="flex items-center justify-between mb-4">
                             <div class="flex flex-col">
                                 <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">إعدادات الأرشفة</span>
                                 <span class="text-[8px] text-gray-600">Archive Settings</span>
                             </div>
                             <div class="flex gap-2">
                                 <span class="px-2 py-0.5 rounded bg-blue-500/10 text-blue-400 text-[8px] font-black uppercase tracking-widest border border-blue-500/10">Fast Compression</span>
                                 <span class="px-2 py-0.5 rounded bg-purple-500/10 text-purple-400 text-[8px] font-black uppercase tracking-widest border border-purple-500/10">Auto Moderation</span>
                             </div>
                         </div>
                         <div class="space-y-2">
                            <label class="text-[10px] uppercase tracking-widest text-gray-400 font-bold italic">مجلد الوجهة (اختياري)</label>
                            <select id="bulk_target_album" class="w-full bg-white/5 border border-white/10 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-500/50 transition-all text-xs">
                                <option value="" class="bg-black">إنشاء ألبوم جديد باسم المجلد</option>
                                @foreach($ownedAlbums as $album)
                                    <option value="{{ $album->id }}" class="bg-black">{{ $album->title }}</option>
                                @endforeach
                            </select>
                         </div>
                    </div>
                </div>

                <div id="bulk-progress-panel" class="hidden glass p-6 rounded-[24px] border-blue-500/20">
                    <div class="flex justify-between items-center mb-3">
                        <div class="flex items-center gap-2">
                             <div class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></div>
                             <span id="bulk-status-msg" class="text-[10px] font-bold text-blue-400 uppercase tracking-widest">جاري التحضير...</span>
                        </div>
                        <span id="bulk-percentage" class="text-xs font-mono font-bold text-blue-400">0%</span>
                    </div>
                    <div class="w-full bg-white/5 rounded-full h-1.5 overflow-hidden">
                        <div id="bulk-progress-bar" class="bg-blue-500 h-full rounded-full transition-all duration-300 shadow-[0_0_10px_rgba(59,130,246,0.5)]" style="width: 0%"></div>
                    </div>
                </div>

                <button id="bulk-folder-submit" disabled onclick="startFolderUpload()" class="w-full bg-blue-600 hover:bg-blue-500 p-5 rounded-[24px] font-bold uppercase tracking-widest text-xs shadow-lg shadow-blue-500/20 hover:scale-[1.02] transition-transform flex items-center justify-center gap-3 disabled:opacity-30 disabled:hover:scale-100 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                    <span id="bulk-btn-text">بدء رفع المجلد</span>
                </button>
            </div>
        </div>

    </div>

</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script>
    let selectedFiles = [];

    async function handleFolderSelection(event) {
        selectedFiles = Array.from(event.target.files);
        const submitBtn = document.getElementById('bulk-folder-submit');
        const btnText = document.getElementById('bulk-btn-text');
        
        // 1. Validation: Check for images
        const imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        const hasImages = selectedFiles.some(file => {
            const ext = file.name.split('.').pop().toLowerCase();
            return imageExtensions.includes(ext);
        });

        if (!hasImages && selectedFiles.length > 0) {
            Swal.fire({
                title: '<span class="text-red-400 font-bold">مجلد غير صالح</span>',
                html: '<p class="text-gray-400 text-sm">المجلد المحدد لا يحتوي على أي صور صالحة (JPG, PNG, WEBP). يرجى اختيار مجلد يحتوي على أصول بصرية.</p>',
                icon: 'error',
                background: '#0a0a0c',
                customClass: {
                    popup: 'glass rounded-[32px] border border-white/10'
                },
                confirmButtonColor: '#3b82f6'
            });
            event.target.value = "";
            submitBtn.disabled = true;
            btnText.innerText = 'بدء رفع المجلد';
            return;
        }

        if (selectedFiles.length > 0) {
            submitBtn.disabled = false;
            btnText.innerHTML = `رفع الألبوم (${selectedFiles.length} ملفاً)`;
            
            // Visual feedback for dropzone
            document.getElementById('bulk-folder-dropzone').classList.add('border-blue-500', 'bg-blue-500/10');
        }
    }

    async function startFolderUpload() {
        const submitBtn = document.getElementById('bulk-folder-submit');
        const progressPanel = document.getElementById('bulk-progress-panel');
        const progressBar = document.getElementById('bulk-progress-bar');
        const statusMsg = document.getElementById('bulk-status-msg');
        const percentageText = document.getElementById('bulk-percentage');
        const albumId = document.getElementById('bulk_target_album').value;

        submitBtn.disabled = true;
        progressPanel.classList.remove('hidden');
        statusMsg.innerText = 'جاري التجهيز والأرشفة...';

        try {
            // 2. Client-Side ZIP (to use existing robust backend)
            const zip = new JSZip();
            selectedFiles.forEach(file => {
                zip.file(file.webkitRelativePath || file.name, file);
            });

            const content = await zip.generateAsync({type:"blob"}, (metadata) => {
                let p = Math.round(metadata.percent);
                progressBar.style.width = `${p}%`;
                percentageText.innerText = `${p}%`;
            });

            statusMsg.innerText = 'جاري مزامنة الأصول...';
            progressBar.style.width = '0%';
            percentageText.innerText = '0%';

            const formData = new FormData();
            formData.append('archive', content, 'folder_upload.zip');
            formData.append('_token', '{{ csrf_token() }}');
            
            // Capture folder name from the first file's path for automatic album naming
            if (selectedFiles.length > 0 && selectedFiles[0].webkitRelativePath) {
                const folderName = selectedFiles[0].webkitRelativePath.split('/')[0];
                formData.append('album_name', folderName);
            }

            if(albumId) formData.append('album_id', albumId);

            const response = await fetch('/api/upload/album', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) throw new Error('فشل الرفع للسيرفر');

            const result = await response.json();
            trackBulkProgress(result.job_id);

        } catch (error) {
            console.error(error);
            Swal.fire('خطأ حرج', 'حدث فشل في نظام الأرشفة أو الرفع. يرجى التأكد من استقرار الاتصال.', 'error');
            submitBtn.disabled = false;
            progressPanel.classList.add('hidden');
        }
    }

    async function trackBulkProgress(jobId) {
        const progressBar = document.getElementById('bulk-progress-bar');
        const statusMsg = document.getElementById('bulk-status-msg');
        const percentageText = document.getElementById('bulk-percentage');

        const interval = setInterval(async () => {
            try {
                const response = await fetch(`/api/upload/progress/${jobId}`);
                if (!response.ok) return;
                
                const data = await response.json();
                
                let percent = data.percentage || 0;
                progressBar.style.width = `${percent}%`;
                percentageText.innerText = `${percent}%`;
                
                if (data.status === 'extracting') {
                    statusMsg.innerText = 'جاري المعالجة السحابية والفلترة الذكية...';
                } else {
                    statusMsg.innerHTML = `النجاح: <span class="text-green-400">${data.processed_items}</span> | المرفوض (ذكاء اصطناعي): <span class="text-red-400">${data.rejected_items || 0}</span> | فشل فني: ${data.failed_items}`;
                }

                if (data.status === 'completed') {
                    clearInterval(interval);
                    statusMsg.innerText = 'تمت مزامنة الألبوم بنجاح!';
                    progressBar.classList.replace('bg-blue-500', 'bg-green-500');
                    
                    Swal.fire({
                        title: 'تم الرفع بنجاح ✅',
                        text: 'تمت معالجة كافة الأصول ونقلها لخزنتك الخاصة.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    setTimeout(() => location.href = "{{ route('images.index') }}", 2200);
                }
            } catch (e) {}
        }, 1500);
    }
</script>
@endpush
@endsection
