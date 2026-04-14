<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رمز التحقق - OpalShot</title>
    <!-- استدعاء الخطوط -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        /* أنماط أساسية لدعم جميع مزودات البريد */
        body {
            margin: 0;
            padding: 0;
            background-color: #0a0b0f;
            color: #ffffff;
            font-family: 'DM Sans', Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        table {
            border-spacing: 0;
            border-collapse: collapse;
        }
        td {
            padding: 0;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #0a0b0f;
            padding: 40px 0;
        }
        .main-container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #11131a;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            overflow: hidden;
        }
        .header {
            padding: 40px 30px 20px 30px;
            text-align: center;
        }
        .logo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 36px;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: #ffffff;
            margin: 0;
        }
        .logo span {
            color: #C3941A;
        }
        .tagline {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.4);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 5px;
        }
        .content {
            padding: 20px 40px 30px 40px;
            text-align: right;
            direction: rtl;
        }
        .greeting {
            font-size: 22px;
            font-weight: 500;
            color: #ffffff;
            font-family: 'Cormorant Garamond', serif;
            margin-bottom: 20px;
        }
        .message {
            font-size: 15px;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 30px;
        }
        .code-container {
            text-align: center;
            background: linear-gradient(145deg, #181b24 0%, #15171e 100%);
            border: 1px solid rgba(195, 148, 26, 0.2);
            border-radius: 12px;
            padding: 25px 0;
            margin: 0 auto;
            width: 100%;
        }
        .code {
            font-family: 'DM Sans', sans-serif;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 8px;
            color: #C3941A;
            margin: 0;
        }
        .warning {
            font-size: 13px;
            color: #ef4444;
            text-align: center;
            margin-top: 15px;
            opacity: 0.8;
        }
        .footer {
            padding: 30px;
            text-align: center;
            background-color: #0e0f14;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        .footer p {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.3);
            margin: 0 0 10px 0;
        }
        
        @media screen and (max-width: 600px) {
            .content {
                padding: 20px 20px 30px 20px;
            }
            .code {
                font-size: 28px;
                letter-spacing: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <table width="100%" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td align="center">
                    <table class="main-container" border="0" cellpadding="0" cellspacing="0">
                        <!-- رأس الرسالة (الشعار) -->
                        <tr>
                            <td class="header">
                                <h1 class="logo">Opal<span>Shot</span></h1>
                                <div class="tagline">Photography Vault</div>
                            </td>
                        </tr>
                        
                        <!-- محتوى الرسالة -->
                        <tr>
                            <td class="content">
                                <h2 class="greeting">مرحباً {{ $userName }}،</h2>
                                <p class="message">
                                    لقد تلقينا طلباً للتحقق من هويتك والدخول إلى حسابك الخاص على منصة OpalShot.
                                    <br><br>
                                    يرجى استخدام رمز التحقق أدناه لإكمال عملية الدخول:
                                </p>
                                
                                <div class="code-container">
                                    <p class="code">{{ $code }}</p>
                                </div>
                                
                                <p class="warning">
                                    هذا الرمز صالح لمدة 3 دقائق فقط.
                                    <br>
                                    إذا لم تقم بطلب هذا الرمز، يُرجى تجاهل هذه الرسالة لتظل بياناتك آمنة.
                                </p>
                            </td>
                        </tr>
                        
                        <!-- التذييل -->
                        <tr>
                            <td class="footer">
                                <p>جميع الحقوق محفوظة &copy; {{ date('Y') }} OpalShot</p>
                                <p style="font-family: 'Cormorant Garamond', serif; font-style: italic;">
                                    "Every photograph is a certificate of presence."
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
