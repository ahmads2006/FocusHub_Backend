@php
    $isAr = app()->getLocale() == 'ar';
    $dir = $isAr ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $isAr ? 'رمز التحقق - OpalShot' : 'Verification Code - OpalShot' }}</title>
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
        max-width: 550px;
        margin: 0 auto;
        background-color: #0d0d0f;
        border: 1px solid #1a1a1c;
        border-radius: 16px;
        padding: 60px 40px;
        text-align: center;
        box-shadow: 0 20px 40px rgba(0,0,0,0.8);
    }
    
    /* Logo Section */
    .logo-container {
        margin-bottom: 50px;
    }
    .logo-icon {
        width: 55px;
        height: 55px;
        margin-bottom: 20px;
    }
    .logo-text {
        font-family: 'Playfair Display', serif;
        font-size: 26px;
        color: #f5f5f5;
        margin: 0 0 8px 0;
        letter-spacing: 1.5px;
        font-weight: 600;
    }
    .logo-subtitle {
        font-size: 10px;
        color: #555;
        letter-spacing: 4px;
        text-transform: uppercase;
        font-family: sans-serif;
    }
    
    /* Main Content */
    .pre-title {
        font-size: 11px;
        color: #555;
        margin-bottom: 12px;
    }
    .title {
        font-size: 28px;
        color: #ffffff;
        margin: 0 0 25px 0;
        font-weight: 400;
    }
    .subtitle {
        font-size: 13px;
        color: #777;
        line-height: 1.8;
        margin-bottom: 4px;
    }
    
    /* Code Box */
    .code-box {
        margin: 45px auto;
        padding: 35px 20px;
        border: 1px solid #1c1c1e;
        border-radius: 12px;
        background-color: #0f0f11;
        max-width: 420px;
    }
    .code-label {
        font-size: 10px;
        color: #444;
        letter-spacing: 3px;
        text-transform: uppercase;
        margin-bottom: 25px;
        font-family: sans-serif;
    }
    .code-value {
        font-family: 'Playfair Display', serif;
        font-size: 42px;
        color: #cca451; /* Elegant Gold */
        letter-spacing: 16px;
        margin: 0;
        padding-left: 16px; /* Balance letter spacing */
        direction: ltr;
        display: block;
    }
    .code-expiry {
        font-size: 11px;
        color: #444;
        margin-top: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    
    /* Divider */
    .divider {
        height: 1px;
        background-color: #1a1a1c;
        margin: 40px auto;
        width: 100%;
    }
    
    /* Security Info */
    .security-info {
        font-size: 12px;
        color: #555;
        line-height: 2;
    }
    
    /* Footer */
    .footer {
        margin-top: 50px;
    }
    .footer-links {
        margin-bottom: 15px;
    }
    .footer-links a {
        color: #444;
        text-decoration: none;
        font-size: 11px;
        margin: 0 12px;
        transition: color 0.2s;
    }
    .footer-links a:hover {
        color: #777;
    }
    .footer-links span {
        color: #222;
    }
    .footer-copy {
        font-size: 10px;
        color: #333;
    }
</style>
</head>
<body>
<div class="wrapper">
    <div class="container">
        
        <!-- Logo Area -->
        <div class="logo-container">
            <svg class="logo-icon" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <circle cx="50" cy="50" r="46" fill="none" stroke="#cca451" stroke-width="0.8" opacity="0.4"/>
                <circle cx="50" cy="50" r="38" fill="none" stroke="#cca451" stroke-width="0.5" opacity="0.2"/>
                <path d="M50 8 L54 46 L92 50 L54 54 L50 92 L46 54 L8 50 L46 46 Z" fill="#cca451" opacity="0.75"/>
                <circle cx="50" cy="50" r="6" fill="#0a0a0a"/>
                <circle cx="50" cy="50" r="2" fill="#cca451"/>
            </svg>
            <h1 class="logo-text">OpalShot</h1>
            <div class="logo-subtitle">STUDIO PLATFORM</div>
        </div>

        <!-- Text Content -->
        <div class="pre-title">{{ $isAr ? 'التحقق من الهوية' : 'Identity Verification' }}</div>
        <h2 class="title">{{ $isAr ? 'رمز الدخول الخاص بك' : 'Your Login Code' }}</h2>
        
        <div class="subtitle">{{ $isAr ? 'أدخل الرمز أدناه لإتمام تسجيل الدخول.' : 'Enter the code below to complete your login.' }}</div>
        <div class="subtitle">{{ $isAr ? 'صالح للاستخدام مرة واحدة فقط.' : 'Valid for one-time use only.' }}</div>

        <!-- Code Box -->
        <div class="code-box">
            <div class="code-label">ONE-TIME PASSWORD</div>
            <div class="code-value">{{ $code ?? '000000' }}</div>
            <div class="code-expiry">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.6">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                {{ $isAr ? 'ينتهي خلال 3 دقائق' : 'Expires in 3 minutes' }}
            </div>
        </div>

        <div class="divider"></div>

        <!-- Security Warning -->
        <div class="security-info">
            <div>{{ $isAr ? 'إذا لم تطلب هذا الرمز تجاهل الرسالة.' : 'If you didn\'t request this code, safely ignore this email.' }}</div>
            <div>{{ $isAr ? 'لن يطلب فريق OpalShot هذا الرمز منك بأي طريقة.' : 'The OpalShot team will never ask you for this code.' }}</div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-links">
                <a href="#">{{ $isAr ? 'الدعم' : 'Support' }}</a>
                <span>|</span>
                <a href="#">{{ $isAr ? 'شروط الاستخدام' : 'Terms of Use' }}</a>
                <span>|</span>
                <a href="#">{{ $isAr ? 'سياسة الخصوصية' : 'Privacy Policy' }}</a>
            </div>
            <div class="footer-copy">
                © {{ date('Y') }} OpalShot — {{ $isAr ? 'جميع الحقوق محفوظة' : 'All rights reserved' }}
            </div>
        </div>
        
    </div>
</div>
</body>
</html>
