<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>

        <div class="space-y-3 mt-6 pt-6 border-t border-gray-100">
            <!-- Google -->
            <a href="{{ route('auth.social.redirect', 'google') }}" class="flex items-center justify-center gap-3 w-full px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition-colors text-sm font-medium text-gray-700">
                <svg class="w-5 h-5" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Sign up with Google
            </a>

            <!-- Instagram -->
            <a
                href="{{ route('auth.social.redirect', 'instagram') }}"
                class="group relative flex items-center justify-center gap-3 w-full px-4 py-2
                bg-gradient-to-r from-[#833ab4] via-[#fd1d1d] to-[#fcb045]
                rounded-xl shadow-md hover:shadow-lg transition-all duration-300 text-sm font-semibold text-white"
            >
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                </svg>
                Sign up with Instagram
            </a>

            <!-- Adobe -->
       {{-- 1. نضغ الـ CSS الخاص بالحركة المتموجة هنا --}}
<style>
    @keyframes gradientBackground {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    .adobe-btn-animated {
        /* الخلفية المتموجة: أزرق - أحمر - أزرق */
        background: linear-gradient(-45deg, #007bff, #FA0F00, #0056b3);
        background-size: 300% 300%;
        animation: gradientBackground 4s ease infinite; /* سرعة الحركة 4 ثواني */
    }
</style>

{{-- 2. زر Adobe المعدل --}}
<a
    href="{{ route('auth.social.redirect', 'adobe') }}"
    {{-- اضفنا الكلاس 'adobe-btn-animated' للحركة، و 'shadow-blue-500/50' للتوهج الأزرق --}}
    class="adobe-btn-animated flex items-center justify-center gap-3 w-full px-4 py-2 rounded-xl shadow-lg shadow-blue-500/50 hover:shadow-xl hover:shadow-blue-600/60 transition-all duration-300 text-sm font-semibold text-black"
>
    {{-- الأيقونة: تم تغيير اللون ليكون أحمر عبر fill-[#FA0F00] --}}
    <svg class="w-5 h-5 fill-[#FA0F00]" viewBox="0 0 24 24">
        <path d="M14.582 3H24v18h-9.418L12 15.652zM9.418 3H0v18h9.418L12 15.652zM12 9.157L16.231 18h-2.314l-1.325-2.793h-1.184V18H9.091V6h3.401c1.506 0 2.391.821 2.391 1.954 0 .822-.613 1.203-1.481 1.203H12v-.001zm0 2.306l-1.408 3.149H13.41L12 11.463z"/>
    </svg>

    {{-- النص باللون الأسود جاهز --}}
    Sign up with Adobe
</a>
    </form>
</x-guest-layout>
