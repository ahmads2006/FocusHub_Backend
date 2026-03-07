<!DOCTYPE html>
<html lang="ar" dir="rtl" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>رمز التحقق - OpticVault</title>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background-color: #0d0f14; font-family: 'IBM Plex Sans Arabic', 'Segoe UI', Tahoma, Arial, sans-serif; -webkit-font-smoothing: antialiased;">

    <!-- Outer wrapper -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #0d0f14; padding: 48px 20px;">
        <tr>
            <td align="center">

                <!-- Subtle top accent bar -->
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; width: 100%; margin-bottom: 0;">
                    <tr>
                        <td style="background: linear-gradient(90deg, #d4a853 0%, #f0c97a 40%, #d4a853 100%); height: 3px; border-radius: 3px 3px 0 0;"></td>
                    </tr>
                </table>

                <!-- Main card -->
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #13151c; border-radius: 0 0 20px 20px; overflow: hidden; max-width: 600px; width: 100%; border: 1px solid #1e2130; border-top: none;">

                    <!-- Header -->
                    <tr>
                        <td style="padding: 44px 48px 36px 48px; text-align: center; background-color: #13151c;">

                            <!-- Logo -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="text-align: center;">
                                        <p style="margin: 0; display: inline-block;">
                                            <span style="font-size: 11px; font-weight: 700; color: #d4a853; letter-spacing: 5px; text-transform: uppercase;">✦ OPTICVAULT</span>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 16px; text-align: center;">
                                        <!-- Decorative line -->
                                        <table width="60" cellpadding="0" cellspacing="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="background: linear-gradient(90deg, transparent, #d4a853, transparent); height: 1px;"></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 0 48px 40px 48px; direction: rtl; text-align: right;">

                            <!-- Greeting -->
                            <p style="margin: 0 0 8px 0; font-size: 24px; font-weight: 700; color: #f1f3f9; line-height: 1.4;">مرحباً، {{ $userName }}</p>
                            <p style="margin: 0 0 36px 0; font-size: 15px; line-height: 1.9; color: #8891aa; font-weight: 400;">
                                شكراً لانضمامك إلى مجتمع <span style="color: #d4a853; font-weight: 600;">OpticVault</span>.
                                استخدم رمز الأمان أدناه لإتمام التحقق من هويّتك والدخول إلى حسابك.
                            </p>

                            <!-- Code Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="background-color: #0d0f14; border: 1px solid #1e2130; border-radius: 14px; padding: 36px 28px; text-align: center; position: relative;">

                                        <!-- Corner accents (top right & bottom left) -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="text-align: center;">
                                                    <p style="margin: 0 0 14px 0; font-size: 10px; font-weight: 700; color: #d4a853; letter-spacing: 5px; text-transform: uppercase;">رمز التحقق</p>

                                                    <!-- The code -->
                                                    <p style="margin: 0 0 14px 0; font-size: 52px; font-weight: 700; color: #f1f3f9; letter-spacing: 14px; font-family: 'Courier New', Courier, monospace; direction: ltr; text-align: center;">{{ $code }}</p>

                                                    <!-- Underline accent -->
                                                    <table width="80" cellpadding="0" cellspacing="0" border="0" style="margin: 0 auto;">
                                                        <tr>
                                                            <td style="background: linear-gradient(90deg, transparent, #d4a853, transparent); height: 1px;"></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                    </td>
                                </tr>
                            </table>

                            <!-- Notice -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 28px;">
                                <tr>
                                    <td style="background-color: #0d0f14; border-right: 2px solid #d4a853; padding: 16px 18px; border-radius: 0 8px 8px 0;">
                                        <p style="margin: 0; font-size: 13px; color: #8891aa; line-height: 1.8;">
                                            🔒 هذا الرمز <span style="color: #d4a853; font-weight: 600;">صالح مرة واحدة فقط</span>. إذا لم تطلبه، يمكنك تجاهل هذا البريد بأمان.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                        <td style="padding: 0 48px;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="background-color: #1e2130; height: 1px;"></td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 48px 32px 48px; text-align: center;">
                            <p style="margin: 0 0 6px 0; font-size: 11px; color: #3d4460; letter-spacing: 3px; text-transform: uppercase;">OPTICVAULT COMMUNITY</p>
                            <p style="margin: 0; font-size: 12px; color: #3d4460;">© {{ date('Y') }} — جميع الحقوق محفوظة</p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>