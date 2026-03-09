@extends('layouts.premium')

@section('title', 'Profile Settings')

@section('content')
<div class="space-y-8" dir="rtl">
    
    <!-- Profile Header -->
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">إعدادات <span class="accent-text-gradient">المستودع</span></h2>
            <p class="text-gray-400 mt-1">تخصيص بياناتك الشخصية وتفضيلات معالجة الصور.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Navigation sidebar (In-page) -->
        <aside class="lg:col-span-3 space-y-2">
            <div class="glass p-2 rounded-2xl sticky top-8">
                <a href="#info" class="flex items-center gap-3 p-3 px-4 rounded-xl text-purple-400 bg-purple-500/10 font-bold border-r-2 border-purple-500 transition-all">
                    <span>بيانات الحساب</span>
                </a>
                <a href="#photography" class="flex items-center gap-3 p-3 px-4 rounded-xl text-gray-400 hover:bg-white/5 hover:text-white transition-all">
                    <span>إعدادات التصوير</span>
                </a>
                <a href="#password" class="flex items-center gap-3 p-3 px-4 rounded-xl text-gray-400 hover:bg-white/5 hover:text-white transition-all">
                    <span>كلمة المرور</span>
                </a>
                <a href="#delete" class="flex items-center gap-3 p-3 px-4 rounded-xl text-red-400 hover:bg-red-500/10 transition-all">
                    <span>منطقة الخطر</span>
                </a>
            </div>
        </aside>

        <!-- Main Settings Area -->
        <main class="lg:col-span-9 space-y-8">
            
            <!-- User Info -->
            <div id="info" class="glass rounded-[40px] overflow-hidden">
                <div class="p-8 border-b border-white/5 bg-white/5 flex items-center gap-6">
                    <div class="w-20 h-20 rounded-full border-4 border-purple-500/30 p-1">
                        <img src="{{ auth()->user()->avatar }}" class="w-full h-full object-cover rounded-full">
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">{{ auth()->user()->name }}</h3>
                        <p class="text-gray-500 text-sm font-mono">{{ auth()->user()->email }}</p>
                    </div>
                </div>
                <div class="p-8">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <!-- Account Settings Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
                <div>
                    <h1 style="font-size: 28px; font-weight: 800; color: #ffffff; margin-bottom: 8px;">إعدادات الحساب</h1>
                    <p style="color: #8a8fb0;">تخصيص هويتك الرقمية وتفضيلات التصوير</p>
                </div>
            </div>

            <!-- Avatar Section (Premium Glass) -->
            <div class="glass-card" style="padding: 32px; margin-bottom: 32px; display: flex; align-items: center; gap: 32px; background: linear-gradient(135deg, rgba(127, 156, 245, 0.1) 0%, rgba(135, 94, 245, 0.05) 100%); border: 1px solid rgba(127, 156, 245, 0.2);">
                <div style="position: relative;">
                    <img id="avatar-preview" src="{{ $user->avatar }}" alt="{{ $user->name }}" 
                         style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #7f9cf5; box-shadow: 0 0 20px rgba(127, 156, 245, 0.3);">
                    <label for="avatar-input" style="position: absolute; bottom: 0; right: 0; background: #7f9cf5; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid #0f121d; transition: all 0.3s ease;">
                        <span style="font-size: 18px;">📷</span>
                    </label>
                </div>
                <div style="flex: 1;">
                    <h3 style="font-size: 18px; font-weight: 700; color: #ffffff; margin-bottom: 8px;">الصورة الشخصية</h3>
                    <p style="font-size: 13px; color: #8a8fb0; margin-bottom: 16px;">ارفع صورة مربعة عالية الدقة. سيتم تحسينها تلقائياً لتناسب ملفك الشخصي.</p>
                    
                    <form id="avatar-form" action="{{ route('profile.avatar.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="file" id="avatar-input" name="profile_picture" accept="image/*" style="display: none;" onchange="handleAvatarSelection(this)">
                        <button type="button" onclick="document.getElementById('avatar-input').click()" class="btn-primary" style="padding: 10px 20px; font-size: 13px;">
                            اختيار صورة جديدة
                        </button>
                        <button id="avatar-save-btn" type="submit" class="btn-primary" style="display: none; padding: 10px 20px; font-size: 13px; background: #10b981; border-color: #10b981; margin-top: 8px;">
                            <span id="avatar-btn-text">حفظ التغييرات</span>
                        </button>
                    </form>
                </div>
            </div>

            <script>
                function handleAvatarSelection(input) {
                    if (input.files && input.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            document.getElementById('avatar-preview').src = e.target.result;
                            document.getElementById('avatar-save-btn').style.display = 'inline-block';
                        }
                        reader.readAsDataURL(input.files[0]);
                    }
                }

                document.getElementById('avatar-form').addEventListener('submit', function() {
                    const btn = document.getElementById('avatar-save-btn');
                    btn.disabled = true;
                    document.getElementById('avatar-btn-text').innerText = 'جاري المعالجة...';
                });
            </script>

            <!-- Photography Settings (NEW) -->
            <div id="photography" class="glass rounded-[40px] overflow-hidden">
                <div class="p-8 border-b border-white/5 flex justify-between items-center">
                    <div>
                        <h3 class="text-xl font-bold">تفضيلات المعالجة الذكية</h3>
                        <p class="text-gray-500 text-xs mt-1">كيف يتعامل النظام مع صورك الاحترافية تلقائياً.</p>
                    </div>
                    <div class="p-3 rounded-2xl bg-purple-500/10 text-purple-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg>
                    </div>
                </div>
                <div class="p-8 space-y-6">
                    <form method="post" action="{{ route('profile.photography.update') }}" class="space-y-8">
                        @csrf
                        @method('put')

                        <!-- Watermark Toggle -->
                        <div class="flex items-center justify-between p-6 rounded-[30px] bg-white/5 hover:bg-white/10 transition-all border border-white/5 group">
                            <div>
                                <p class="text-lg font-bold">تفعيل العلامة المائية الديناميكية</p>
                                <p class="text-sm text-gray-500">حماية الصور عند التحميل بواسطة الزوار عبر رابط آمن.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="dynamic_watermark" value="1" class="sr-only peer" {{ auth()->user()->dynamic_watermark ? 'checked' : '' }}>
                                <div class="w-14 h-7 bg-white/5 border border-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-purple-500 shadow-inner"></div>
                            </label>
                        </div>

                        <!-- Orientation Toggle (Persistent preference) -->
                        <div class="flex items-center justify-between p-6 rounded-[30px] bg-white/5 hover:bg-white/10 transition-all border border-white/5">
                            <div>
                                <p class="text-lg font-bold">تصحيح الاتجاه تلقائياً (EXIF)</p>
                                <p class="text-sm text-gray-500">تدوير الصور بشكل قائم دائماً اعتماداً على مستشعر الجاذبية.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="auto_orient_default" value="1" class="sr-only peer" {{ auth()->user()->auto_orient_default ? 'checked' : '' }}>
                                <div class="w-14 h-7 bg-white/5 border border-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-purple-500 shadow-inner"></div>
                            </label>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="accent-gradient p-3 px-8 rounded-2xl font-bold shadow-lg shadow-purple-500/20 hover:scale-105 transition-transform">حفظ التفضيلات</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Password -->
            <div id="password" class="glass rounded-[40px] overflow-hidden border-blue-500/10 border">
                <div class="p-8 border-b border-white/5">
                    <h3 class="text-xl font-bold text-blue-400">تغيير السر</h3>
                </div>
                <div class="p-8">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <!-- Delete -->
            <div id="delete" class="glass rounded-[40px] overflow-hidden border-red-500/10 border">
                <div class="p-8 border-b border-white/5">
                    <h3 class="text-xl font-bold text-red-500">حذف الحساب</h3>
                </div>
                <div class="p-8">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>

        </main>
    </div>
</div>

<style>
    /* Styling for Breeze partials to match premium UI */
    .space-y-6 label { color: #8891aa !important; font-weight: 600; font-size: 13px; }
    .space-y-6 input:not([type="checkbox"]) { 
        background: rgba(255, 255, 255, 0.05) !important; 
        border: 1px solid rgba(255, 255, 255, 0.1) !important; 
        border-radius: 15px !important;
        color: white !important;
        padding: 12px !important;
    }
    .space-y-6 input:focus { border-color: #a855f7 !important; border-width: 2px !important; outline: none !important; }
    .space-y-6 button[type="submit"]:not(.accent-gradient) {
        background: rgba(255,255,255,0.05) !important;
        color: white !important;
        border-radius: 12px !important;
        border: 1px solid rgba(255,255,255,0.1) !important;
        font-weight: 700 !important;
        padding: 10px 20px !important;
    }
</style>
@endsection