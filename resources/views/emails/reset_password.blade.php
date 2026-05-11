@php
    $isAr = app()->getLocale() == 'ar';
    $dir = $isAr ? 'rtl' : 'ltr';
    $accentColor = '#C3941A';
    $darkBg = '#0a0a0a';
    $cardBg = '#111111';
    $textColor = '#ffffff';
    $textSecondary = '#d0d0d0';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isAr ? 'إعادة تعيين كلمة المرور - OpalShot' : 'Reset Password - OpalShot' }}</title>
    <style>
        body {
            background-color: {{ $darkBg }};
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: {{ $textColor }};
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .wrapper {
            width: 100%;
            background-color: {{ $darkBg }};
            padding: 40px 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: {{ $cardBg }};
            border: 1px solid #222;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .header {
            padding: 40px;
            text-align: center;
            border-bottom: 1px solid #222;
        }
        .logo-text {
            font-size: 32px;
            font-weight: bold;
            color: {{ $textColor }};
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .logo-accent {
            color: {{ $accentColor }};
        }
        .content {
            padding: 40px;
            text-align: center;
        }
        .title {
            font-size: 24px;
            margin-bottom: 20px;
            color: {{ $textColor }};
        }
        .description {
            font-size: 16px;
            color: {{ $textSecondary }};
            margin-bottom: 30px;
        }
        .btn-wrapper {
            margin-bottom: 30px;
        }
        .reset-btn {
            background-color: {{ $accentColor }};
            color: #000000 !important;
            padding: 15px 35px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            display: inline-block;
        }
        .info-box {
            background-color: #1a1a1a;
            border-left: 4px solid {{ $accentColor }};
            padding: 15px;
            font-size: 14px;
            color: {{ $textSecondary }};
            text-align: {{ $isAr ? 'right' : 'left' }};
            margin-top: 20px;
        }
        .footer {
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            background-color: #050505;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <div class="logo-text">
                    <span class="logo-accent">Opal</span>Shot
                </div>
            </div>
            <div class="content">
                <h1 class="title">
                    {{ $isAr ? 'إعادة تعيين كلمة المرور' : 'Reset Your Password' }}
                </h1>
                <p class="description">
                    {{ $isAr ? 'لقد تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بحسابك. انقر على الزر أدناه لاختيار كلمة مرور جديدة وآمنة.' : 'We received a request to reset your account password. Click below to set a new secure password.' }}
                </p>
                <div class="btn-wrapper">
                    <a href="{{ $url }}" class="reset-btn">
                        {{ $isAr ? 'إعادة التعيين' : 'Reset Now' }}
                    </a>
                </div>
                <div class="info-box">
                    🔐 {{ $isAr ? 'إذا لم تقم بطلب هذا، تجاهل الرسالة. الرابط صالح لمدة ساعة واحدة فقط ولا يمكن إعادة استخدامه.' : 'If you didn\'t request this, safely ignore this email. This link expires in 1 hour.' }}
                </div>
            </div>
            <div class="footer">
                &copy; {{ date('Y') }} OpalShot {{ $isAr ? 'جميع الحقوق محفوظة' : 'All Rights Reserved' }}
            </div>
        </div>
    </div>
</body>
</html>