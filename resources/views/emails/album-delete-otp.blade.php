<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e1e1; border-radius: 10px; }
        .header { text-align: center; margin-bottom: 30px; }
        .otp-code { font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #D4AF37; text-align: center; padding: 20px; background: #f9f9f9; border-radius: 8px; margin: 20px 0; border: 1px dashed #D4AF37; }
        .footer { font-size: 12px; color: #999; margin-top: 30px; text-align: center; }
        .warning { color: #d9534f; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="color: #D4AF37;">OpalShot Security</h1>
        </div>
        <p>Hello,</p>
        <p>You requested to delete an album that contains photos. To prevent accidental data loss, please use the following verification code to confirm the deletion:</p>
        
        <div class="otp-code">{{ $otp }}</div>
        
        <p class="warning">Warning: This action will permanently delete the album and all its images from our servers and cloud storage. This cannot be undone.</p>
        
        <p>If you did not initiate this request, please secure your account immediately.</p>
        
        <div class="footer">
            &copy; {{ date('Y') }} OpalShot. All rights reserved.
        </div>
    </div>
</body>
</html>
