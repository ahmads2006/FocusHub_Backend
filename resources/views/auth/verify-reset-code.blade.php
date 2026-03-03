<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('الرجاء إدخال رمز التحقق المكون من 6 أرقام والذي أرسلناه إلى بريدك الإلكتروني.') }}
    </div>

    <form method="POST" action="{{ route('password.verify.code.post') }}">
        @csrf
        <div>
            <x-input-label for="code" :value="__('رمز التحقق')" />
            <x-text-input id="code" class="block mt-1 w-full" type="text" name="code" required autofocus />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('تحقق') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>