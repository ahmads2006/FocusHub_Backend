@extends('layouts.premium')

@section('title', 'تقديم طلب مراجعة')

@section('content')
<div class="max-w-4xl mx-auto space-y-8" dir="rtl">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">إنشاء <span class="accent-text-gradient">طلب مراجعة</span></h2>
            <p class="text-gray-400 mt-1">يُرجى توضيح سبب اعتقادك بأن قرار الحظر كان خاطئاً.</p>
        </div>
        <a href="{{ route('images.index') }}" class="p-3 bg-white/5 hover:bg-white/10 rounded-2xl border border-white/10 transition-all font-bold uppercase tracking-widest text-[10px] text-gray-400">
            العودة للمكتبة
        </a>
    </div>

    @if(session('error'))
        <div class="bg-red-500/10 border border-red-500/20 text-red-500 font-bold p-4 rounded-2xl">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        
        <!-- Image Preview Panel -->
        <div class="glass p-6 rounded-[40px] border border-white/10 flex flex-col items-center justify-center text-center space-y-4 relative overflow-hidden group">
            <h3 class="text-xs font-bold uppercase tracking-widest text-purple-400 mb-2 w-full text-right">الصورة المحظورة</h3>
            <div class="w-full relative rounded-3xl overflow-hidden border border-white/10 aspect-video bg-black/50 flex items-center justify-center" oncontextmenu="return false;">
                <img src="{{ $image->url }}" class="w-full h-full object-contain blur-lg group-hover:blur-md transition-all duration-500 select-none">
                <div class="absolute inset-0 bg-black/40 flex flex-col items-center justify-center pointer-events-none">
                    <svg class="w-12 h-12 text-red-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span class="text-white font-bold text-sm tracking-widest uppercase">محتوى محظور</span>
                </div>
            </div>
            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">{{ $image->title ?? 'بدون عنوان' }}</p>
        </div>

        <!-- Appeal Form Panel -->
        <div class="glass p-8 rounded-[40px] border border-purple-500/20 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 right-0 p-8 opacity-5 pointer-events-none">
                <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
            </div>
            
            <form action="{{ route('images.appeal', $image) }}" method="POST" class="space-y-6 relative z-10">
                @csrf
                
                <div class="space-y-2">
                    <label class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">الاسم الكامل (للتواصل)</label>
                    <input type="text" name="contact_name" value="{{ old('contact_name', auth()->user()->name) }}" required class="w-full bg-black/20 border border-white/10 rounded-2xl p-4 text-white focus:border-purple-500/50 focus:ring-1 focus:ring-purple-500/50 outline-none transition-all placeholder-gray-600">
                    @error('contact_name') <p class="text-red-400 text-[10px] font-bold mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] uppercase tracking-widest text-gray-400 font-bold">البريد الإلكتروني للرد</label>
                    <input type="email" name="contact_email" value="{{ old('contact_email', auth()->user()->email) }}" required class="w-full bg-black/20 border border-white/10 rounded-2xl p-4 text-white focus:border-purple-500/50 focus:ring-1 focus:ring-purple-500/50 outline-none transition-all placeholder-gray-600" dir="ltr">
                    @error('contact_email') <p class="text-red-400 text-[10px] font-bold mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] uppercase tracking-widest text-purple-400 font-bold">مبررات الطلب (لماذا تعتقد أن الحظر خاطئ؟)</label>
                    <textarea name="reason" required minlength="10" rows="5" class="w-full bg-black/20 border border-purple-500/30 rounded-2xl p-4 text-white focus:border-purple-500 focus:ring-1 focus:ring-purple-500 outline-none transition-all placeholder-gray-600" placeholder="أعتقد أن الصورة لا تخالف سياسات المحتوى لأن...">{{ old('reason') }}</textarea>
                    @error('reason') <p class="text-red-400 text-[10px] font-bold mt-1">{{ $message }}</p> @enderror
                    <p class="text-[9px] text-gray-500 uppercase tracking-widest mt-2">يرجى كتابة رسالة واضحة بحد أدنى 10 أحرف.</p>
                </div>
                
                <div class="pt-4">
                    <button type="submit" class="w-full accent-gradient hover:scale-[1.02] text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-purple-500/20 uppercase tracking-widest text-xs flex items-center justify-center gap-2">
                        <span>إرسال طلب المراجعة للإدارة</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    </button>
                    <p class="text-center text-[9px] text-gray-500 font-bold mt-4 uppercase tracking-widest">ملاحظة: يحق لك تقديم طلب مراجعة واحد فقط لكل صورة محظورة.</p>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
