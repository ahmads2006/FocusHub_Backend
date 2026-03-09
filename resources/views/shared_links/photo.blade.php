<x-guest-layout>
    <div style="min-height: 100vh; background: #0d0f14; color: #f1f3f9; display: flex; align-items: center; justify-content: center; font-family: 'IBM Plex Sans Arabic', sans-serif;" dir="rtl">
        <div style="max-width: 900px; margin: 0 auto; padding: 40px 20px; width: 100%;">
            <div style="background: #13151c; border: 1px solid #1e2130; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
                <div style="position: relative;">
                    <img src="{{ $photo->url }}" alt="{{ $photo->title }}" style="width: 100%; max-height: 70vh; object-fit: contain; background: #000;">
                </div>
                <div style="padding: 32px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                        <div>
                            <h1 style="font-size: 24px; font-weight: 700; color: #d4a853; margin-bottom: 8px;">{{ $photo->title }}</h1>
                            <p style="color: #c8cfe0; line-height: 1.6;">{{ $photo->description }}</p>
                        </div>
                        <div style="text-align: left;">
                            <span style="display: block; font-size: 12px; color: #4a5270;">تحميلات: {{ $photo->downloads_count }}</span>
                            <span style="display: block; font-size: 12px; color: #4a5270;">مشاهدات: {{ $photo->views_count }}</span>
                        </div>
                    </div>
                    
                    @if(!empty($photo->metadata))
                    <div style="background: #0d0f14; border: 1px solid #1e2130; border-radius: 12px; padding: 16px; margin-top: 16px; margin-bottom: 24px; display: flex; gap: 24px; flex-wrap: wrap;">
                        @if(isset($photo->metadata['CameraModel']))
                        <div>
                            <span style="display: block; font-size: 10px; color: #4a5270; text-transform: uppercase; letter-spacing: 1px;">الكاميرا</span>
                            <span style="font-size: 13px; font-weight: 600; color: #c8cfe0;">{{ $photo->metadata['CameraModel'] }}</span>
                        </div>
                        @endif
                        @if(isset($photo->metadata['ApertureValue']))
                        <div>
                            <span style="display: block; font-size: 10px; color: #4a5270; text-transform: uppercase; letter-spacing: 1px;">الفتحة</span>
                            <span style="font-size: 13px; font-weight: 600; color: #c8cfe0;">{{ $photo->metadata['ApertureValue'] }}</span>
                        </div>
                        @endif
                        @if(isset($photo->metadata['ShutterSpeed']))
                        <div>
                            <span style="display: block; font-size: 10px; color: #4a5270; text-transform: uppercase; letter-spacing: 1px;">السرعة</span>
                            <span style="font-size: 13px; font-weight: 600; color: #c8cfe0;">{{ $photo->metadata['ShutterSpeed'] }}s</span>
                        </div>
                        @endif
                        @if(isset($photo->metadata['ISO']))
                        <div>
                            <span style="display: block; font-size: 10px; color: #4a5270; text-transform: uppercase; letter-spacing: 1px;">ISO</span>
                            <span style="font-size: 13px; font-weight: 600; color: #c8cfe0;">{{ $photo->metadata['ISO'] }}</span>
                        </div>
                        @endif
                    </div>
                    @endif
                    
                    <div style="border-top: 1px solid #1e2130; padding-top: 24px; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 40px; height: 40px; background: #1e2130; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #d4a853; font-weight: 700;">
                                {{ mb_substr($photo->user->name, 0, 1) }}
                            </div>
                            <div>
                                <span style="display: block; font-size: 14px; font-weight: 600; color: #f1f3f9;">{{ $photo->user->name }}</span>
                                <span style="display: block; font-size: 12px; color: #4a5270;">تم الرفع في {{ $photo->created_at->format('Y-m-d') }}</span>
                            </div>
                        </div>
                        
                        @if($link->permission === 'download')
                            <a href="{{ $photo->url }}" download class="btn" 
                               style="background: #d4a853; color: #0d0f14; padding: 10px 24px; border-radius: 10px; font-weight: 700; text-decoration: none; transition: opacity 0.2s;">
                                تحميل الصورة
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
