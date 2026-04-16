// @php
// $isAr = app()->getLocale() == 'ar';
// $dir = $isAr ? 'rtl' : 'ltr';
// @endphp

// <!DOCTYPE html>
// <html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
// <head>

// <meta charset="UTF-8">
// <meta name="viewport" content="width=device-width, initial-scale=1.0">

// <title>Activate Your Account</title>

// <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;700&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">

// <style>

// body{
// margin:0;
// padding:0;
// background:#050608;
// font-family:'DM Sans', Arial;
// color:white;
// }

// .wrapper{
// padding:50px 15px;
// }

// .card{

// max-width:520px;
// margin:auto;

// background:#11131a;

// border-radius:26px;

// border:1px solid rgba(195,148,26,.12);

// box-shadow:
// 0 25px 70px rgba(0,0,0,.7);

// overflow:hidden;

// text-align:center;
// }

// .header{

// padding:45px 20px 25px;

// background:

// radial-gradient(circle at center,
// rgba(195,148,26,.15),
// transparent 60%);
// }

// .logo{

// font-family:'Cormorant Garamond';

// font-size:36px;

// font-weight:700;
// }

// .logo span{

// color:#C3941A;
// }

// .content{

// padding:40px;
// }

// .badge{

// display:inline-block;

// padding:6px 14px;

// border-radius:40px;

// border:1px solid rgba(195,148,26,.35);

// background:rgba(195,148,26,.08);

// color:#C3941A;

// font-size:11px;

// letter-spacing:1px;

// margin-bottom:20px;
// }

// .title{

// font-family:'Cormorant Garamond';

// font-size:32px;

// margin-bottom:15px;
// }

// .title span{

// color:#E8B84B;
// }

// .text{

// font-size:15px;

// color:rgba(255,255,255,.65);

// line-height:1.6;

// margin-bottom:30px;
// }

// .code-box{

// background:

// linear-gradient(
// 145deg,
// rgba(195,148,26,.12),
// rgba(195,148,26,.02)
// );

// border:1px solid rgba(195,148,26,.35);

// border-radius:18px;

// padding:30px;

// margin:20px 0;

// box-shadow:

// inset 0 0 15px rgba(195,148,26,.15);
// }

// .code-label{

// font-size:11px;

// letter-spacing:1px;

// color:#777;

// margin-bottom:10px;

// text-transform:uppercase;
// }

// .code{

// font-size:44px;

// font-weight:700;

// letter-spacing:6px;

// color:#E8B84B;

// text-shadow:

// 0 0 25px rgba(195,148,26,.45);
// }

// .warning{

// font-size:13px;

// color:#ef4444;

// margin-top:20px;
// }

// .note{

// font-size:12px;

// color:#888;

// margin-top:10px;
// }

// .footer{

// background:rgba(0,0,0,.3);

// padding:35px;

// border-top:1px solid rgba(255,255,255,.05);
// }

// .quote{

// font-family:'Cormorant Garamond';

// font-style:italic;

// color:rgba(195,148,26,.6);

// font-size:15px;
// }

// .copyright{

// font-size:11px;

// color:rgba(255,255,255,.25);

// margin-top:20px;
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

// </div>


// <div class="content">


// <div class="badge">

// @if($isAr)

// عضو جديد

// @else

// NEW MEMBER

// @endif

// </div>


// <div class="title">

// @if($isAr)

// أهلاً بك في <span>OpalShot</span>

// @else

// Welcome to <span>OpalShot</span>

// @endif

// </div>


// <div class="text">

// @if($isAr)

// مرحباً {{ $userName }}، استخدم رمز التفعيل التالي لتأكيد حسابك والبدء باستخدام المنصة.

// @else

// Hello {{ $userName }}, use the activation code below to confirm your account and start using the platform.

// @endif

// </div>


// <div class="code-box">

// <div class="code-label">

// Verification Code

// </div>

// <div class="code">

// {{ $code }}

// </div>

// </div>


// <div class="warning">

// @if($isAr)

// الرمز صالح لمدة 3 دقائق فقط

// @else

// This code is valid for 3 minutes only

// @endif

// </div>


// <div class="note">

// @if($isAr)

// إذا لم تطلب هذا الرمز يمكنك تجاهل الرسالة بأمان

// @else

// If you didn’t request this email you can safely ignore it

// @endif

// </div>


// </div>


// <div class="footer">

// <div class="quote">

// "Every photograph is a certificate of presence."

// </div>


// <div class="copyright">

// © {{ date('Y') }} OPALSHOT

// </div>


// </div>


// </div>

// </div>

// </body>

// </html>
