@extends('layouts.premium')

@section('title', 'Laboratory Dashboard')

@section('content')
<div x-data="dashboard()" x-cloak class="h-full flex flex-col gap-8">
    
    <!-- Title Section -->
    <div class="flex justify-between items-end">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">Image Processing <span class="accent-text-gradient">Lab</span></h2>
            <p class="text-gray-400 mt-1">Simulate and verify advanced optimization pipelines.</p>
        </div>
        <div class="flex items-center gap-4 bg-white/5 p-2 rounded-full px-4 border border-white/10">
            <div class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></div>
            <span class="text-xs font-semibold uppercase tracking-widest text-gray-300">Imagick Active</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 flex-1 min-h-0">
        
        <!-- Left Panel: Configuration -->
        <aside class="lg:col-span-4 space-y-6 flex flex-col overflow-y-auto pr-2">
            <div class="glass p-6 rounded-3xl">
                <h3 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-6 flex items-center gap-2">
                    <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z"></path></svg>
                    Pipeline Controls
                </h3>
                
                <div class="space-y-4">
                    <!-- Feature Toggles -->
                    <template x-for="(val, key) in features">
                        <div class="flex items-center justify-between p-4 rounded-2xl bg-white/5 hover:bg-white/10 transition-colors group">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-purple-500/10 text-purple-400 group-hover:bg-purple-500 group-hover:text-white transition-all">
                                    <svg x-show="key === 'compression'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                    <svg x-show="key === 'watermark'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    <svg x-show="key === 'orientation'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                    <svg x-show="key === 'multisize'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold capitalize" x-text="key.replace('_', ' ')"></p>
                                    <p class="text-[10px] text-gray-500 uppercase font-mono" x-show="key === 'compression'">MozJPEG 4:2:0</p>
                                    <p class="text-[10px] text-gray-500 uppercase font-mono" x-show="key === 'watermark'">© Overlay Protection</p>
                                    <p class="text-[10px] text-gray-500 uppercase font-mono" x-show="key === 'orientation'">EXIF Gravity Fix</p>
                                    <p class="text-[10px] text-gray-500 uppercase font-mono" x-show="key === 'multisize'">Variant Generation</p>
                                </div>
                            </div>
                            <button @click="features[key] = !features[key]" 
                                    :class="features[key] ? 'accent-gradient shadow-[0_0_10px_rgba(168,85,247,0.4)]' : 'bg-white/5'" 
                                    class="w-12 h-6 rounded-full relative transition-all duration-500 border border-white/10">
                                <span :class="features[key] ? 'translate-x-6' : 'translate-x-1'" class="absolute top-1 left-0 w-4 h-4 bg-white rounded-full transition-all duration-500 shadow-md"></span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Log Activity -->
            <div class="glass p-6 rounded-3xl flex-1 flex flex-col min-h-0">
                <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-4 font-mono">Real-time Analysis</h3>
                <div class="flex-1 overflow-y-auto space-y-3 font-mono text-[10px] pr-2">
                    <template x-for="log in logs" :key="log.time">
                        <div class="flex gap-3 border-l-2 border-white/5 pl-3 py-1 hover:border-purple-500/30 transition-all">
                            <span class="text-purple-400 opacity-60" x-text="log.time"></span>
                            <span class="text-gray-300" x-text="log.message"></span>
                        </div>
                    </template>
                </div>
            </div>
        </aside>

        <!-- Right Content Area: Upload & Results -->
        <main class="lg:col-span-8 flex flex-col gap-8 min-h-0">
            
            <!-- Comparison Container -->
            <div x-show="fileLoaded" x-transition.opacity.duration.500ms class="flex-1 flex flex-col gap-8 min-h-0">
                
                <!-- Main Split Preview -->
                <div class="glass rounded-[40px] overflow-hidden flex-1 relative flex flex-col">
                    <div class="p-6 border-b border-white/5 flex justify-between items-center bg-black/20">
                        <div class="flex gap-4 items-baseline">
                            <h3 class="text-lg font-bold">Visual Comparison</h3>
                            <p class="text-[10px] text-gray-500 uppercase tracking-tighter" x-text="'File: ' + (fileInfo?.name || 'Untitled')"></p>
                        </div>
                        <div class="flex items-center gap-2">
                             <span class="text-xs px-3 py-1 bg-white/5 rounded-full font-mono text-gray-400" x-text="original.dims"></span>
                             <div class="h-4 w-[1px] bg-white/10 mx-2"></div>
                             <span class="text-xs px-3 py-1 bg-green-500/10 text-green-400 rounded-full font-bold font-mono" x-text="'-' + processed.saved + '% Saved'"></span>
                        </div>
                    </div>

                    <div class="flex-1 flex min-h-0 relative">
                        <!-- Before -->
                        <div class="flex-1 relative group overflow-hidden border-r border-white/5">
                            <img :src="original.preview" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
                            <div class="absolute bottom-6 left-8">
                                <p class="text-xs font-bold uppercase tracking-widest opacity-40 mb-1">Raw Source</p>
                                <p class="text-2xl font-bold" x-text="original.size"></p>
                            </div>
                        </div>
                        <!-- After -->
                        <div class="flex-1 relative group overflow-hidden">
                            <div class="absolute inset-0 z-10 flex items-center justify-center bg-black/60 backdrop-blur-sm" x-show="processing">
                                <div class="flex flex-col items-center gap-6">
                                    <div class="w-16 h-16 border-4 border-purple-500/10 border-t-purple-500 rounded-full animate-spin"></div>
                                    <div class="text-center">
                                        <p class="text-lg font-bold tracking-tight animate-pulse">Processing Asset...</p>
                                        <p class="text-xs text-gray-500 font-mono mt-1">Applying Imagick Filters</p>
                                    </div>
                                </div>
                            </div>
                            <div class="relative h-full bg-black flex items-center justify-center">
                                <img :src="processed.preview || original.preview" class="w-full h-full object-cover brightness-105 contrast-105">
                                <!-- Watermark Layer -->
                                <div x-show="features.watermark" class="absolute bottom-6 right-8 bg-black/40 backdrop-blur-xl border border-white/10 p-2 px-4 rounded-xl text-[10px] text-white/40 tracking-widest uppercase font-bold">
                                    © {{ auth()->user()->name }} | FocusHub | 2026
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-t from-purple-900/40 to-transparent"></div>
                                <div class="absolute bottom-6 left-8">
                                    <p class="text-xs font-bold uppercase tracking-widest text-purple-400 mb-1">Optimized WebP</p>
                                    <p class="text-2xl font-bold" x-text="processed.size"></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Divider Badge -->
                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <div class="glass p-1 px-4 rounded-full text-[10px] font-bold tracking-widest bg-black/80 uppercase">Analysis View</div>
                        </div>
                    </div>
                </div>

                <!-- Asset Tree Area -->
                <div class="glass p-8 rounded-[40px] flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-2 font-mono">Generated Asset Tree</p>
                        <h4 class="text-xl font-bold">Multi-tier Delivery Check</h4>
                    </div>
                    
                    <div class="flex items-center gap-12">
                        <!-- Variant Icon -->
                        <div class="flex flex-col items-center gap-3 group cursor-pointer">
                            <div class="w-14 h-14 rounded-2xl overflow-hidden glass border-2 border-white/10 group-hover:border-purple-500/50 transition-all p-1">
                                <img :src="original.preview" class="w-full h-full object-cover rounded-xl grayscale group-hover:grayscale-0 transition-all">
                            </div>
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Avatar (150px)</p>
                        </div>
                        <!-- Variant Medium -->
                        <div class="flex flex-col items-center gap-3 group cursor-pointer">
                            <div class="w-48 h-14 rounded-2xl glass border border-white/10 flex items-center px-4 gap-4 overflow-hidden relative group-hover:bg-white/5 transition-all">
                                <div class="w-8 h-8 rounded-lg overflow-hidden flex-shrink-0">
                                    <div class="absolute inset-0 bg-purple-500/10 border-r border-white/5"></div>
                                    <img :src="original.preview" class="w-full h-full object-cover relative z-10">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-bold truncate tracking-tight">gallery_medium.webp</p>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[8px] font-bold text-gray-600 uppercase font-mono tracking-tighter">800px</span>
                                        <div class="w-1 h-1 rounded-full bg-green-500"></div>
                                        <span class="text-[8px] font-bold text-green-500 font-mono uppercase tracking-tighter">~42KB</span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Gallery Optimized</p>
                        </div>
                    </div>

                    <button @click="reset()" class="p-4 rounded-full glass hover:bg-red-500/10 hover:text-red-500 transition-all text-gray-400 group">
                        <svg class="w-6 h-6 border-2 border-transparent group-hover:rotate-90 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </button>
                </div>

            </div>

            <!-- Empty State / Drag Zone -->
            <div x-show="!fileLoaded" class="flex-1 flex items-center justify-center">
                <div 
                    @dragover.prevent="dragOver = true" 
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="handleDrop($event)"
                    class="w-full max-w-2xl h-96 relative group border-2 border-dashed rounded-[60px] flex flex-col items-center justify-center transition-all duration-700"
                    :class="dragOver ? 'border-purple-500 bg-purple-500/5 glow scale-[1.02]' : 'border-white/10 hover:border-white/20'"
                >
                    <input type="file" @change="handleFile($event)" class="absolute inset-0 opacity-0 cursor-pointer" id="fileUpload">
                    
                    <div class="relative mb-8">
                        <div class="absolute inset-0 blur-3xl bg-purple-500/20 rounded-full animate-pulse"></div>
                        <div class="relative p-8 rounded-full bg-black border border-white/10 group-hover:scale-110 transition-transform duration-700">
                             <svg class="w-12 h-12 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        </div>
                    </div>
                    
                    <h3 class="text-3xl font-bold tracking-tight">Drag & Drop Image</h3>
                    <p class="text-gray-500 mt-2 font-medium">Or click to browse your workstation</p>
                    
                    <div class="mt-8 flex gap-3">
                        <span class="text-[10px] px-3 py-1 glass rounded-full opacity-40 font-mono">MAX 80MB</span>
                        <span class="text-[10px] px-3 py-1 glass rounded-full opacity-40 font-mono">WEBP/JPEG/TIFF</span>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script>
    function dashboard() {
        return {
            dragOver: false,
            fileLoaded: false,
            processing: false,
            fileInfo: null,
            features: {
                compression: true,
                watermark: true,
                orientation: true,
                multisize: true
            },
            original: {
                size: '0MB',
                dims: '0x0',
                preview: ''
            },
            processed: {
                size: '0MB',
                saved: '0',
                preview: ''
            },
            logs: [
                { time: '00:00:01', message: 'Hyper-Processing Core Standby...' },
                { time: '00:00:02', message: 'Imagick Driver V7.1 Registered.' }
            ],
            addLog(msg) {
                const now = new Date();
                const time = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0') + ':' + now.getSeconds().toString().padStart(2, '0');
                this.logs.unshift({ time, message: msg });
                if (this.logs.length > 8) this.logs.pop();
            },
            handleDrop(e) {
                this.processFile(e.dataTransfer.files[0]);
            },
            handleFile(e) {
                this.processFile(e.target.files[0]);
            },
            reset() {
                this.fileLoaded = false;
                this.fileInfo = null;
                this.addLog('Cache cleared. Lab reset.');
            },
            processFile(file) {
                if (!file) return;
                this.fileLoaded = true;
                this.processing = true;
                this.dragOver = false;
                this.fileInfo = file;
                
                this.original.size = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
                this.addLog('Loading binary: ' + file.name);
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.original.preview = e.target.result;
                    const img = new Image();
                    img.onload = () => {
                        this.original.dims = img.width + 'x' + img.height;
                        this.runSimulation(file);
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            },
            async runSimulation(file) {
                this.addLog('Validating Security Signature...');
                
                const formData = new FormData();
                formData.append('image', file);
                formData.append('compression', this.features.compression ? 1 : 0);
                formData.append('watermark', this.features.watermark ? 1 : 0);
                formData.append('orientation', this.features.orientation ? 1 : 0);
                formData.append('multisize', this.features.multisize ? 1 : 0);

                try {
                    const response = await fetch('{{ route("lab.process") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.addLog('Correcting EXIF Orientation (Imagick)...');
                        this.addLog('Applying Smart Compression (MozJPEG)...');
                        this.addLog('Injecting Rights Protection Layer...');
                        
                        this.processing = false;
                        this.processed.size = (result.processed_size / (1024 * 1024)).toFixed(2) + ' MB';
                        this.processed.saved = Math.round((1 - result.processed_size / result.original_size) * 100);
                        this.processed.preview = result.preview_url;
                        
                        this.addLog('Pipeline successfully executed. Assets Ready.');
                    } else {
                        throw new Error(result.message);
                    }
                } catch (e) {
                    this.processing = false;
                    this.addLog('ERROR: ' + e.message);
                    Swal.fire({
                        title: 'Lab Failure',
                        text: e.message,
                        icon: 'error',
                        confirmButtonColor: '#a855f7'
                    });
                }
            }
        }
    }
</script>
@endsection
