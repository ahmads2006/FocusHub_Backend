@php
    $isAr = app()->getLocale() == 'ar';
    $dir = $isAr ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $isAr ? 'إعادة تعيين كلمة المرور - OpalShot' : 'Reset Password - OpalShot' }}</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Tajawal:wght@300;400;500&display=swap" rel="stylesheet">
<style>
    body {
        margin: 0;
        padding: 0;
        background-color: #0a0a0a;
        font-family: 'Tajawal', -apple-system, sans-serif;
        color: #e5e5e5;
        -webkit-font-smoothing: antialiased;
    }
    .wrapper {
        width: 100%;
        padding: 60px 20px;
        box-sizing: border-box;
    }
    .container {
        max-width: 600px;
        margin: 0 auto;
        background: #111111;
        border: 1px solid rgba(195, 148, 26, 0.15);
        border-radius: 16px;
        overflow: hidden;
        position: relative;
    }
    .header {
        text-align: center;
        padding: 50px 40px 30px;
        position: relative;
    }
    .logo-container {
        margin-bottom: 24px;
        position: relative;
        display: inline-block;
    }
    .lens-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: 1px solid rgba(195, 148, 26, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        position: relative;
    }
    .lens-inner {
        width: 20px;
        height: 20px;
        background: radial-gradient(circle, #C3941A 0%, rgba(195, 148, 26, 0.2) 100%);
        border-radius: 50%;
        box-shadow: 0 0 15px rgba(195, 148, 26, 0.4);
    }
    .brand-name {
        font-family: 'Playfair Display', serif;
        font-size: 32px;
        color: #ffffff;
        margin: 0;
        letter-spacing: 1px;
    }
    .brand-accent { color: #C3941A; }
    
    .content {
        padding: 0 50px 40px;
        text-align: center;
    }
    .title {
        font-size: 22px;
        color: #ffffff;
        margin: 0 0 16px 0;
        font-weight: 500;
    }
    .text {
        font-size: 15px;
        line-height: 1.6;
        color: rgba(255, 255, 255, 0.6);
        margin: 0 0 35px 0;
    }
    
    .btn-container {
        margin: 40px 0;
    }
    
    .reset-btn {
        display: inline-block;
        background: linear-gradient(135deg, #C3941A, #E8B84B);
        color: #0a0a0a !important;
        text-decoration: none;
        padding: 16px 40px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 16px;
        letter-spacing: 0.5px;
        box-shadow: 0 10px 25px rgba(195, 148, 26, 0.2);
    }

    .warning-text {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.4);
        margin-top: 30px;
        padding: 15px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }
    
    .footer {
        text-align: center;
        padding: 30px;
        background: #0d0d0d;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }
    .footer-text {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.3);
        margin: 0;
    }
</style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <div class="logo-container">
                    <div class="lens-icon">
                        <div class="lens-inner"></div>
                    </div>
                    <h1 class="brand-name"><span class="brand-accent">Opal</span>Shot</h1>
                </div>
            </div>
            
            <div class="content">
                <h2 class="title">{{ $isAr ? 'إعادة تعيين كلمة المرور' : 'Reset Your Password' }}</h2>
                <p class="text">
                    {{ $isAr ? 'لقد تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بحسابك في OpalShot. انقر على الزر أدناه لاختيار كلمة مرور جديدة.' : 'We received a request to reset your OpalShot password. Click the button below to choose a new one.' }}
                </p>
                
                <div class="btn-container">
                    <a href="{{ $url }}" class="reset-btn">
                        {{ $isAr ? 'إعادة تعيين كلمة المرور' : 'Reset Password' }}
                    </a>
                </div>
                
                <div class="warning-text">
                    {{ $isAr ? 'إذا لم تقم بطلب إعادة التعيين، يمكنك تجاهل هذه الرسالة بأمان. هذا الرابط صالح لمدة ساعة واحدة فقط.' : 'If you didn\'t request a password reset, you can safely ignore this email. This link is valid for 1 hour.' }}
                </div>
            </div>
            
            <div class="footer">
                <p class="footer-text">&copy; {{ date('Y') }} OpalShot. {{ $isAr ? 'جميع الحقوق محفوظة.' : 'All rights reserved.' }}</p>
            </div>
        </div>
    </div>
</body>
</html>
