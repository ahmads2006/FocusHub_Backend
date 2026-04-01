<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6" x-data="{ currentEmail: '{{ $user->email }}', inputEmail: '{{ old('email', $user->email) }}' }">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl">
            <x-input-label for="username" value="اسم المستخدم الفريد (Handle)" />
            <div class="relative mt-1">
                <x-text-input id="username" name="username" type="text" 
                    class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" 
                    :value="old('username', $user->profile->username)" 
                    required 
                    placeholder="@username" />
            </div>
            <p class="mt-2 text-xs text-gray-500">
                <i class="fas fa-info-circle mr-1"></i>
                هذا هو اسمك الفريد الذي يبدأ بـ @. يمكنك تغييره **مرة واحدة كل 30 يوماً** فقط.
            </p>
            @if($user->profile->username_last_changed_at)
                <p class="mt-1 text-xs text-indigo-600">
                    آخر تغيير: {{ $user->profile->username_last_changed_at->diffForHumans() }}
                </p>
            @endif
            <x-input-error class="mt-2" :messages="$errors->get('username')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" x-model="inputEmail" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            <!-- Conditional password confirmation for email change -->
            <div x-show="inputEmail !== currentEmail" style="display: none;" class="mt-4 p-4 rounded-xl border border-purple-500/20 bg-purple-500/5 transition-all">
                <x-input-label for="update_profile_password" value="لتغيير بريدك الإلكتروني، يرجى إدخال كلمة المرور الحالية" class="text-purple-400 font-bold" />
                <x-text-input id="update_profile_password" name="password" type="password" class="mt-2 block w-full" placeholder="كلمة المرور الحالية" autocomplete="current-password" />
                <x-input-error class="mt-2" :messages="$errors->get('password')" />
            </div>

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
