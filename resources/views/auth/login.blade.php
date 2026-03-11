<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
            <a href="{{ route('register') }}"
                style="margin-left: 10px; background-color: #000; color: #fff; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 14px; display: inline-block; margin-right: 10px; margin-left: 10px; margin-top: 10px; margin-bottom: 10px; cursor: pointer;">

                {{ __('Register') }}
            </a>

            @if (Route::has('verify.code'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('verify.code') }}">
                    {{ __('لم يصلك رمز التحقق؟') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>

        <div class="flex items-center justify-center mt-6 pt-6 border-t border-gray-100">
            <a href="{{ route('auth.google') }}" class="flex items-center justify-center gap-3 w-full px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition-colors text-sm font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="w-5 h-5" viewBox="0 0 24 24">
                    <path fill="#EA4335" d="M12 11h9v2h-9zM12 1h9v2h-9zM12 21h9v2h-9z"/>
                    <path fill="#FBBC05" d="M1 12c0-1.1.2-2.1.6-3.1L4.8 11c-.1.3-.1.7-.1 1s0 .7.1 1l-3.2 2.1c-.4-1-.6-2-.6-3.1z"/>
                    <path fill="#4285F4" d="M12 4.1c1.6 0 3 .6 4.1 1.6l3-3C17.2 1 14.8 0 12 0 7.3 0 3.3 2.7 1.3 6.6L4.5 9c1-2.8 3.6-4.9 7.5-4.9z"/>
                    <path fill="#34A853" d="M12 19.9c-3.9 0-6.5-2.1-7.5-4.9L1.3 17.4C3.3 21.3 7.3 24 12 24c2.8 0 5.2-1 7.1-2.7l-3-2.3c-1.1.7-2.5 1.1-4.1 1.1z"/>
                    <path fill="#4285F4" d="M22.2 12c0-.6 0-1.2-.1-1.8H12v3.6h5.8c-.2 1.2-1 2.2-2.1 2.9l3 2.3c1.7-1.6 2.7-3.9 2.7-6.6z"/>
                    <!-- Simplified SVG path for the sake of the edit, ideally a clean Google logo SVG -->
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Continue with Google
            </a>
        </div>
    </form>
</x-guest-layout>
