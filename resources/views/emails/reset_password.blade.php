Option3 star logo · HTML
Copy

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
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&family=Tajawal:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #C3941A;
            --accent-bright: #FFD700;
            --dark-1: #0a0a0a;
            --dark-2: #111111;
            --dark-3: #1a1a1a;
            --text-1: #ffffff;
            --text-2: #d0d0d0;
        }
 
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
 
        html, body {
            width: 100%;
            height: 100%;
        }
 
        body {
            background: linear-gradient(45deg, var(--dark-1) 0%, #0f0f0f 50%, var(--dark-1) 100%);
            font-family: 'Space Grotesk', 'Tajawal', sans-serif;
            color: var(--text-1);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
            -webkit-font-smoothing: antialiased;
        }
 
        /* Animated grid background */
        body::before {
            content: '';
            position: fixed;
            width: 200%;
            height: 200%;
            top: -50%;
            left: -50%;
            z-index: 0;
            opacity: 0.15;
            background-image: 
                linear-gradient(0deg, transparent 24%, rgba(195, 148, 26, 0.2) 25%, rgba(195, 148, 26, 0.2) 26%, transparent 27%, transparent 74%, rgba(195, 148, 26, 0.2) 75%, rgba(195, 148, 26, 0.2) 76%, transparent 77%, transparent),
                linear-gradient(90deg, transparent 24%, rgba(195, 148, 26, 0.2) 25%, rgba(195, 148, 26, 0.2) 26%, transparent 27%, transparent 74%, rgba(195, 148, 26, 0.2) 75%, rgba(195, 148, 26, 0.2) 76%, transparent 77%, transparent);
            background-size: 50px 50px;
            animation: grid-move 20s linear infinite;
            pointer-events: none;
        }
 
        @keyframes grid-move {
            0% {
                transform: translate(0, 0);
            }
            100% {
                transform: translate(50px, 50px);
            }
        }
 
        /* Glow orbs */
        body::after {
            content: '';
            position: fixed;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(195, 148, 26, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 0;
            pointer-events: none;
            filter: blur(40px);
            animation: glow-pulse 4s ease-in-out infinite;
        }
 
        @keyframes glow-pulse {
            0%, 100% {
                transform: translate(-50%, -50%) scale(1);
            }
            50% {
                transform: translate(-50%, -50%) scale(1.1);
            }
        }
 
        .wrapper {
            width: 100%;
            padding: 20px;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
 
        .container {
            width: 100%;
            max-width: 540px;
            background: linear-gradient(135deg, var(--dark-2) 0%, var(--dark-3) 100%);
            border: 1px solid rgba(195, 148, 26, 0.2);
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            backdrop-filter: blur(20px);
            box-shadow: 0 0 60px rgba(195, 148, 26, 0.15),
                        0 25px 50px rgba(0, 0, 0, 0.8);
            animation: containerPop 0.9s cubic-bezier(0.34, 1.56, 0.64, 1) 0s;
        }
 
        @keyframes containerPop {
            0% {
                opacity: 0;
                transform: scale(0.85) translateY(40px);
            }
            50% {
                transform: scale(1.02);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
 
        /* Accent line */
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, rgba(195, 148, 26, 0.5) 50%, transparent 100%);
            z-index: 10;
        }
 
        .header {
            text-align: center;
            padding: clamp(40px, 10vw, 60px) clamp(25px, 6vw, 40px) clamp(30px, 7vw, 40px);
            position: relative;
            background: linear-gradient(180deg, rgba(195, 148, 26, 0.1) 0%, transparent 100%);
            border-bottom: 1px solid rgba(195, 148, 26, 0.15);
        }
 
        .logo-container {
            display: inline-block;
            position: relative;
            animation: logoFloat 0.8s ease-out 0.1s both;
        }
 
        @keyframes logoFloat {
            0% {
                opacity: 0;
                transform: translateY(-40px) scale(0.8);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
 
        .star-logo {
            width: 100px;
            height: 100px;
            display: block;
            margin: 0 auto 28px;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            filter: drop-shadow(0 0 8px rgba(195, 148, 26, 0.4))
                    drop-shadow(0 0 20px rgba(195, 148, 26, 0.2));
            animation: star-spin-slow 20s linear infinite, star-glow-pulse 3s ease-in-out infinite;
        }
 
        .logo-container:hover .star-logo {
            transform: scale(1.12) rotate(15deg);
            filter: drop-shadow(0 0 15px rgba(255, 215, 0, 0.7))
                    drop-shadow(0 0 35px rgba(195, 148, 26, 0.5));
        }
 
        @keyframes star-spin-slow {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
 
        .logo-container:hover .star-logo {
            animation: none;
        }
 
        @keyframes star-glow-pulse {
            0%, 100% {
                filter: drop-shadow(0 0 8px rgba(195, 148, 26, 0.4))
                        drop-shadow(0 0 20px rgba(195, 148, 26, 0.2));
            }
            50% {
                filter: drop-shadow(0 0 14px rgba(255, 215, 0, 0.7))
                        drop-shadow(0 0 35px rgba(195, 148, 26, 0.45));
            }
        }
 
        .brand-name {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(28px, 8vw, 38px);
            color: var(--text-1);
            margin: 0;
            letter-spacing: -1px;
            font-weight: 600;
            text-transform: uppercase;
            tracking: -0.02em;
        }
 
        .brand-accent {
            background: linear-gradient(135deg, #FFD700 0%, #E8B84B 50%, #C3941A 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            transition: all 0.4s ease;
            filter: drop-shadow(0 0 10px rgba(195, 148, 26, 0.3));
        }
 
        .logo-container:hover .brand-accent {
            filter: drop-shadow(0 0 20px rgba(195, 148, 26, 0.6));
        }
 
        .content {
            padding: clamp(35px, 9vw, 50px);
            text-align: center;
            animation: contentSlide 0.8s ease-out 0.25s both;
        }
 
        @keyframes contentSlide {
            from {
                opacity: 0;
                transform: translateY(25px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
 
        .title {
            font-size: clamp(22px, 6vw, 28px);
            color: var(--text-1);
            margin: 0 0 16px 0;
            font-weight: 600;
            line-height: 1.3;
            letter-spacing: -0.5px;
        }
 
        .description {
            font-size: clamp(14px, 3.8vw, 15px);
            color: var(--text-2);
            line-height: 1.75;
            margin: 0 0 40px 0;
        }
 
        .btn-wrapper {
            margin: 45px 0 30px;
        }
 
        .reset-btn {
            display: inline-block;
            background: linear-gradient(135deg, #C3941A 0%, #E8B84B 50%, #FFD700 100%);
            color: #0a0a0a !important;
            text-decoration: none;
            padding: clamp(14px 40px, 4vw 9vw, 17px 55px);
            border-radius: 12px;
            font-weight: 600;
            font-size: clamp(14px, 3.8vw, 16px);
            letter-spacing: 0.8px;
            text-transform: uppercase;
            cursor: pointer;
            border: none;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 15px 35px rgba(195, 148, 26, 0.3),
                        0 0 0 0 rgba(195, 148, 26, 0.2);
            white-space: nowrap;
        }
 
        .reset-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.6s ease;
        }
 
        .reset-btn::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 12px;
            background: radial-gradient(circle at 50% 0%, rgba(255, 255, 255, 0.3), transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
 
        .reset-btn:hover {
            background: linear-gradient(135deg, #FFD700 0%, #E8B84B 50%, #C3941A 100%);
            transform: translateY(-4px);
            box-shadow: 0 20px 50px rgba(195, 148, 26, 0.5),
                        0 0 30px rgba(195, 148, 26, 0.3);
        }
 
        .reset-btn:hover::before {
            left: 100%;
        }
 
        .reset-btn:hover::after {
            opacity: 1;
        }
 
        .reset-btn:active {
            transform: translateY(-2px);
        }
 
        .info-box {
            background: linear-gradient(135deg, rgba(195, 148, 26, 0.08) 0%, rgba(195, 148, 26, 0.02) 100%);
            border: 1px solid rgba(195, 148, 26, 0.2);
            border-radius: 12px;
            padding: 18px;
            font-size: clamp(12px, 3.2vw, 13px);
            color: var(--text-2);
            line-height: 1.6;
            position: relative;
            overflow: hidden;
        }
 
        .info-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 100%;
            background: linear-gradient(180deg, var(--accent) 0%, transparent 100%);
        }
 
        .footer {
            text-align: center;
            padding: clamp(18px, 5vw, 25px);
            background: rgba(0, 0, 0, 0.3);
            border-top: 1px solid rgba(195, 148, 26, 0.1);
            font-size: clamp(11px, 2.8vw, 12px);
            color: rgba(255, 255, 255, 0.2);
            letter-spacing: 0.3px;
        }
 
        .reset-btn:focus {
            outline: 2px solid var(--accent-bright);
            outline-offset: 2px;
        }
 
        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition-duration: 0.01ms !important;
            }
        }
 
        @media (max-width: 480px) {
            .container {
                border-radius: 16px;
            }
 
            body::before {
                background-size: 30px 30px;
            }
        }
 
        @media (max-height: 600px) and (orientation: landscape) {
            .header {
                padding: 25px 40px 15px;
            }
 
            .content {
                padding: 20px 40px;
            }
 
            .btn-wrapper {
                margin: 25px 0 15px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <div class="logo-container">
                    <svg class="star-logo" viewBox="-55 -55 110 110" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <radialGradient id="coreGrad" cx="40%" cy="35%" r="60%">
                                <stop offset="0%"   stop-color="#FFFFFF" stop-opacity="0.9"/>
                                <stop offset="30%"  stop-color="#FFD700"/>
                                <stop offset="70%"  stop-color="#C3941A"/>
                                <stop offset="100%" stop-color="#8B6914" stop-opacity="0"/>
                            </radialGradient>
                            <radialGradient id="starGrad" cx="50%" cy="30%" r="70%">
                                <stop offset="0%"   stop-color="#FFD700" stop-opacity="0.85"/>
                                <stop offset="60%"  stop-color="#C3941A" stop-opacity="0.55"/>
                                <stop offset="100%" stop-color="#8B6914" stop-opacity="0.2"/>
                            </radialGradient>
                        </defs>
                        <circle r="48" fill="none" stroke="#C3941A" stroke-width="0.6" opacity="0.35"/>
                        <circle r="42" fill="none" stroke="#C3941A" stroke-width="0.4" opacity="0.2"/>
                        <line x1="0"    y1="-48" x2="0"    y2="-43" stroke="#C3941A" stroke-width="1.2" opacity="0.7"/>
                        <line x1="48"   y1="0"   x2="43"   y2="0"   stroke="#C3941A" stroke-width="1.2" opacity="0.7"/>
                        <line x1="0"    y1="48"  x2="0"    y2="43"  stroke="#C3941A" stroke-width="1.2" opacity="0.7"/>
                        <line x1="-48"  y1="0"   x2="-43"  y2="0"   stroke="#C3941A" stroke-width="1.2" opacity="0.7"/>
                        <line x1="33.9" y1="-33.9" x2="30.3" y2="-30.3" stroke="#C3941A" stroke-width="0.8" opacity="0.45"/>
                        <line x1="33.9" y1="33.9"  x2="30.3" y2="30.3"  stroke="#C3941A" stroke-width="0.8" opacity="0.45"/>
                        <line x1="-33.9" y1="33.9" x2="-30.3" y2="30.3" stroke="#C3941A" stroke-width="0.8" opacity="0.45"/>
                        <line x1="-33.9" y1="-33.9" x2="-30.3" y2="-30.3" stroke="#C3941A" stroke-width="0.8" opacity="0.45"/>
                        <polygon
                            points="0,-40 5.207,-10.811 31.272,-24.94 11.699,-2.67 38.996,8.9 9.382,7.482 17.356,36.036 0,12 -17.356,36.036 -9.382,7.482 -38.996,8.9 -11.699,-2.67 -31.272,-24.94 -5.207,-10.811"
                            fill="url(#starGrad)"
                            stroke="#C3941A"
                            stroke-width="0.5"
                            stroke-linejoin="round"
                        />
                        <circle r="13" fill="rgba(195,148,26,0.15)"/>
                        <circle r="9" fill="url(#coreGrad)"/>
                    </svg>
                    <h1 class="brand-name"><span class="brand-accent">Opal</span>Shot</h1>
                </div>
            </div>
 
            <div class="content">
                <h2 class="title">
                    {{ $isAr ? 'إعادة تعيين كلمة المرور' : 'Reset Your Password' }}
                </h2>
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