@extends('layouts.premium')

@section('title', $album->title)

@section('content')
<div class="space-y-8" dir="rtl" x-data="{ }">
    
    <!-- Breadcrumb / Header -->
    <div class="flex justify-between items-end">
        <div>
            <div class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">
                <a href="{{ route('images.index') }}" class="hover:text-purple-400 transition-colors">إدارة الأصول</a>
                <span>/</span>
                <span class="text-purple-500">الألبومات</span>
            </div>
            <h2 class="text-3xl font-bold tracking-tight">{{ $album->title }}</h2>
            <p class="text-gray-400 mt-1">{{ $album->description ?? 'مجموعة فوتوغرافية احترافية.' }}</p>
        </div>
        
        <div class="flex items-center gap-4">
            <span class="px-4 py-2 glass rounded-2xl text-[10px] font-bold uppercase tracking-widest {{ $album->privacy === 'public' ? 'text-green-400 border-green-500/20' : 'text-red-400 border-red-500/20' }}">
                {{ $album->privacy === 'public' ? 'Public Vault' : 'Private Vault' }}
            </span>
            @if(Auth::id() === $album->user_id)
                <button onclick="openShareModal('{{ $album->id }}', 'App\\Models\\Album')" class="p-3 px-6 glass rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-white/5 transition-all">مشاركة الألبوم</button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Main Photos Area -->
        <div class="lg:col-span-8 space-y-8">
            
            <!-- Upload in Album -->
            @can('uploadPhoto', $album)
            <div class="glass p-8 rounded-[40px] border-purple-500/10 border relative overflow-hidden">
                <h3 class="text-xs font-bold uppercase tracking-widest text-purple-400 mb-6">إضافة أصل جديد للألبوم</h3>
                <form action="{{ route('images.store') }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap gap-6 items-end">
                    @csrf
                    <input type="hidden" name="album_id" value="{{ $album->id }}">
                    <div class="flex-1 min-w-[200px] space-y-2">
                         <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">ملف الصورة</label>
                         <input type="file" name="image" required class="w-full bg-white/5 border border-white/10 rounded-2xl p-2 text-xs text-gray-400">
                    </div>
                    <div class="flex-1 min-w-[200px] space-y-2">
                         <label class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">العنوان</label>
                         <input type="text" name="title" placeholder="بلا عنوان..." class="w-full bg-white/5 border border-white/10 rounded-2xl p-3 px-4 outline-none focus:ring-2 focus:ring-purple-500/50 transition-all text-xs">
                    </div>
                    <button type="submit" class="accent-gradient p-4 px-8 rounded-2xl font-bold uppercase tracking-widest text-xs shadow-lg shadow-purple-500/20 hover:scale-[1.02] transition-transform">رفع ومعالجة</button>
                </form>
            </div>
            @endcan

            <!-- Photo Grid -->
            <div class="glass p-8 rounded-[40px] min-h-[400px]">
                <div class="flex justify-between items-center mb-8">
                    <h3 class="text-xs font-bold uppercase tracking-widest text-gray-500">محتوى الألبوم ({{ $album->images->count() }})</h3>
                </div>

                @if($album->images->isEmpty())
                    <div class="h-64 flex flex-col items-center justify-center opacity-40">
                        <svg class="w-16 h-16 mb-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <p class="font-bold">الألبوم فارغ حالياً.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($album->images as $image)
                            <div class="glass group rounded-[32px] overflow-hidden border border-white/5 hover:border-purple-500/30 transition-all">
                                <div class="relative h-40 overflow-hidden">
                                     <img src="{{ $image->url }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-1000">
                                     <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent flex flex-col justify-end p-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                        @can('delete', $image)
                                            <form action="{{ route('images.destroy', $image) }}" method="POST" onsubmit="return confirm('حذف؟')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="w-full text-[8px] font-bold text-red-400 bg-red-400/10 p-2 rounded-xl hover:bg-red-400/20 transition-all uppercase tracking-widest">حذف</button>
                                            </form>
                                        @endcan
                                     </div>
                                </div>
                                <div class="p-4">
                                    <p class="text-xs font-bold truncate">{{ $image->title ?? $image->filename }}</p>
                                    <p class="text-[8px] text-gray-600 mt-1 uppercase font-mono">بواسطة: {{ $image->user->name }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar: Collaboration -->
        <aside class="lg:col-span-4 space-y-8">
            
            <!-- Collaborators Management -->
            <div class="glass p-8 rounded-[40px] space-y-8">
                <div class="flex justify-between items-center">
                    <h3 class="text-xs font-bold uppercase tracking-widest text-purple-400">المتعاونون</h3>
                    <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                </div>

                <div class="space-y-4">
                    <!-- Owner -->
                    <div class="flex items-center gap-4 p-4 rounded-3xl bg-white/5 border border-white/5">
                        <div class="w-10 h-10 rounded-2xl accent-gradient flex items-center justify-center font-bold text-white">
                            {{ mb_substr($album->owner->name, 0, 1) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold truncate">{{ $album->owner->name }}</p>
                            <p class="text-[8px] text-purple-400 uppercase font-bold tracking-widest">المؤسس</p>
                        </div>
                    </div>

                    <!-- Collaborator List -->
                    @foreach($album->collaborators as $collaborator)
                        <div class="flex items-center gap-4 p-4 rounded-3xl bg-white/5 border border-white/5 group">
                            <div class="w-10 h-10 rounded-2xl glass-dark border border-white/10 flex items-center justify-center font-bold text-gray-400">
                                {{ mb_substr($collaborator->name, 0, 1) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold truncate">{{ $collaborator->name }}</p>
                                <p class="text-[8px] text-gray-500 uppercase font-bold tracking-widest">{{ $collaborator->pivot->role }}</p>
                            </div>
                            @if(Auth::id() === $album->user_id)
                                <form action="{{ route('albums.collaborators.remove', [$album, $collaborator]) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-gray-600 hover:text-red-400 transition-colors">&times;</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- Invite Form -->
                @if(Auth::id() === $album->user_id)
                <div class="pt-8 border-t border-white/5">
                    <h4 class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-4">دعوة عضو جديد</h4>
                    <form action="{{ route('albums.collaborators.add', $album) }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="email" name="email" required placeholder="البريد الإلكتروني..." class="w-full bg-white/5 border border-white/10 rounded-2xl p-3 px-4 text-xs outline-none focus:ring-2 focus:ring-purple-500/50">
                        <select name="role" class="w-full bg-white/5 border border-white/10 rounded-2xl p-3 px-4 text-xs outline-none focus:ring-2 focus:ring-purple-500/50 h-11">
                            <option value="viewer" class="bg-black">مشاهد</option>
                            <option value="contributor" class="bg-black">مساهم</option>
                            <option value="admin" class="bg-black">مشرف</option>
                        </select>
                        <button type="submit" class="w-full bg-white/5 border border-white/10 hover:border-purple-500/50 hover:bg-purple-500/5 text-[10px] font-bold uppercase tracking-widest p-4 rounded-2xl transition-all">إرسال الدعوة</button>
                    </form>
                </div>
                @endif
            </div>

        </aside>
    </div>

</div>

@include('shared_links._generate_modal')
@endsection
