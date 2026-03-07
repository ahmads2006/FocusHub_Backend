<x-guest-layout>
    <div style="min-height: 100vh; background: #0d0f14; display: flex; align-items: center; justify-content: center; font-family: 'IBM Plex Sans Arabic', sans-serif;" dir="rtl">
        <div style="background: #13151c; border: 1px solid #1e2130; border-radius: 16px; padding: 32px; width: 100%; max-width: 400px; text-align: center;">
            <div style="color: #d4a853; font-size: 32px; margin-bottom: 24px;">🔒</div>
            <h1 style="color: #f1f3f9; font-size: 20px; font-weight: 700; margin-bottom: 8px;">محتوى محمي</h1>
            <p style="color: #8891aa; font-size: 14px; margin-bottom: 24px;">هذا الرابط محمي بكلمة مرور. يرجى إدخال كلمة المرور للمتابعة.</p>

            <form method="POST" action="{{ route('shared.link.verify', $link->token) }}">
                @csrf
                <div style="margin-bottom: 20px;">
                    <input type="password" name="password" placeholder="كلمة المرور" required
                           style="background: #0d0f14; border: 1px solid #1e2130; border-radius: 10px; padding: 12px 16px; width: 100%; color: #f1f3f9; text-align: center;">
                    @error('password')
                        <p style="color: #f87171; font-size: 12px; margin-top: 8px;">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" 
                        style="background: #d4a853; color: #0d0f14; font-weight: 700; border: none; border-radius: 10px; padding: 12px; width: 100%; cursor: pointer; transition: opacity 0.2s;">
                    دخول
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>
