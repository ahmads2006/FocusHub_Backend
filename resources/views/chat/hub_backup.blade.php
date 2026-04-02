@extends('layouts.premium')
@section('title', 'Photographers Hub')

@section('content')
<div x-data="chatHub()" x-init="init()" class="h-full flex gap-6 relative overflow-hidden" x-cloak>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- LEFT PANEL: Connections List --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <div class="w-full lg:w-[380px] flex flex-col shrink-0" :class="{ 'hidden lg:flex': activeChat }">

        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight">
                Photographers <span class="accent-text-gradient">Hub</span>
            </h1>
            <p class="text-gray-500 text-sm mt-1">تواصل مع مصورين متابعينك</p>
        </div>

        {{-- Search --}}
        <div class="relative mb-4">
            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input type="text" x-model="searchQuery" placeholder="ابحث عن مصور..." class="w-full bg-white/5 border border-white/10 rounded-xl py-2.5 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500/50 transition-all placeholder-gray-600">
        </div>

        {{-- Connections Grid / List --}}
        <div class="flex-1 overflow-y-auto space-y-3 pr-1 custom-scrollbar">
            @forelse($connections as $connection)
            <div
                x-show="'{{ strtolower($connection->name) }}'.includes(searchQuery.toLowerCase()) || searchQuery === ''"
                @click="openChat('{{ $connection->id }}', '{{ addslashes($connection->name) }}', '{{ $connection->avatar }}', {{ $onlineMap[$connection->id] ? 'true' : 'false' }})"
                data-partner-id="{{ $connection->id }}"
                class="glass rounded-2xl p-4 cursor-pointer hover:bg-white/[0.06] transition-all duration-300 group glow"
                :class="activeChat?.id === '{{ $connection->id }}' ? 'bg-white/[0.08] border-purple-500/30' : ''"
            >
                <div class="flex items-center gap-4">
                    {{-- Avatar with Online Dot --}}
                    <div class="relative shrink-0">
                        <img src="{{ $connection->avatar }}" alt="{{ $connection->name }}"
                             class="w-12 h-12 rounded-full border-2 border-white/10 group-hover:border-purple-500/50 transition-all object-cover">
                        @if($onlineMap[$connection->id])
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-[#0a0a0c]" style="box-shadow: 0 0 12px rgba(34, 197, 94, 0.6);"></span>
                        @else
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-gray-600 rounded-full border-2 border-[#0a0a0c]"></span>
                        @endif
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold truncate group-hover:text-white transition-colors">{{ $connection->name }}</h3>
                            @if(isset($unreadCounts[$connection->id]) && $unreadCounts[$connection->id] > 0)
                            <span class="inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold text-white accent-gradient rounded-full shrink-0">
                                {{ $unreadCounts[$connection->id] }}
                            </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs text-gray-500">{{ $connection->images_count }} صورة</span>
                            <span class="text-gray-700">•</span>
                            <span class="text-xs {{ $onlineMap[$connection->id] ? 'text-emerald-400' : 'text-gray-600' }}">
                                {{ $onlineMap[$connection->id] ? 'متصل الآن' : 'غير متصل' }}
                            </span>
                        </div>
                    </div>

                    {{-- Arrow --}}
                    <svg class="w-4 h-4 text-gray-600 group-hover:text-purple-400 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </div>
            </div>
            @empty
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="w-20 h-20 rounded-full bg-white/5 flex items-center justify-center mb-4">
                    <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
                <h3 class="text-gray-400 font-semibold mb-1">لا توجد اتصالات بعد</h3>
                <p class="text-gray-600 text-sm max-w-xs">ابدأ بمتابعة مصورين من المعرض العام لبدء محادثات معهم.</p>
                <a href="{{ route('images.gallery') }}" class="mt-4 px-5 py-2 accent-gradient rounded-xl text-sm font-semibold text-white hover:opacity-90 transition-opacity">
                    استكشف المعرض
                </a>
            </div>
            @endforelse
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- RIGHT PANEL: Chat Interface --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <div class="flex-1 flex flex-col" x-show="activeChat" x-transition.opacity.duration.200ms>

        {{-- Chat Header --}}
        <div class="glass rounded-2xl p-4 mb-4 flex items-center gap-4">
            {{-- Back Button (Mobile) --}}
            <button @click="closeChat()" class="lg:hidden p-2 rounded-xl hover:bg-white/10 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>

            {{-- Partner Info --}}
            <div class="relative">
                <img :src="activeChat?.avatar" :alt="activeChat?.name" class="w-10 h-10 rounded-full border-2 border-white/10 object-cover">
                <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-[#0a0a0c]"
                      :class="partnerOnline ? 'bg-emerald-500' : 'bg-gray-600'"
                      :style="partnerOnline ? 'box-shadow: 0 0 12px rgba(34, 197, 94, 0.6)' : ''"></span>
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-semibold" x-text="activeChat?.name"></h3>
                <span class="text-xs" :class="partnerOnline ? 'text-emerald-400' : 'text-gray-500'" x-text="partnerOnline ? 'متصل الآن' : 'غير متصل'"></span>
            </div>

            {{-- Profile Link --}}
            <a :href="'/photographer/' + activeChat?.id" class="p-2 rounded-xl text-gray-500 hover:text-purple-400 hover:bg-white/5 transition-all" title="عرض الملف الشخصي">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </a>
        </div>

        {{-- Messages Area --}}
        <div class="flex-1 glass rounded-2xl p-4 mb-4 overflow-y-auto flex flex-col" id="chat-messages" x-ref="chatMessages">

            {{-- Loading Skeleton --}}
            <template x-if="loadingMessages">
                <div class="flex-1 flex flex-col justify-center items-center gap-3">
                    <div class="flex gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-purple-500/50 animate-bounce" style="animation-delay:0ms"></div>
                        <div class="w-2.5 h-2.5 rounded-full bg-purple-500/50 animate-bounce" style="animation-delay:150ms"></div>
                        <div class="w-2.5 h-2.5 rounded-full bg-purple-500/50 animate-bounce" style="animation-delay:300ms"></div>
                    </div>
                    <p class="text-gray-500 text-sm">جاري تحميل المحادثة...</p>
                </div>
            </template>

            {{-- Empty State --}}
            <template x-if="!loadingMessages && messages.length === 0">
                <div class="flex-1 flex flex-col justify-center items-center text-center">
                    <div class="w-16 h-16 rounded-full bg-white/5 flex items-center justify-center mb-3">
                        <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    </div>
                    <p class="text-gray-500 text-sm">ابدأ محادثة جديدة! 👋</p>
                </div>
            </template>

            {{-- Messages List --}}
            <template x-if="!loadingMessages && messages.length > 0">
                <div class="flex flex-col gap-3 mt-auto">
                    <template x-for="(msg, index) in messages" :key="msg.id">
                        <div>
                            {{-- Date Separator --}}
                            <template x-if="index === 0 || msg.date !== messages[index - 1]?.date">
                                <div class="flex items-center gap-3 my-3">
                                    <div class="flex-1 h-px bg-white/5"></div>
                                    <span class="text-[10px] text-gray-600 uppercase tracking-wider" x-text="formatDate(msg.date)"></span>
                                    <div class="flex-1 h-px bg-white/5"></div>
                                </div>
                            </template>

                            {{-- Message Bubble --}}
                            <div class="flex flex-col" :class="msg.is_mine ? 'items-end' : 'items-start'">
                                <div class="max-w-[75%] overflow-hidden rounded-2xl"
                                     :class="msg.is_mine
                                        ? 'bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-br-md shadow-lg shadow-purple-500/10'
                                        : 'bg-white/[0.06] text-gray-200 rounded-bl-md border border-white/5'">

                                    {{-- Shared Image --}}
                                    <template x-if="msg.image_id">
                                        <div class="p-1">
                                            <a :href="msg.image_url" target="_blank" class="block relative group overflow-hidden rounded-xl">
                                                <img :src="msg.thumb_url" class="w-full max-h-64 object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">
                                                <div class="absolute inset-0 bg-black/20 group-hover:bg-transparent transition-colors"></div>
                                            </a>
                                        </div>
                                    </template>

                                    {{-- Message Body --}}
                                    <template x-if="msg.body">
                                        <div class="px-4 py-2.5 text-sm leading-relaxed">
                                            <p x-text="msg.body"></p>
                                            
                                            {{-- Interactive Invitation Buttons --}}
                                            <template x-if="msg.album_id && !msg.is_mine">
                                                <div class="mt-3 flex gap-2">
                                                    <button @click="respondToInvitation(msg, 'accept')" 
                                                            class="px-4 py-1.5 bg-white text-purple-600 font-bold rounded-lg text-[11px] hover:bg-gray-100 transition-all shadow-md">
                                                        Accept Invitation
                                                    </button>
                                                    <button @click="respondToInvitation(msg, 'decline')" 
                                                            class="px-4 py-1.5 bg-black/20 text-white font-bold rounded-lg text-[11px] hover:bg-black/40 transition-all">
                                                        Decline
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    {{-- Metadata --}}
                                    <div class="px-3 pb-1.5 flex items-center gap-1" :class="msg.is_mine ? 'justify-end' : 'justify-start'">
                                        <span class="text-[9px] opacity-60" x-text="msg.created_at"></span>
                                        <template x-if="msg.is_mine">
                                            <svg class="w-3 h-3 opacity-60" :class="msg.is_read ? 'text-blue-200' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- Message Input --}}
        <div class="glass rounded-2xl p-3 flex items-center gap-3">
            {{-- Media Button --}}
            <button
                @click="showMediaPicker = true"
                class="p-2.5 rounded-xl text-gray-400 hover:text-white hover:bg-white/5 transition-all"
                title="مشاركة صورة من المعرض"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </button>

            <input
                type="text"
                x-model="newMessage"
                @keydown.enter.prevent="sendMessage()"
                placeholder="اكتب رسالتك..."
                class="flex-1 bg-transparent border-none text-sm focus:outline-none focus:ring-0 placeholder-gray-600"
                :disabled="sendingMessage"
            >

            <button
                @click="sendMessage()"
                :disabled="!newMessage.trim() || sendingMessage"
                class="p-2.5 accent-gradient rounded-xl text-white disabled:opacity-30 hover:opacity-90 transition-all disabled:cursor-not-allowed"
            >
                <svg class="w-5 h-5 rotate-[-45deg]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
            </button>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- MODAL: Media Picker --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <div
        x-show="showMediaPicker"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @keydown.escape.window="showMediaPicker = false"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
    >
        <div class="glass w-full max-w-2xl rounded-[32px] overflow-hidden flex flex-col max-h-[80vh] shadow-2xl border border-white/10" @click.away="showMediaPicker = false">
            {{-- Header --}}
            <div class="p-6 border-b border-white/5 flex justify-between items-center bg-white/[0.02]">
                <div>
                    <h3 class="text-xl font-bold">معرض الصور الخاص بك</h3>
                    <p class="text-xs text-gray-500 mt-1">اختر صورة لمشاركتها فوراً</p>
                </div>
                <button @click="showMediaPicker = false" class="p-2 rounded-full hover:bg-white/10 text-gray-500 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            {{-- Media Grid --}}
            <div class="flex-1 overflow-y-auto p-4 custom-scrollbar bg-black/20">
                <template x-if="loadingMedia">
                    <div class="grid grid-cols-3 gap-3">
                        <template x-for="i in 6">
                            <div class="aspect-square bg-white/[0.03] rounded-2xl animate-pulse"></div>
                        </template>
                    </div>
                </template>

                <template x-if="!loadingMedia && myMedia.length > 0">
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                        <template x-for="item in myMedia" :key="item.id">
                            <button
                                @click="shareMedia(item.id)"
                                class="aspect-square rounded-2xl overflow-hidden relative group border-2 border-transparent hover:border-purple-500/50 transition-all"
                            >
                                <img :src="item.thumb" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                    <svg class="w-8 h-8 text-white translate-y-2 group-hover:translate-y-0 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                </div>
                            </button>
                        </template>
                    </div>
                </template>

                <template x-if="!loadingMedia && myMedia.length === 0">
                    <div class="py-20 text-center">
                        <p class="text-gray-500">لم تقم برفع أي صور بعد.</p>
                    </div>
                </template>

                {{-- Pagination Trigger --}}
                <template x-if="mediaHasMore && !loadingMedia">
                    <button @click="loadMedia(mediaPage + 1)" class="w-full py-4 text-xs font-bold text-gray-500 hover:text-white transition-colors">تحميل المزيد...</button>
                </template>
            </div>
        </div>
    </div>

    {{-- Empty State (No Chat Selected - Desktop) --}}
    <div class="hidden lg:flex flex-1 items-center justify-center" x-show="!activeChat">
        <div class="text-center">
            <div class="w-24 h-24 rounded-full bg-white/[0.03] flex items-center justify-center mx-auto mb-4 border border-white/5">
                <svg class="w-12 h-12 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
            </div>
            <h3 class="text-gray-500 font-semibold mb-1">اختر محادثة</h3>
            <p class="text-gray-600 text-sm">اضغط على أي مصور من القائمة لبدء المحادثة</p>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function chatHub() {
    return {
        searchQuery: '',
        activeChat: null,
        messages: [],
        newMessage: '',
        loadingMessages: false,
        sendingMessage: false,
        partnerOnline: false,
        pollInterval: null,
        lastMessageId: 0,

        // Media Picker State
        showMediaPicker: false,
        myMedia: [],
        loadingMedia: false,
        mediaPage: 1,
        mediaHasMore: false,

        init() {
            window.addEventListener('beforeunload', () => this.stopPolling());

            // Handle auto-opening chat from notification redirect
            const initialPartnerId = '{{ $partnerId }}';
            if (initialPartnerId) {
                this.$nextTick(() => {
                    const conn = document.querySelector(`[data-partner-id="${initialPartnerId}"]`);
                    if (conn) conn.click();
                });
            }

            // Load media when modal opens
            this.$watch('showMediaPicker', value => {
                if (value && this.myMedia.length === 0) {
                    this.loadMedia(1);
                }
            });
        },

        async openChat(id, name, avatar, isOnline) {
            this.stopPolling();
            this.activeChat = { id, name, avatar };
            this.partnerOnline = isOnline;
            this.messages = [];
            this.lastMessageId = 0;
            this.loadingMessages = true;

            try {
                const res = await fetch(`/api/chat/messages/${id}`, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                });
                const data = await res.json();
                this.messages = data.messages;
                this.partnerOnline = data.partner.is_online;

                if (this.messages.length > 0) {
                    this.lastMessageId = this.messages[this.messages.length - 1].id;
                }

                this.$nextTick(() => this.scrollToBottom());
            } catch (e) {
                console.error('Failed to load messages:', e);
            } finally {
                this.loadingMessages = false;
            }

            this.startPolling();
        },

        closeChat() {
            this.stopPolling();
            this.activeChat = null;
            this.messages = [];
        },

        async sendMessage() {
            if (!this.newMessage.trim() || this.sendingMessage) return;
            const body = this.newMessage.trim();
            this.newMessage = '';
            await this.performSend({ body });
        },

        async shareMedia(imageId) {
            this.showMediaPicker = false;
            await this.performSend({ image_id: imageId });
        },

        async performSend(payload) {
            this.sendingMessage = true;
            try {
                const res = await fetch('/api/chat/send', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        receiver_id: this.activeChat.id,
                        ...payload
                    }),
                });

                const msg = await res.json();
                if (res.ok) {
                    this.messages.push(msg);
                    this.lastMessageId = msg.id;
                    this.$nextTick(() => this.scrollToBottom());
                }
            } catch (e) {
                console.error('Send failed:', e);
            } finally {
                this.sendingMessage = false;
            }
        },

        async loadMedia(page) {
            this.loadingMedia = true;
            try {
                const res = await fetch(`/api/chat/my-images?page=${page}`);
                const data = await res.json();
                if (page === 1) this.myMedia = data.data;
                else this.myMedia = [...this.myMedia, ...data.data];

                this.mediaPage = page;
                this.mediaHasMore = data.next_page_url !== null;
            } catch (e) {
                console.error('Failed to load media:', e);
            } finally {
                this.loadingMedia = false;
            }
        },

        startPolling() {
            this.pollInterval = setInterval(() => this.pollMessages(), 3000);
        },

        stopPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },

        async pollMessages() {
            if (!this.activeChat) return;
            try {
                const res = await fetch(`/api/chat/poll/${this.activeChat.id}?after_id=${this.lastMessageId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.partnerOnline = data.is_online;

                if (data.messages.length > 0) {
                    const existingIds = new Set(this.messages.map(m => m.id));
                    const newMessages = data.messages.filter(m => !existingIds.has(m.id));

                    if (newMessages.length > 0) {
                        this.messages = [...this.messages, ...newMessages];
                        this.lastMessageId = this.messages[this.messages.length - 1].id;
                        this.$nextTick(() => this.scrollToBottom());
                    }
                }
            } catch (e) {}
        },

        async respondToInvitation(msg, action) {
            const albumId = msg.album_id;
            const url = action === 'accept' ? `/albums/${albumId}/invitation/accept` : `/albums/${albumId}/invitation/decline`;
            
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: action === 'accept' ? 'تم قبول الدعوة!' : 'تم رفض الدعوة',
                        text: data.message,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: true,
                        confirmButtonText: 'إغلاق',
                        background: '#1a1a1c',
                        color: '#fff'
                    });
                    
                    // We can mark the message as responded locally if we want, 
                    // but for now, the toast is the primary feedback.
                } else {
                    Swal.fire({ icon: 'error', title: 'خطأ', text: data.message });
                }
            } catch (e) {
                console.error('Failed to respond to invitation:', e);
            }
        },

        scrollToBottom() {
            const el = this.$refs.chatMessages;
            if (el) el.scrollTop = el.scrollHeight;
        },

        formatDate(dateStr) {
            const today = new Date().toISOString().split('T')[0];
            const yesterday = new Date(Date.now() - 86400000).toISOString().split('T')[0];
            if (dateStr === today) return 'اليوم';
            if (dateStr === yesterday) return 'أمس';
            return dateStr;
        },
    };
}
</script>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.05); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.1); }
</style>
@endsection
