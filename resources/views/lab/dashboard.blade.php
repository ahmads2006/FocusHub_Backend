@extends('layouts.premium')

@section('title', 'Performance Diagnostic Tool')

@section('content')
<div x-data="dashboard()" x-cloak class="h-full flex flex-col gap-8">
    
    <!-- Title Section -->
    <div class="flex justify-between items-end">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">Performance <span class="accent-text-gradient">Diagnostic Tool</span></h2>
            <p class="text-gray-400 mt-1">Simulate and measure real-time processing, CDN sync, and AI analysis speeds.</p>
        </div>
        <div class="flex items-center gap-4 bg-white/5 p-2 rounded-full px-4 border border-white/10">
            <div class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></div>
            <span class="text-xs font-semibold uppercase tracking-widest text-gray-300">Live Tracing Active</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 flex-1 min-h-0">
        
        <!-- Left Panel: Configuration -->
        <aside class="lg:col-span-4 space-y-6 flex flex-col overflow-y-auto pr-2">
            <div class="glass p-6 rounded-3xl">
                <h3 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-6 flex items-center gap-2">
                    <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    Diagnostic Triggers
                </h3>
                
                <div class="space-y-4">
                    <!-- Feature Toggles -->
                    <template x-for="(val, key) in features">
                        <div class="flex items-center justify-between p-4 rounded-2xl bg-white/5 hover:bg-white/10 transition-colors group">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-purple-500/10 text-purple-400 group-hover:bg-purple-500 group-hover:text-white transition-all">
                                    <svg x-show="key === 'local_io'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
                                    <svg x-show="key === 'cdn_sync'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>
                                    <svg x-show="key === 'database'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                                    <svg x-show="key === 'ai_analysis'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold capitalize" x-text="key.replace('_', ' ')"></p>
                                    <p class="text-[10px] text-gray-500 uppercase font-mono" x-show="key === 'local_io'">Local File System & Disks</p>
                                    <p class="text-[10px] text-gray-500 uppercase font-mono" x-show="key === 'cdn_sync'">ImageKit API Upload</p>
                                    <p class="text-[10px] text-gray-500 uppercase font-mono" x-show="key === 'database'">Record Creation Overhead</p>
                                    <p class="text-[10px] text-gray-500 uppercase font-mono" x-show="key === 'ai_analysis'">Content Safety & Labeling</p>
                                </div>
                            </div>
                            <button @click="features[key] = !features[key]" 
                                    :class="features[key] ? 'accent-gradient shadow-[0_0_10px_rgba(168,85,247,0.4)]' : 'bg-white/5'" 
                                    class="w-12 h-6 rounded-full relative transition-all duration-500 border border-white/10 opacity-70 cursor-not-allowed" disabled>
                                <span :class="features[key] ? 'translate-x-6' : 'translate-x-1'" class="absolute top-1 left-0 w-4 h-4 bg-white rounded-full transition-all duration-500 shadow-md"></span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Trace Execution Logs -->
            <div class="glass p-6 rounded-3xl flex-1 flex flex-col min-h-0">
                <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-4 font-mono">Trace Execution</h3>
                <div class="flex-1 overflow-y-auto space-y-3 font-mono text-[10px] pr-2">
                    <template x-for="log in logs" :key="log.time">
                        <div class="flex gap-3 border-l-2 pl-3 py-1 transition-all" :class="log.isError ? 'border-red-500/50 hover:border-red-500 text-red-400' : 'border-white/5 hover:border-purple-500/30'">
                            <span class="opacity-60" :class="log.isError ? 'text-red-400' : 'text-purple-400'" x-text="log.time"></span>
                            <span :class="log.isError ? 'text-red-300' : 'text-gray-300'" x-text="log.message"></span>
                        </div>
                    </template>
                </div>
            </div>
        </aside>

        <!-- Right Content Area: Upload & Results -->
        <main class="lg:col-span-8 flex flex-col gap-8 min-h-0">
            
            <!-- Comparison Container -->
            <div x-show="fileLoaded" x-transition.opacity.duration.500ms class="flex-1 flex flex-col gap-8 min-h-0">
                
                <!-- Performance Breakdown & Image View -->
                <div class="glass rounded-[40px] overflow-hidden flex-1 relative flex flex-col lg:flex-row">
                    <!-- Image Preview -->
                    <div class="lg:w-5/12 relative border-r border-white/5 flex items-center justify-center bg-black/50">
                        <img :src="original.preview" class="w-full h-full object-contain absolute opacity-60">
                        
                        <div class="absolute inset-0 z-10 flex items-center justify-center bg-black/60 backdrop-blur-sm" x-show="processing">
                            <div class="flex flex-col items-center gap-6">
                                <div class="w-16 h-16 border-4 border-purple-500/10 border-t-purple-500 rounded-full animate-spin"></div>
                                <div class="text-center">
                                    <p class="text-lg font-bold tracking-tight animate-pulse" x-text="currentStage"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Results Overlay -->
                        <div class="absolute bottom-6 left-6 right-6 z-20 space-y-2" x-show="!processing && processed.ai_result">
                            <div class="glass bg-black/60 p-3 rounded-2xl border border-white/10 flex items-center justify-between shadow-2xl">
                                <div>
                                    <p class="text-[10px] text-gray-500 font-bold tracking-widest uppercase mb-1">Total Pipeline Time</p>
                                    <p class="text-2xl font-bold font-mono text-purple-400" x-text="processed.timings?.total_ms + ' ms'"></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] text-gray-500 font-bold tracking-widest uppercase mb-1">Analyzer Used</p>
                                    <p class="text-sm font-bold uppercase tracking-widest" x-text="processed.driver_used"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Metrics Data -->
                    <div class="flex-1 flex flex-col bg-black/20 overflow-y-auto">
                        <div class="p-8 border-b border-white/5 flex justify-between items-center">
                            <div>
                                <h3 class="text-xl font-bold">Diagnostic Trace Results</h3>
                                <p class="text-xs text-gray-400 uppercase tracking-widest mt-1">Millisecond Precision</p>
                            </div>
                            <span class="text-xs px-3 py-1 bg-white/5 rounded-full font-mono text-gray-400" x-text="original.size"></span>
                        </div>

                        <div class="p-8 space-y-6 flex-1">
                            <template x-if="processed.timings">
                                <div class="space-y-4">
                                    <!-- Local IO Metric -->
                                    <div class="flex justify-between items-center group">
                                        <div>
                                            <p class="font-bold text-sm tracking-widest uppercase text-gray-300">Local I/O</p>
                                            <p class="text-[10px] text-gray-500 font-mono mt-1">Storage::put, disk operations</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-mono text-lg font-bold group-hover:text-purple-400 transition-colors" x-text="processed.timings.local_io_ms + ' ms'"></p>
                                        </div>
                                    </div>
                                    <div class="h-px w-full bg-white/5"></div>

                                    <!-- CDN Sync Metric -->
                                    <div class="flex justify-between items-center group">
                                        <div>
                                            <p class="font-bold text-sm tracking-widest uppercase text-gray-300">ImageKit Sync</p>
                                            <p class="text-[10px] text-gray-500 font-mono mt-1">API payload and response roundtrip</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-mono text-lg font-bold group-hover:text-purple-400 transition-colors" x-text="processed.timings.cdn_upload_ms + ' ms'"></p>
                                        </div>
                                    </div>
                                    <div class="h-px w-full bg-white/5"></div>

                                    <!-- DB Metric -->
                                    <div class="flex justify-between items-center group">
                                        <div>
                                            <p class="font-bold text-sm tracking-widest uppercase text-gray-300">Database Record</p>
                                            <p class="text-[10px] text-gray-500 font-mono mt-1">Model creation & relationships</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-mono text-lg font-bold group-hover:text-purple-400 transition-colors" x-text="processed.timings.database_ms + ' ms'"></p>
                                        </div>
                                    </div>
                                    <div class="h-px w-full bg-white/5"></div>

                                    <!-- AI Analysis Metric -->
                                    <div class="flex justify-between items-center group">
                                        <div>
                                            <p class="font-bold text-sm tracking-widest uppercase text-purple-400">AI Safety & Vision Analysis</p>
                                            <p class="text-[10px] text-gray-400 font-mono mt-1">Failover SDK request execution</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-mono text-xl font-bold text-purple-400" x-text="processed.timings.ai_analysis_ms + ' ms'"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <template x-if="!processed.timings && !processing">
                                <div class="text-center py-12 text-gray-500 font-mono text-xs uppercase">
                                    Awaiting Trace Execution...
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- AI Extraction Dashboard -->
                <div class="glass p-8 rounded-[40px] flex items-center justify-between" x-show="processed.ai_result">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-2 font-mono">Vision AI Extraction Data</p>
                        <div class="flex gap-6 mt-4">
                            <!-- Category Badge -->
                            <div class="flex gap-2 items-center bg-white/5 px-4 py-2 rounded-xl border border-white/5">
                                <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Category</p>
                                <p class="font-bold text-white capitalize" x-text="processed.ai_result?.category || 'Unknown'"></p>
                            </div>
                            <!-- Quality Badge -->
                            <div class="flex gap-2 items-center bg-white/5 px-4 py-2 rounded-xl border border-white/5">
                                <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Quality Grade</p>
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full" :class="processed.ai_result?.qualityGrade === 'high_quality' ? 'bg-green-500' : (processed.ai_result?.qualityGrade === 'medium_quality' ? 'bg-yellow-500' : 'bg-red-500')"></div>
                                    <p class="font-bold text-white capitalize whitespace-nowrap" x-text="processed.ai_result?.qualityGrade?.replace('_', ' ')"></p>
                                </div>
                            </div>
                            <!-- NSFW Badge -->
                            <div class="flex gap-2 items-center bg-white/5 px-4 py-2 rounded-xl border border-white/5" x-show="processed.ai_result?.contentSafety?.length > 0">
                                <p class="text-[10px] text-red-400 uppercase tracking-widest font-bold">Safety Trigger</p>
                                <p class="font-bold text-red-300 capitalize" x-text="processed.ai_result?.contentSafety?.join(', ')"></p>
                            </div>
                        </div>
                    </div>
                    
                    <button @click="reset()" class="p-4 rounded-full glass hover:bg-white/10 transition-all text-gray-400 group border border-white/10">
                        <svg class="w-6 h-6 border-2 border-transparent group-hover:-rotate-90 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
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
                             <svg class="w-12 h-12 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                    </div>
                    
                    <h3 class="text-3xl font-bold tracking-tight">Run Performance Trace</h3>
                    <p class="text-gray-500 mt-2 font-medium">Drag & drop or click to upload</p>
                    
                    <div class="mt-8 flex gap-3">
                        <span class="text-[10px] px-3 py-1 glass rounded-full opacity-40 font-mono">MAX 80MB</span>
                        <span class="text-[10px] px-3 py-1 glass rounded-full opacity-40 font-mono">JPG/PNG/WEBP</span>
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
            currentStage: 'Initializing...',
            fileInfo: null,
            features: {
                local_io: true,
                cdn_sync: true,
                database: true,
                ai_analysis: true
            },
            original: {
                size: '0MB',
                dims: '0x0',
                preview: ''
            },
            processed: {
                timings: null,
                ai_result: null,
                driver_used: null,
                preview: ''
            },
            logs: [],
            init() {
                this.addLog('Diagnostic Tool Loaded. Waiting for input.');
            },
            addLog(msg, isError = false) {
                const now = new Date();
                const time = now.getHours().toString().padStart(2, '0') + ':' + 
                             now.getMinutes().toString().padStart(2, '0') + ':' + 
                             now.getSeconds().toString().padStart(2, '0') + '.' + 
                             now.getMilliseconds().toString().padStart(3, '0');
                this.logs.unshift({ time, message: msg, isError });
                if (this.logs.length > 20) this.logs.pop();
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
                this.processed = { timings: null, ai_result: null, driver_used: null, preview: '' };
                this.addLog('Trace cache cleared. Ready.');
            },
            processFile(file) {
                if (!file) return;
                this.fileLoaded = true;
                this.processing = true;
                this.dragOver = false;
                this.fileInfo = file;
                this.processed.timings = null;
                this.processed.ai_result = null;
                
                this.original.size = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
                this.addLog('Upload event triggered: ' + file.name);
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.original.preview = e.target.result;
                    this.runDiagnosticTrace(file);
                };
                reader.readAsDataURL(file);
            },
            async runDiagnosticTrace(file) {
                this.addLog('Initiating Synchronous Trace Execution...');
                this.currentStage = 'Measuring I/O & Network...';
                
                const formData = new FormData();
                formData.append('image', file);

                try {
                    const tStart = performance.now();
                    
                    const response = await fetch('{{ route("lab.process") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: formData
                    });

                    const result = await response.json();
                    const tEnd = performance.now();
                    const browserRoundTrip = Math.round(tEnd - tStart);

                    if (result.success) {
                        this.addLog(`Local I/O Time: ${result.timings.local_io_ms} ms`);
                        this.addLog(`ImageKit Sync Time: ${result.timings.cdn_upload_ms} ms`);
                        this.addLog(`Database Overhead: ${result.timings.database_ms} ms`);
                        this.addLog(`AI Failover Engine: ${result.timings.ai_analysis_ms} ms (${result.driver_used})`);
                        this.addLog(`Total Server Execution: ${result.timings.total_ms} ms`);
                        this.addLog(`Browser HTTP Latency: ${browserRoundTrip} ms`);
                        
                        this.processing = false;
                        this.processed.timings = result.timings;
                        this.processed.ai_result = result.ai_result;
                        this.processed.driver_used = result.driver_used;
                        this.processed.preview = result.preview_url;
                        
                        this.addLog('Diagnostic Trace completed successfully.');
                    } else {
                        throw new Error(result.message);
                    }
                } catch (e) {
                    this.processing = false;
                    this.addLog('TRACE FAILURE: ' + e.message, true);
                }
            }
        }
    }
</script>
@endsection
