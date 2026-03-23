<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('الرجاء إدخال رمز التحقق المكون من 6 أرقام والذي أرسلناه إلى بريدك الإلكتروني.') }}
    </div>

    <form method="POST" action="{{ route('verify.code.post') }}">
        @csrf
        <div>
            <x-input-label for="code" :value="__('رمز التحقق')" />
            <x-text-input id="code" class="block mt-1 w-full" type="text" name="code" required autofocus />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="block mt-4">
            <label for="trust_device" class="inline-flex items-center">
                <input id="trust_device" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="trust_device" checked>
                <span class="ms-2 text-sm text-gray-600">{{ __('الثقة بهذا الجهاز لمدة 30 يوماً') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-between mt-4">
            <x-primary-button>
                {{ __('تحقق') }}
            </x-primary-button>
        </div>
    </form>

    <div class="mt-6 flex items-center justify-center border-t border-gray-100 pt-4">
        <form method="POST" action="{{ Auth::check() ? route('verification.send') : route('verification.resend.guest') }}">
            @csrf
            <button type="submit" class="text-sm text-blue-600 hover:text-blue-500 font-medium underline focus:outline-none transition ease-in-out duration-150">
                {{ __('إعادة إرسال الرمز') }}
            </button>
        </form>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mt-2 font-medium text-sm text-green-600 text-center">
            {{ __('تم إرسال رمز جديد إلى بريدك الإلكتروني.') }}
        </div>
    @endif
</x-guest-layout>