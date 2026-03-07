<x-guest-layout>
    <div style="min-height: 100vh; background: #0d0f14; color: #f1f3f9; font-family: 'IBM Plex Sans Arabic', sans-serif;" dir="rtl">
        <div style="max-width: 1200px; margin: 0 auto; padding: 40px 20px;">
            <div style="margin-bottom: 40px; text-align: center;">
                <h1 style="font-size: 32px; font-weight: 700; margin-bottom: 12px; color: #d4a853;">{{ $album->title }}</h1>
                <p style="color: #8891aa; font-size: 16px;">{{ $album->description }}</p>
                <div style="margin-top: 16px; font-size: 13px; color: #4a5270;">
                    بواسطة: {{ $album->owner->name }} • {{ $album->photos->count() }} صورة
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px;">
                @foreach($album->photos as $photo)
                    <div style="background: #13151c; border: 1px solid #1e2130; border-radius: 12px; overflow: hidden;">
                        <img src="{{ $photo->url }}" alt="{{ $photo->title }}" style="width: 100%; height: 200px; object-fit: cover;">
                        <div style="padding: 16px;">
                            <h3 style="font-size: 14px; font-weight: 600; margin-bottom: 4px;">{{ $photo->title }}</h3>
                            <p style="font-size: 12px; color: #8891aa;">{{ Str::limit($photo->description, 50) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($album->photos->isEmpty())
                <div style="text-align: center; padding: 100px 0; color: #4a5270;">
                    لا توجد صور في هذا الألبوم بعد.
                </div>
            @endif
        </div>
    </div>
</x-guest-layout>
