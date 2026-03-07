<x-app-layout>
    <div style="background-color: #0d0f14; min-height: 100vh; font-family: 'IBM Plex Sans Arabic', sans-serif;" dir="rtl">
        <div class="py-12">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div style="margin-bottom: 32px;">
                    <p style="font-size: 10px; font-weight: 700; letter-spacing: 5px; text-transform: uppercase; color: #d4a853; margin: 0 0 8px 0;">OPTICVAULT</p>
                    <h1 style="font-size: 26px; font-weight: 700; color: #f1f3f9; margin: 0;">مركز التنبيهات</h1>
                </div>

                <div style="background: #13151c; border: 1px solid #1e2130; border-radius: 16px; padding: 40px; text-align: center;">
                    <div style="font-size: 48px; margin-bottom: 20px;">🔔</div>
                    <h2 style="color: #f1f3f9; font-size: 20px; font-weight: 700; margin-bottom: 12px;">لا يوجد تنبيهات جديدة</h2>
                    <p style="color: #8891aa; font-size: 14px; max-width: 400px; margin: 0 auto; line-height: 1.6;">
                        حسابك محدث بالكامل! ستظهر أي تنبيهات متعلقة بنشاطاتك أو الصور الخاصة بك هنا.
                    </p>
                    
                    <div style="margin-top: 32px;">
                        <a href="{{ route('dashboard') }}" style="display: inline-block; background: #d4a853; color: #0d0f14; padding: 12px 24px; border-radius: 10px; font-weight: 700; text-decoration: none; transition: opacity 0.2s;">
                            العودة للوحة التحكم
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
