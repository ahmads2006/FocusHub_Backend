<x-app-layout>
    <div style="background-color: #0d0f14; min-height: 100vh; font-family: 'IBM Plex Sans Arabic', sans-serif;" dir="rtl">
        <div class="py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Breadcrumb / Back Navigation -->
                <div style="margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                    <a href="{{ route('images.test.index') }}" style="color: #4a5270; text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                        <span>&rarr;</span>
                        العودة لإدارة الصور
                    </a>
                </div>

                <!-- Album Header -->
                <div style="margin-bottom: 32px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <p style="font-size: 10px; font-weight: 700; letter-spacing: 5px; text-transform: uppercase; color: #d4a853; margin: 0 0 8px 0;">الألبوم</p>
                        <h1 style="font-size: 32px; font-weight: 700; color: #f1f3f9; margin: 0;">{{ $album->title }}</h1>
                        @if($album->description)
                            <p style="color: #8891aa; font-size: 14px; margin-top: 8px; max-width: 600px;">{{ $album->description }}</p>
                        @endif
                        <div style="display: flex; gap: 12px; margin-top: 16px;">
                            <span style="font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: {{ $album->privacy === 'public' ? '#34d399' : ($album->privacy === 'private' ? '#f87171' : '#8891aa') }}; background: {{ $album->privacy === 'public' ? '#0a1f10' : ($album->privacy === 'private' ? '#1a0a0a' : '#13151c') }}; border: 1px solid {{ $album->privacy === 'public' ? '#1a4a22' : ($album->privacy === 'private' ? '#4a1515' : '#1e2130') }}; padding: 4px 10px; border-radius: 6px;">
                                {{ $album->privacy === 'public' ? 'عام' : ($album->privacy === 'private' ? 'خاص' : 'مخفي') }}
                            </span>
                            @if($album->is_collaborative)
                                <span style="font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #60a5fa; background: #0a141f; border: 1px solid #1a2c4a; padding: 4px 10px; border-radius: 6px;">
                                    تعاوني (Collaborative)
                                </span>
                            @endif
                            @if(Auth::id() === $album->user_id)
                                <button onclick="openShareModal('{{ $album->id }}', 'App\\Models\\Album')" style="font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #d4a853; background: transparent; border: 1px solid #d4a853; padding: 4px 10px; border-radius: 6px; cursor: pointer;">
                                    مشاركة الألبوم
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Grid Layout (Main Content & Sidebar) -->
                <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 24px;">
                    
                    <!-- Photos Section -->
                    <div style="display: flex; flex-direction: column; gap: 24px;">
                        
                        <!-- Upload Section (Collaborator/Owner) -->
                        @can('uploadPhoto', $album)
                        <div style="background: #13151c; border: 1px solid #d4a85333; border-radius: 16px; padding: 24px;">
                            <p style="font-size: 10px; font-weight: 700; letter-spacing: 4px; text-transform: uppercase; color: #d4a853; margin: 0 0 16px 0;">إضافة صور للألبوم</p>
                            <form action="{{ route('images.test.store') }}" method="POST" enctype="multipart/form-data" style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                                @csrf
                                <input type="hidden" name="album_id" value="{{ $album->id }}">
                                <div style="flex: 1; min-width: 200px;">
                                    <input type="file" name="image" required style="width: 100%; background: #0d0f14; border: 1px dashed #2e3450; border-radius: 10px; padding: 8px; color: #8891aa; font-size: 12px;">
                                </div>
                                <div style="flex: 1; min-width: 200px;">
                                    <input type="text" name="title" placeholder="عنوان الصورة (اختياري)" style="width: 100%; background: #0d0f14; border: 1px solid #1e2130; border-radius: 10px; padding: 8px 12px; color: #c8cfe0; font-size: 13px;">
                                </div>
                                <button type="submit" style="background: linear-gradient(135deg, #d4a853, #f0c97a); color: #0d0f14; font-size: 11px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; white-space: nowrap;">
                                    رفع ومعالجة
                                </button>
                            </form>
                        </div>
                        @endcan

                        <div style="background: #13151c; border: 1px solid #1e2130; border-radius: 16px; padding: 28px;">
                            <p style="font-size: 10px; font-weight: 700; letter-spacing: 4px; text-transform: uppercase; color: #d4a853; margin: 0 0 20px 0;">الصور ({{ $album->images->count() }})</p>
                            
                            @if($album->images->isEmpty())
                                <div style="text-align: center; padding: 60px 20px; color: #3d4460;">
                                    <div style="font-size: 40px; margin-bottom: 12px; opacity: 0.3;">🖼</div>
                                    <p>لا توجد صور في هذا الألبوم بعد.</p>
                                </div>
                            @else
                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px;">
                                    @foreach($album->images as $image)
                                        <div style="background: #0d0f14; border: 1px solid #1e2130; border-radius: 12px; overflow: hidden; transition: transform 0.2s;">
                                            <img src="{{ $image->url }}" style="width: 100%; aspect-ratio: 1; object-fit: cover;" alt="{{ $image->title }}">
                                            <div style="padding: 12px; display: flex; flex-direction: column; gap: 4px;">
                                                <p style="font-size: 13px; font-weight: 600; color: #c8cfe0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0;">{{ $image->title ?? $image->filename }}</p>
                                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                                    <p style="font-size: 10px; color: #4a5270; margin: 0;">بواسطة: {{ $image->user->name }}</p>
                                                    @can('delete', $image)
                                                        <form action="{{ route('images.test.destroy', $image) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذه الصورة؟');">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" style="background: transparent; border: none; color: #f87171; font-size: 10px; font-weight: 700; cursor: pointer; padding: 2px;">حذف</button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Sidebar: Collaborators -->
                    <div style="display: flex; flex-direction: column; gap: 24px;">
                        
                        <!-- Manage Collaborators (Owner Only) -->
                        @if(Auth::id() === $album->user_id)
                            <div style="background: #13151c; border: 1px solid #1e2130; border-radius: 16px; padding: 24px;">
                                <p style="font-size: 10px; font-weight: 700; letter-spacing: 4px; text-transform: uppercase; color: #d4a853; margin: 0 0 20px 0;">إضافة متعاون</p>
                                
                                <form action="{{ route('albums.collaborators.add', $album) }}" method="POST" style="display: flex; flex-direction: column; gap: 16px;">
                                    @csrf
                                    <div>
                                        <label style="display: block; font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #4a5270; margin-bottom: 8px;">البريد الإلكتروني</label>
                                        <input type="email" name="email" required style="width: 100%; background: #0d0f14; border: 1px solid #1e2130; border-radius: 10px; padding: 11px 14px; font-size: 13px; color: #c8cfe0; outline: none;" placeholder="example@mail.com">
                                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #4a5270; margin-bottom: 8px;">الدور (Role)</label>
                                        <select name="role" required style="width: 100%; background: #0d0f14; border: 1px solid #1e2130; border-radius: 10px; padding: 11px 14px; font-size: 13px; color: #c8cfe0; outline: none;">
                                            <option value="viewer">مشاهد (Viewer)</option>
                                            <option value="contributor">مساهم (Contributor)</option>
                                            <option value="admin">مشرف (Admin)</option>
                                        </select>
                                    </div>
                                    <button type="submit" style="background: linear-gradient(135deg, #d4a853, #f0c97a); color: #0d0f14; font-size: 12px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; padding: 13px; border: none; border-radius: 10px; cursor: pointer;">
                                        إضافة المتعاون
                                    </button>
                                </form>
                            </div>
                        @endif

                        <!-- Collaborators List -->
                        <div style="background: #13151c; border: 1px solid #1e2130; border-radius: 16px; padding: 24px;">
                            <p style="font-size: 10px; font-weight: 700; letter-spacing: 4px; text-transform: uppercase; color: #d4a853; margin: 0 0 20px 0;">المتعاونون</p>
                            
                            <div style="display: flex; flex-direction: column; gap: 16px;">
                                <!-- Owner -->
                                <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 12px; border-bottom: 1px solid #1e2130;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 32px; height: 32px; border-radius: 50%; background: #1e2130; border: 1px solid #d4a853; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #d4a853;">
                                            {{ mb_substr($album->owner->name, 0, 1) }}
                                        </div>
                                        <div>
                                            <p style="font-size: 13px; font-weight: 600; color: #f1f3f9; margin: 0;">{{ $album->owner->name }}</p>
                                            <p style="font-size: 11px; color: #d4a853; margin: 0;">المالك</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Others -->
                                @foreach($album->collaborators as $collaborator)
                                    <div style="display: flex; align-items: center; justify-content: space-between;">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #0d0f14; border: 1px solid #1e2130; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #8891aa;">
                                                {{ mb_substr($collaborator->name, 0, 1) }}
                                            </div>
                                            <div>
                                                <p style="font-size: 13px; font-weight: 600; color: #c8cfe0; margin: 0;">{{ $collaborator->name }}</p>
                                                <p style="font-size: 11px; color: #4a5270; margin: 0;">{{ Str::title($collaborator->pivot->role) }}</p>
                                            </div>
                                        </div>
                                        
                                        @if(Auth::id() === $album->user_id)
                                            <form action="{{ route('albums.collaborators.remove', [$album, $collaborator]) }}" method="POST">
                                                @csrf @method('DELETE')
                                                <button type="submit" style="background: transparent; border: none; color: #f87171; font-size: 16px; cursor: pointer; padding: 4px;" title="إزالة">
                                                    &times;
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    @include('shared_links._generate_modal')
</x-app-layout>
