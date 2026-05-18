<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />

    <title>{{ $title }} | OpalShot</title>

    <!-- Open Graph (WhatsApp, Facebook) -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="OpalShot">
    <meta property="og:locale" content="ar_AR">
    <meta property="og:locale:alternate" content="en_US">
    <meta property="og:title" content="{{ $title }} | OpalShot">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $imageUrl }}">
    <meta property="og:url" content="{{ $redirectUrl }}">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }} | OpalShot">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $imageUrl }}">

    <!-- JavaScript redirect for real browsers -->
    <script>
        window.location.replace("{!! $redirectUrl !!}");
    </script>

    <!-- Fallback meta refresh for clients without JS -->
    <meta http-equiv="refresh" content="0;url={!! $redirectUrl !!}">

    <style>
        body {
            background-color: #0d0f14;
            color: #d4a853;
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            text-align: center;
        }

        a {
            color: #d4a853;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <p>جاري فتح الألبوم... <br><a href="{!! $redirectUrl !!}">اضغط هنا إذا لم يتم توجيهك تلقائياً</a></p>
</body>

</html>