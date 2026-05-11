<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 0; background-color: #050608; font-family: Arial, sans-serif; color: #ffffff; }
        .wrapper { width: 100%; padding: 40px 10px; background-color: #050608; }
        .container { max-width: 500px; margin: 0 auto; background-color: #11131a; border: 1px solid #1a1a1c; border-radius: 12px; padding: 40px; text-align: center; }
        .header { margin-bottom: 30px; }
        .logo-text { font-size: 28px; font-weight: bold; color: #ffffff; }
        .logo-text span { color: #C3941A; }
        .badge { display: inline-block; padding: 5px 12px; background-color: rgba(217, 83, 79, 0.1); border: 1px solid rgba(217, 83, 79, 0.3); color: #d9534f; border-radius: 20px; font-size: 11px; margin-bottom: 20px; }
        .title { font-size: 20px; color: #ffffff; margin-bottom: 15px; }
        .text { font-size: 14px; color: #a0a0a0; line-height: 1.6; margin-bottom: 30px; }
        .code-box { background-color: #1a1a1c; border: 1px dashed #d9534f; border-radius: 10px; padding: 25px; margin-bottom: 25px; }
        .code-label { font-size: 10px; color: #888888; text-transform: uppercase; margin-bottom: 15px; letter-spacing: 2px; }
        .code { font-size: 36px; font-weight: bold; color: #d9534f; letter-spacing: 10px; margin: 0; }
        .warning { font-size: 12px; color: #d9534f; margin-bottom: 10px; font-weight: bold; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #222222; font-size: 11px; color: #666666; }
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
                        <div class="badge">
                            {{ app()->getLocale() == 'ar' ? 'تأكيد الحذف' : 'DELETE CONFIRMATION' }}
                        </div>
                        <div class="title">
                            {{ app()->getLocale() == 'ar' ? 'طلب حذف ألبوم' : 'Album Deletion Request' }}
                        </div>
                        <div class="text">
                            {{ app()->getLocale() == 'ar' ? 'لقد طلبت حذف ألبوم يحتوي على صور. لمنع فقدان البيانات عن طريق الخطأ، يرجى استخدام رمز التحقق التالي لتأكيد الحذف:' : 'You requested to delete an album that contains photos. To prevent accidental data loss, please use the following verification code to confirm the deletion:' }}
                        </div>
                        <div class="code-box">
                            <div class="code-label">Verification Code</div>
                            <div class="code">{{ implode(' ', str_split($otp ?? '000000')) }}</div>
                        </div>
                        <div class="warning">
                            {{ app()->getLocale() == 'ar' ? 'تحذير: سيؤدي هذا الإجراء إلى حذف الألبوم وجميع صوره بشكل دائم من خوادمنا ولن يمكن التراجع عنه.' : 'Warning: This action will permanently delete the album and all its images from our servers. This cannot be undone.' }}
                        </div>
                        <div style="font-size: 12px; color: #888888;">
                            {{ app()->getLocale() == 'ar' ? 'إذا لم تطلب هذا، يرجى تأمين حسابك فوراً.' : 'If you did not initiate this request, please secure your account immediately.' }}
                        </div>
                        <div class="footer">
                            <div>&copy; {{ date('Y') }} OPALSHOT PREMIER VAULT</div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
