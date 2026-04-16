// @php
// $isAr = app()->getLocale() == 'ar';
// $dir = $isAr ? 'rtl' : 'ltr';
// @endphp

// <!DOCTYPE html>
// <html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
// <head>
// <meta charset="UTF-8">
// <meta name="viewport" content="width=device-width, initial-scale=1.0">

// <title>Verification Code</title>

// <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;700&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">

// <style>

// body{
// margin:0;
// padding:0;
// background:#0a0b0f;
// font-family:'DM Sans', Arial, sans-serif;
// color:white;
// }

// .wrapper{
// width:100%;
// padding:40px 0;
// }

// .card{

// max-width:520px;
// margin:auto;

// background:#11131a;

// border-radius:22px;

// border:1px solid rgba(195,148,26,.12);

// box-shadow:
// 0 25px 70px rgba(0,0,0,.65),
// 0 0 0 1px rgba(195,148,26,.05);

// overflow:hidden;

// text-align:center;
// }

// .header{

// padding:45px 20px 25px;

// background:
// radial-gradient(circle at center,
// rgba(195,148,26,.18),
// transparent 55%);

// }

// .logo{

// font-family:'Cormorant Garamond', serif;

// font-size:34px;

// font-weight:700;
// }

// .logo span{

// color:#C3941A;

// }

// .divider{

// height:1px;

// width:60%;

// margin:18px auto;

// background:linear-gradient(
// 90deg,
// transparent,
// rgba(195,148,26,.4),
// transparent
// );

// }

// .content{

// padding:10px 40px 40px;

// }

// .secure-label{

// font-size:11px;

// color:#C3941A;

// letter-spacing:.7px;

// margin-bottom:10px;

// }

// .main-text{

// font-size:16px;

// color:#e8e8ea;

// margin-bottom:25px;

// }

// .code-box{

// background:

// linear-gradient(
// 145deg,
// rgba(195,148,26,.12),
// rgba(195,148,26,.02)
// );

// border:1px solid rgba(195,148,26,.35);

// border-radius:14px;

// padding:25px;

// margin:20px 0;

// box-shadow:

// inset 0 0 15px rgba(195,148,26,.15),

// 0 8px 30px rgba(0,0,0,.4);

// }

// .code-label{

// font-size:11px;

// letter-spacing:1px;

// color:#777;

// margin-bottom:10px;

// text-transform:uppercase;

// }

// .code{

// font-size:38px;

// font-weight:700;

// letter-spacing:6px;

// color:#C3941A;

// text-shadow:0 0 20px rgba(195,148,26,.4);

// }

// .warning{

// font-size:13px;

// color:#ef4444;

// margin-top:15px;

// }

// .sub{

// font-size:13px;

// color:rgba(255,255,255,.55);

// margin-top:18px;

// }

// .security{

// font-size:12px;

// color:#888;

// margin-top:15px;

// }

// .footer{

// background:rgba(0,0,0,.2);

// padding:30px;

// border-top:1px solid rgba(255,255,255,.03);

// }

// .links a{

// color:rgba(255,255,255,.4);

// text-decoration:none;

// font-size:12px;

// margin:0 10px;

// }

// .quote{

// font-family:'Cormorant Garamond', serif;

// font-style:italic;

// opacity:.7;

// letter-spacing:.4px;

// color:rgba(195,148,26,.5);

// font-size:14px;

// margin-top:15px;

// }

// .copyright{

// font-size:10px;

// color:rgba(255,255,255,.2);

// margin-top:18px;

// }

// </style>

// </head>

// <body>

// <div class="wrapper">

// <div class="card">

// <div class="header">

// <div class="logo">

// Opal<span>Shot</span>

// </div>

// <div class="divider"></div>

// </div>


// <div class="content">


// <div class="secure-label">

// 🔒 Secure Verification

// </div>


// @if($isAr)

// <div class="main-text">

// مرحباً {{ $userName }}، هذا رمز التحقق الخاص بك:

// </div>

// @else

// <div class="main-text">

// Hello {{ $userName }}, here is your verification code:

// </div>

// @endif


// <div class="code-box">

// <div class="code-label">

// Verification Code

// </div>

// <div class="code">

// {{ $code }}

// </div>

// </div>


// @if($isAr)

// <div class="warning">

// الرمز صالح لمدة 3 دقائق فقط

// </div>

// @else

// <div class="warning">

// This code is valid for 3 minutes only

// </div>

// @endif


// @if($isAr)

// <div class="security">

// إذا لم تطلب هذا الرمز يمكنك تجاهل الرسالة بأمان

// </div>

// @else

// <div class="security">

// If you didn’t request this code you can safely ignore this email

// </div>

// @endif


// @if($isAr)

// <div class="sub">

// يرجى عدم مشاركة هذا الرمز مع أي شخص

// </div>

// @else

// <div class="sub">

// Please do not share this code with anyone

// </div>

// @endif


// </div>


// <div class="footer">

// <div class="links">

// <a href="https://opalshot.studio/privacy-policy">

// @if($isAr) سياسة الخصوصية @else Privacy Policy @endif

// </a>

// |

// <a href="https://opalshot.studio/terms-of-service">

// @if($isAr) شروط الاستخدام @else Terms of Service @endif

// </a>

// </div>


// <div class="quote">

// "Every photograph is a certificate of presence."

// </div>


// <div class="copyright">

// © {{ date('Y') }} OpalShot

// </div>


// </div>

// </div>

// </div>

// </body>

// </html>
