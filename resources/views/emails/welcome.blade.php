@php
    $isAr = app()->getLocale() == 'ar';
    $dir = $isAr ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to OpalShot</title>
    <style>
        body { margin: 0; padding: 0; background-color: #050608; font-family: Arial, sans-serif; color: #ffffff; }
        .wrapper { width: 100%; padding: 40px 10px; background-color: #050608; }
        .container { max-width: 500px; margin: 0 auto; background-color: #11131a; border: 1px solid #1a1a1c; border-radius: 12px; overflow: hidden; text-align: center; }
        .header { padding: 40px 20px; background-color: #151821; border-bottom: 1px solid #222222; }
        .logo-text { font-size: 32px; font-weight: bold; color: #ffffff; letter-spacing: 1px; }
        .logo-text span { color: #C3941A; }
        .content { padding: 40px; }
        .title { font-size: 24px; color: #E8B84B; margin-bottom: 20px; font-weight: bold; }
        .text { font-size: 15px; color: #d0d0d0; line-height: 1.6; margin-bottom: 30px; }
        .btn { display: inline-block; padding: 14px 30px; background-color: #C3941A; color: #000000; text-decoration: none; font-weight: bold; border-radius: 8px; text-transform: uppercase; font-size: 14px; }
        .footer { padding: 25px; background-color: #08090c; font-size: 11px; color: #666666; border-top: 1px solid #1a1a1c; }
        .quote { font-style: italic; color: #888888; margin-bottom: 10px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td align="center">
                    <div class="container">
                        <div class="header">
                            <div class="logo-text">Opal<span>Shot</span></div>
                        </div>
                        <div class="content">
                            <h1 class="title">
                                {{ $isAr ? 'مرحباً بك في مجتمعنا، ' . $userName . '!' : 'Welcome to the community, ' . $userName . '!' }}
                            </h1>
                            <div class="text">
                                {{ $isAr ? 'شكراً لانضمامك إلى OpalShot. نحن متحمسون جداً لمشاهدة إبداعاتك ومساعدتك في تنظيم ملفاتك وصورك الأكثر قيمة في خزنتنا المؤمنة.' : 'Thank you for joining OpalShot. We\'re thrilled to see your creativity and help you organize your most precious photos and files in our secure vault.' }}
                            </div>
                            <a href="{{ config('app.frontend_url') }}" class="btn">
                                {{ $isAr ? 'ابدأ الآن' : 'Get Started' }}
                            </a>
                            
                            <div style="margin-top: 30px; font-size: 12px; color: #888888; line-height: 1.6;">
                                {{ $isAr ? 'إذا كان لديك أي أسئلة، فلا تتردد في الرد على هذه الرسالة. يسعدنا دائماً تقديم المساعدة.' : 'If you have any questions, feel free to reply to this email. We\'re always here to help.' }}
                            </div>
                        </div>
                        <div class="footer">
                            <div class="quote">"Every photograph is a certificate of presence."</div>
                            <div>&copy; {{ date('Y') }} OPALSHOT PREMIER VAULT</div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
