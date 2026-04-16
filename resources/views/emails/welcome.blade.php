// @php
//     $isAr = app()->getLocale() == 'ar';
//     $dir = $isAr ? 'rtl' : 'ltr';
// @endphp
// <!DOCTYPE html>
// <html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
// <head>
//     <meta charset="UTF-8">
//     <meta name="viewport" content="width=device-width, initial-scale=1.0">
//     <title>Welcome to OpalShot</title>
//     <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
//     <style>
//         @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
//         @keyframes scan { 0% { left: -100%; } 100% { left: 100%; } }
//         @keyframes pulse-gold { 0% { box-shadow: 0 0 0 0 rgba(195, 148, 26, 0.4); } 70% { box-shadow: 0 0 0 15px rgba(195, 148, 26, 0); } 100% { box-shadow: 0 0 0 0 rgba(195, 148, 26, 0); } }

//         body { margin: 0; padding: 0; background-color: #050608; font-family: 'DM Sans', Arial, sans-serif; color: #ffffff; }
//         .wrapper { width: 100%; table-layout: fixed; background-color: #050608; padding: 60px 0; }
        
//         .main-card {
//             width: 100%; max-width: 500px; margin: 0 auto;
//             background-color: #11131a;
//             background-image: 
//                 radial-gradient(circle at 100% 0%, rgba(195,148,26,0.08) 0%, transparent 35%),
//                 radial-gradient(circle at 0% 100%, rgba(195,148,26,0.08) 0%, transparent 35%);
//             border-radius: 32px; border: 1px solid rgba(195, 148, 26, 0.1);
//             box-shadow: 0 50px 100px rgba(0,0,0,0.8); overflow: hidden; text-align: center;
//         }

//         .header { padding: 50px 20px 20px; background: radial-gradient(circle at center, rgba(195,148,26,0.1) 0%, transparent 70%); }
//         .aperture-svg { animation: spin 25s linear infinite; }
//         .logo-text { font-family: 'Cormorant Garamond', serif; font-size: 36px; font-weight: 700; color: #ffffff; margin-top: 20px; }
//         .logo-text span { color: #C3941A; }

//         .content { padding: 0 45px 45px; }
        
//         .welcome-title {
//             font-family: 'Cormorant Garamond', serif;
//             font-size: 32px;
//             font-weight: 700;
//             color: #E8B84B;
//             margin: 20px 0;
//             line-height: 1.2;
//         }

//         .welcome-text {
//             font-size: 16px;
//             color: rgba(255, 255, 255, 0.7);
//             line-height: 1.8;
//             margin-bottom: 30px;
//         }

//         .action-btn {
//             display: inline-block; position: relative; padding: 16px 45px;
//             margin-top: 10px; background: #C3941A; color: #11131a;
//             text-decoration: none; font-family: 'DM Sans', sans-serif;
//             font-weight: 700; font-size: 16px; border: none;
//             border-radius: 50px; overflow: hidden; cursor: pointer;
//             transition: all 0.4s ease; animation: pulse-gold 2.5s infinite;
//             text-transform: uppercase;
//             letter-spacing: 1px;
//         }
//         .action-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(195, 148, 26, 0.4); background: #E8B84B; }
//         .action-btn::before {
//             content: ''; position: absolute; top: 0; left: -100%;
//             width: 100%; height: 100%;
//             background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
//             animation: scan 3s infinite;
//         }

//         .divider {
//             height: 1px; width: 60%; background: linear-gradient(90deg, transparent, rgba(195,148,26,0.3), transparent);
//             margin: 40px auto;
//         }

//         .footer { background: rgba(0,0,0,0.4); padding: 40px; border-top: 1px solid rgba(255,255,255,0.05); }
//         .quote { font-family: 'Cormorant Garamond', serif; font-style: italic; color: rgba(255,255,255,0.3); font-size: 15px; }
//     </style>
// </head>
// <body>
//     <div class="wrapper">
//         <table width="100%" cellpadding="0" cellspacing="0">
//             <tr>
//                 <td>
//                     <div class="main-card">
//                         <div class="header">
//                             <svg class="aperture-svg" width="90" height="90" viewBox="0 0 200 200">
//                                 <circle cx="100" cy="100" r="90" fill="none" stroke="rgba(195,148,42,0.15)" stroke-width="1.5"/>
//                                 <circle cx="100" cy="100" r="8" fill="#C3941A" />
//                                 <g opacity="0.8">
//                                     @for ($i = 0; $i < 6; $i++)
//                                         <path d="M100 15 L120 95 L80 95 Z" fill="rgba(195,148,26,0.5)" transform="rotate({{ $i * 60 }} 100 100)"/>
//                                     @endfor
//                                 </g>
//                             </svg>
//                             <div class="logo-text">Opal<span>Shot</span></div>
//                         </div>

//                         <div class="content">
//                             <h1 class="welcome-title">
//                                 @if($isAr) 
//                                     مرحباً بك في مجتمعنا، {{ $userName }}!
//                                 @else 
//                                     Welcome to the community, {{ $userName }}!
//                                 @endif
//                             </h1>
                            
//                             <div class="welcome-text">
//                                 @if($isAr)
//                                     شكراً لانضمامك إلى OpalShot. نحن متحمسون جداً لمشاهدة إبداعاتك ومساعدتك في تنظيم ملفاتك وصورك الأكثر قيمة في خزنتنا المؤمنة.
//                                 @else
//                                     Thank you for joining OpalShot. We're thrilled to see your creativity and help you organize your most precious photos and files in our secure vault.
//                                 @endif
//                             </div>

//                             <a href="{{ config('app.frontend_url') }}" class="action-btn">
//                                 @if($isAr) ابدأ الآن @else Get Started @endif
//                             </a>

//                             <div class="divider"></div>

//                             <p style="font-size: 13px; color: rgba(255,255,255,0.5); line-height: 1.6;">
//                                 @if($isAr)
//                                     إذا كان لديك أي أسئلة، فلا تتردد في الرد على هذه الرسالة. يسعدنا دائماً تقديم المساعدة.
//                                 @else
//                                     If you have any questions, feel free to reply to this email. We're always here to help.
//                                 @endif
//                             </p>
//                         </div>

//                         <div class="footer">
//                              <div class="quote">"Every photograph is a certificate of presence."</div>
//                              <div style="font-size: 11px; color: rgba(195,148,26,0.4); margin-top: 25px; letter-spacing: 1px;">
//                                 &copy; {{ date('Y') }} OPALSHOT PREMIER VAULT
//                              </div>
//                         </div>
//                     </div>
//                 </td>
//             </tr>
//         </table>
//     </div>
// </body>
// </html>
