@extends('layouts.premium')

@section('title', 'Photography Vault')

@section('content')
<div class="space-y-8 h-full flex flex-col" x-data="{ view: 'grid' }">
    
    <!-- Gallery Header -->
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">Your <span class="accent-text-gradient">Vault</span></h2>
            <p class="text-gray-400 mt-1">Manage and showcase your professional assets.</p>
        </div>
        
        <div class="flex items-center gap-3 glass p-1 rounded-2xl">
            <button @click="view = 'grid'" :class="view === 'grid' ? 'bg-white/10 text-white' : 'text-gray-500 hover:text-gray-300'" class="p-2 px-4 rounded-xl text-xs font-bold uppercase transition-all">Grid</button>
            <button @click="view = 'list'" :class="view === 'list' ? 'bg-white/10 text-white' : 'text-gray-500 hover:text-gray-300'" class="p-2 px-4 rounded-xl text-xs font-bold uppercase transition-all">List</button>
        </div>
    </div>

    <!-- Gallery Grid -->
    <div class="flex-1 min-h-0 overflow-y-auto pr-2">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            @forelse($images as $image)
                <div class="glass group rounded-3xl overflow-hidden hover:scale-[1.02] transition-all duration-500">
                    <div class="relative h-48 overflow-hidden">
                        <img src="{{ $image->url }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-end p-4">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold text-white/50 bg-black/40 backdrop-blur-md px-2 py-1 rounded">{{ $image->file_type }}</span>
                                <div class="flex gap-2">
                                    <button class="p-2 glass rounded-lg hover:bg-white/10 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    </button>
                                    <a href="{{ $image->getOriginalUrl() }}" class="p-2 glass rounded-lg hover:bg-purple-500/20 hover:text-purple-400 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <h4 class="font-bold truncate text-sm">{{ $image->title }}</h4>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-[10px] text-gray-500 font-mono">{{ $image->created_at->format('M d, Y') }}</span>
                            <span class="text-[10px] font-bold text-purple-400 uppercase tracking-widest">{{ $image->privacy }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full h-64 glass rounded-3xl flex flex-col items-center justify-center opacity-40">
                    <svg class="w-16 h-16 mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <p class="font-bold">Vault is currently empty.</p>
                </div>
            @endforelse
        </div>
        
        <div class="mt-8">
            {{ $images->links() }}
        </div>
    </div>
</div>
@endsection