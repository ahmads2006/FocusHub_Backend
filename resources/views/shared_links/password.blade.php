<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Secure Access | FocusHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #0a0a0c; color: #e1e1e6; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .accent-gradient { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); }
    </style>
</head>
<body class="antialiased min-h-screen bg-[#0a0a0c] flex items-center justify-center p-6" dir="rtl">
    
    <div class="fixed inset-0 pointer-events-none">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full bg-purple-600/5 blur-[120px]"></div>
    </div>

    <div class="relative w-full max-w-md">
        <div class="glass rounded-[40px] border border-white/10 overflow-hidden shadow-2xl p-10 sm:p-12 text-center space-y-8">
            <div class="inline-flex p-5 rounded-3xl bg-purple-500/10 text-purple-400 mb-2">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-white">محتوى محمي</h1>
                <p class="text-sm text-gray-400 mt-2 leading-relaxed">هذا الرابط مـؤمن بكلمة مـرور. يرجى إدخال مـفتاح الوصول للمـتابعة.</p>
            </div>

            <form method="POST" action="{{ route('shared_link.verify', $link->token) }}" class="space-y-6">
                @csrf
                <div class="space-y-2">
                    <input 
                        type="password" 
                        name="password" 
                        placeholder="أدخل كلمة المرور هنا..." 
                        required 
                        autofocus
                        class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-center text-sm outline-none focus:border-purple-500/50 transition-all font-mono"
                    >
                    @error('password')
                        <p class="text-red-400 text-[10px] font-bold uppercase tracking-widest mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <button 
                    type="submit" 
                    class="w-full accent-gradient p-4 rounded-2xl font-bold uppercase tracking-widest text-[10px] shadow-lg shadow-purple-500/20 hover:scale-[1.02] transition-all"
                >
                    فك التشفير والدخول
                </button>
            </form>

            <div class="pt-6 border-t border-white/5 flex flex-col items-center gap-2">
                <p class="text-[9px] text-gray-600 font-bold uppercase tracking-[0.2em]">Protected Asset Protocol</p>
                <p class="text-[10px] text-gray-500">Focus<span class="text-purple-500">Hub</span> Secure Delivery</p>
            </div>
        </div>
    </div>
</body>
</html>
