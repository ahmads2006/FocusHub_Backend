<?php

$file = 'resources/views/chat/hub.blade.php';
$content = file_get_contents($file);

// 1. Add Support Card for everyone at the top of the connections list
$connectionsListStart = '{{-- Connections Grid / List --}}
        <div class="flex-1 overflow-y-auto space-y-3 pr-1 custom-scrollbar">';

$supportCard = '
            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- ADMIN SUPPORT PENDING LIST (ONLY FOR ADMINS) --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            @if(isset($isAdmin) && $isAdmin)
            <div x-show="adminPending.length > 0 || adminActive.length > 0" class="mb-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">طلبات الدعم (للإدارة)</h3>
                
                {{-- Admin Pending --}}
                <template x-for="req in adminPending" :key="req.id">
                    <div @click="openAdminSupport(req, false)" class="glass rounded-2xl p-4 cursor-pointer hover:bg-white/[0.06] transition-all bg-yellow-500/10 border border-yellow-500/20 mb-2">
                        <div class="flex items-center gap-4">
                            <img :src="req.user_avatar" class="w-10 h-10 rounded-full object-cover">
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-center"><h3 class="text-sm font-semibold text-white" x-text="req.user_name"></h3><span class="text-xs text-yellow-500">معلق</span></div>
                                <p class="text-xs text-gray-400 truncate mt-1" x-text="req.last_message"></p>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Admin Active (My tickets) --}}
                <template x-for="req in adminActive" :key="req.id">
                    <div @click="openAdminSupport(req, true)" class="glass rounded-2xl p-4 cursor-pointer hover:bg-white/[0.06] transition-all bg-emerald-500/10 border border-emerald-500/20 mb-2" :class="activeChat?.id === req.id ? \'border-emerald-500\' : \'\'">
                        <div class="flex items-center gap-4">
                            <img :src="req.user_avatar" class="w-10 h-10 rounded-full object-cover">
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-center"><h3 class="text-sm font-semibold text-white" x-text="req.user_name"></h3><span class="text-xs text-emerald-500">نشط</span></div>
                                <p class="text-xs text-gray-400 truncate mt-1" x-text="req.last_message"></p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            @endif

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- OPTICVAULT SUPPORT CARD (FOR EVERYONE) --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div
                @click="openUserSupport()"
                class="glass rounded-2xl p-4 cursor-pointer hover:bg-white/[0.06] transition-all duration-300 group glow mb-4 relative overflow-hidden"
                :class="isSupportMode && !isAdminSupport ? \'bg-white/[0.08] border-yellow-500/50\' : \'border border-[#FFD700]/30\'"
            >
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-yellow-400 to-yellow-600"></div>
                <div class="flex items-center gap-4 relative z-10">
                    <div class="relative shrink-0">
                        <div class="w-12 h-12 rounded-full border-2 border-yellow-500/50 group-hover:border-yellow-400 flex items-center justify-center bg-gradient-to-br from-[#1a1a1c] to-[#0a0a0c] shadow-[0_0_15px_rgba(255,215,0,0.3)]">
                            <img src="/img/logo.svg" alt="Support" class="w-7 h-7 object-contain" onerror="this.src=\'https://ui-avatars.com/api/?name=Op&color=FFD700&background=111\'">
                        </div>
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-[#0a0a0c]" style="box-shadow: 0 0 12px rgba(34, 197, 94, 0.6);"></span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-yellow-200 group-hover:text-yellow-100 transition-colors">OpticVault Support</h3>
                            <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20" title="موثق"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                        </div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-[11px] text-gray-500">الدعم الفني الرسمي</span>
                        </div>
                    </div>
                </div>
            </div>
';
$content = str_replace($connectionsListStart, $connectionsListStart . $supportCard, $content);

// 2. Add emoji button to input area (and include emoji-picker-element script at the end)
// We will simply replace the input div wrapper
$chatInputAreaOriginal = '<div class="glass rounded-2xl p-3 flex items-center gap-3">';
$chatInputAreaNew = '<div class="glass rounded-2xl p-3 flex items-center gap-3 relative">
            {{-- Emoji Button --}}
            <button @click="toggleEmojiPicker()" class="p-2.5 rounded-xl text-gray-400 hover:text-white hover:bg-white/5 transition-all text-xl" title="اختر إيموجي">
                😊
            </button>
            
            {{-- Emoji Picker Dropdown --}}
            <div x-show="showEmojiPicker" @click.away="showEmojiPicker = false" class="absolute bottom-full left-0 mb-2 z-50">
                <emoji-picker @emoji-click="addEmoji"></emoji-picker>
            </div>
';
$content = str_replace($chatInputAreaOriginal, $chatInputAreaNew, $content);

// 3. Inject FAQ block into messages area for user support
$emptyStateOriginal = '{{-- Empty State --}}
            <template x-if="!loadingMessages && messages.length === 0">';
$faqBlock = '
            {{-- Support Action Banner (For Admin) --}}
            <template x-if="isAdminSupport && !adminIsActive">
                <div class="bg-yellow-500/20 border border-yellow-500/50 rounded-xl p-4 text-center mb-4">
                    <p class="text-yellow-100 mb-3 text-sm">هذا المستخدم يطلب المساعدة. هل تريد استلام هذه المحادثة؟</p>
                    <button @click="claimAdminSupport()" class="px-6 py-2 bg-yellow-500 hover:bg-yellow-600 text-black font-bold rounded-lg transition-colors text-sm">استلام المحادثة</button>
                </div>
            </template>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- SMART FAQ MENU (ONLY IN SUPPORT MODE FOR USERS) --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <template x-if="isSupportMode && !isAdminSupport">
                <div class="mb-6 flex flex-col items-center">
                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center shadow-lg shadow-yellow-500/20 mb-3">
                        <img src="/img/logo.svg" class="w-8 h-8" onerror="this.src=\'https://ui-avatars.com/api/?name=Op&color=fff&background=000\'">
                    </div>
                    <h2 class="text-xl font-bold text-white mb-1">مرحباً بك في دعم OpticVault</h2>
                    <p class="text-gray-400 text-sm mb-6 text-center max-w-md">نحن هنا لمساعدتك. تفضل باختيار أحد الأسئلة الشائعة أو اكتب رسالتك مباشرة لفريق الدعم.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 w-full max-w-2xl px-4">
                        <template x-for="(qa, index) in faqList" :key="index">
                            <button @click="askFaq(qa)" class="text-right p-3 rounded-xl border border-white/10 hover:border-yellow-500/50 bg-white/5 hover:bg-white/10 transition-all text-sm group">
                                <span class="text-yellow-500 group-hover:text-yellow-400 mb-1 block">❓ السؤال</span>
                                <span class="text-gray-200" x-text="qa.q"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Empty State --}}
            <template x-if="!loadingMessages && messages.length === 0 && !isSupportMode">';
$content = str_replace($emptyStateOriginal, $faqBlock, $content);

// 4. Update Header in right panel to reflect Support properly
$chatHeaderOriginal = '{{-- Chat Header --}}
        <div class="glass rounded-2xl p-4 mb-4 flex items-center gap-4">
            {{-- Back Button (Mobile) --}}
            <button @click="closeChat()" class="lg:hidden p-2 rounded-xl hover:bg-white/10 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>

            {{-- Partner Info --}}
            <div class="relative">
                <img :src="activeChat?.avatar" :alt="activeChat?.name" class="w-10 h-10 rounded-full border-2 border-white/10 object-cover">
                <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-[#0a0a0c]"
                      :class="partnerOnline ? \'bg-emerald-500\' : \'bg-gray-600\'"
                      :style="partnerOnline ? \'box-shadow: 0 0 12px rgba(34, 197, 94, 0.6)\' : \'\'"></span>
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-semibold" x-text="activeChat?.name"></h3>
                <span class="text-xs" :class="partnerOnline ? \'text-emerald-400\' : \'text-gray-500\'" x-text="partnerOnline ? \'متصل الآن\' : \'غير متصل\'"></span>
            </div>

            {{-- Profile Link --}}
            <a :href="\'/photographer/\' + activeChat?.id" class="p-2 rounded-xl text-gray-500 hover:text-purple-400 hover:bg-white/5 transition-all" title="عرض الملف الشخصي">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </a>
        </div>';

$chatHeaderNew = '{{-- Chat Header --}}
        <div class="glass rounded-2xl p-4 mb-4 flex items-center gap-4" :class="isSupportMode ? \'border-yellow-500/30 border-2\' : \'\'">
            {{-- Back Button (Mobile) --}}
            <button @click="closeChat()" class="lg:hidden p-2 rounded-xl hover:bg-white/10 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>

            {{-- Partner Info --}}
            <div class="relative">
                <img :src="activeChat?.avatar" :alt="activeChat?.name" class="w-10 h-10 rounded-full border-2 border-white/10 object-cover" :class="isSupportMode ? \'border-yellow-500\' : \'\'">
                <template x-if="!isSupportMode">
                    <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-[#0a0a0c]"
                          :class="partnerOnline ? \'bg-emerald-500\' : \'bg-gray-600\'"
                          :style="partnerOnline ? \'box-shadow: 0 0 12px rgba(34, 197, 94, 0.6)\' : \'\'"></span>
                </template>
                <template x-if="isSupportMode">
                     <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-[#0a0a0c] bg-emerald-500" style="box-shadow: 0 0 12px rgba(34, 197, 94, 0.6)"></span>
                </template>
            </div>
            <div class="flex-1 flex gap-2 items-center">
                <div>
                    <h3 class="text-sm font-semibold" :class="isSupportMode ? \'text-yellow-400\' : \'\'" x-text="activeChat?.name"></h3>
                    <span class="text-xs text-emerald-400" x-show="isSupportMode">متصل دائماً (نظام آلي & فريق الدعم)</span>
                    <span class="text-xs" :class="partnerOnline ? \'text-emerald-400\' : \'text-gray-500\'" x-show="!isSupportMode" x-text="partnerOnline ? \'متصل الآن\' : \'غير متصل\'"></span>
                </div>
                <!-- Support Gold Badge -->
                <template x-if="isSupportMode && !isAdminSupport">
                    <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                </template>
            </div>

            {{-- Admin actions in support mode --}}
            <template x-if="isAdminSupport && adminIsActive">
                <button @click="closeAdminSupport()" class="p-2 text-xs bg-red-500/20 text-red-400 hover:bg-red-500/40 rounded-lg transition-colors font-bold mr-2">إنهاء المحادثة</button>
            </template>

            {{-- Profile Link (hidden in support) --}}
            <a x-show="!isSupportMode" :href="\'/photographer/\' + activeChat?.id" class="p-2 rounded-xl text-gray-500 hover:text-purple-400 hover:bg-white/5 transition-all" title="عرض الملف الشخصي">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </a>
        </div>';
$content = str_replace($chatHeaderOriginal, $chatHeaderNew, $content);

// 5. Update AlpineJS script
$alpineScriptOriginal = '        searchQuery: \'\',
        activeChat: null,
        messages: [],
        newMessage: \'\',
        loadingMessages: false,
        sendingMessage: false,
        partnerOnline: false,
        pollInterval: null,
        lastMessageId: 0,';

$alpineScriptNew = '        searchQuery: \'\',
        activeChat: null, // {id, name, avatar} or {id="support", ...}
        messages: [],
        newMessage: \'\',
        loadingMessages: false,
        sendingMessage: false,
        partnerOnline: false,
        pollInterval: null,
        lastMessageId: 0,
        
        // Emoji Picker
        showEmojiPicker: false,
        
        // Support System
        isSupportMode: false,
        supportConversationId: null,
        faqList: [
            { q: "ما هو OpticVault؟", a: "OpticVault هو منصة تخزين سحابي وشبكة تواصل اجتماعي متكاملة مصممة خصيصاً للمصورين المحترفين والهواة." },
            { q: "كيف أرفع صوري؟", a: "يمكنك رفع صورك عبر صفحة \'إدارة الأصول\' → اختر ألبوماً → اضغط على \'رفع ومعالجة\'. الموقع يدعم الرفع الفردي والجماعي (ZIP/RAR)." },
            { q: "كيف أحصل على العلامة الزرقاء؟", a: "للحصول على العلامة الزرقاء للتوثيق، يجب أن تمتلك في حسابك 100 صورة معتمدة (في المنطقة الخضراء) خالية من المخالفات." },
            { q: "ما هو نظام الذكاء الاصطناعي؟", a: "نظام الأمان يقوم بتحليل كل صورة تُرفع عبر عدة محركات AI كالمشهد والعنف لتصنيف الصور إلى آمنة (خضراء) أو معلقة للرد من الإدارة." }
        ],
        
        // Admin Support System
        isAdminConfig: ' . ($isAdmin ?? 'false' ? 'true' : 'false') . ',
        isAdminSupport: false, // If true, the admin is chatting with a user req
        adminIsActive: false, // If true, admin has claimed this req
        adminPending: [],
        adminActive: [],
        adminSupportInterval: null,
        
        addEmoji(e) {
            this.newMessage += e.detail.unicode;
            this.showEmojiPicker = false;
        },
';
$content = str_replace($alpineScriptOriginal, $alpineScriptNew, $content);

$initOriginal = '        init() {
            window.addEventListener(\'beforeunload\', () => this.stopPolling());';
$initNew = '        init() {
            window.addEventListener(\'beforeunload\', () => this.stopPolling());
            if (this.isAdminConfig) {
                this.loadAdminSupportData();
                this.adminSupportInterval = setInterval(() => this.loadAdminSupportData(), 5000);
            }';
$content = str_replace($initOriginal, $initNew, $content);

$openChatOriginal = '        async openChat(id, name, avatar, isOnline) {
            this.stopPolling();';
$openChatNew = '        async openChat(id, name, avatar, isOnline) {
            this.stopPolling();
            this.isSupportMode = false;
            this.isAdminSupport = false;';
$content = str_replace($openChatOriginal, $openChatNew, $content);

$sendMessageOriginal = '        async sendMessage() {
            if (!this.newMessage.trim() || this.sendingMessage) return;
            const body = this.newMessage.trim();
            this.newMessage = \'\';
            await this.performSend({ body });
        },';
$sendMessageNew = '        async sendMessage() {
            if (!this.newMessage.trim() || this.sendingMessage) return;
            const body = this.newMessage.trim();
            this.newMessage = \'\';
            if (this.isSupportMode && !this.isAdminSupport) {
                await this.sendUserSupportMessage(body);
            } else if (this.isAdminSupport) {
                await this.sendAdminSupportMessage(body);
            } else {
                await this.performSend({ body });
            }
        },';
$content = str_replace($sendMessageOriginal, $sendMessageNew, $content);

$pollingOriginal = '        async pollMessages() {
            if (!this.activeChat) return;';
$pollingNew = '        async pollMessages() {
            if (!this.activeChat) return;
            
            if (this.isSupportMode && !this.isAdminSupport) {
                return this.pollUserSupport();
            }
            if (this.isAdminSupport) {
                return this.pollAdminSupport();
            }';
$content = str_replace($pollingOriginal, $pollingNew, $content);

$newFunctions = '
        // ──────────────────────────────────────────────────
        // USER SUPPORT FUNCTIONS
        // ──────────────────────────────────────────────────
        async openUserSupport() {
            this.stopPolling();
            this.isSupportMode = true;
            this.isAdminSupport = false;
            this.activeChat = { id: \'support\', name: \'OpticVault Support\', avatar: \'/img/logo.svg\' };
            this.partnerOnline = true;
            this.messages = [];
            this.loadingMessages = true;

            try {
                const res = await fetch(\'/api/support/conversation\', { headers: { \'Accept\': \'application/json\' }});
                const data = await res.json();
                this.supportConversationId = data.conversation_id;
                this.messages = data.messages;
                if (this.messages.length > 0) {
                    this.lastMessageId = this.messages[this.messages.length - 1].id;
                }
                this.$nextTick(() => this.scrollToBottom());
            } catch (e) {
                console.error(e);
            } finally {
                this.loadingMessages = false;
            }
            this.startPolling();
        },

        async askFaq(qa) {
            // Simulate user sending the question
            const tempId = Date.now();
            this.messages.push({
                id: tempId,
                body: qa.q,
                is_mine: true,
                is_system: false,
                is_faq: true,
                created_at: new Date().toLocaleTimeString(\'en-US\', {hour12: false, hour: "numeric", minute: "numeric"}),
                date: new Date().toISOString().split("T")[0]
            });
            this.$nextTick(() => this.scrollToBottom());
            
            this.sendingMessage = true;
            // Delay for realistic bot feel
            setTimeout(() => {
                this.messages.push({
                    id: Date.now() + 1,
                    body: qa.a,
                    is_mine: false,
                    is_system: true,
                    is_faq: true,
                    created_at: new Date().toLocaleTimeString(\'en-US\', {hour12: false, hour: "numeric", minute: "numeric"}),
                    date: new Date().toISOString().split("T")[0]
                });
                this.sendingMessage = false;
                this.$nextTick(() => this.scrollToBottom());
            }, 800);
        },

        async sendUserSupportMessage(body) {
            this.sendingMessage = true;
            try {
                const res = await fetch(\'/api/support/send\', {
                    method: \'POST\',
                    headers: {
                        \'Content-Type\': \'application/json\',
                        \'X-CSRF-TOKEN\': document.querySelector(\'meta[name="csrf-token"]\')?.content,
                        \'Accept\': \'application/json\',
                    },
                    body: JSON.stringify({ conversation_id: this.supportConversationId, body }),
                });
                const msg = await res.json();
                if (res.ok) {
                    this.messages.push(msg);
                    this.lastMessageId = msg.id;
                    this.$nextTick(() => this.scrollToBottom());
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.sendingMessage = false;
            }
        },

        async pollUserSupport() {
            try {
                const res = await fetch(`/api/support/poll/${this.supportConversationId}?after_id=${this.lastMessageId}`, {
                    headers: { \'Accept\': \'application/json\' }
                });
                const data = await res.json();
                if (data.messages && data.messages.length > 0) {
                    this.messages = [...this.messages, ...data.messages];
                    this.lastMessageId = this.messages[this.messages.length - 1].id;
                    this.$nextTick(() => this.scrollToBottom());
                }
            } catch (e) {}
        },

        // ──────────────────────────────────────────────────
        // ADMIN SUPPORT FUNCTIONS
        // ──────────────────────────────────────────────────
        async loadAdminSupportData() {
            try {
                const res = await fetch(\'/api/support/admin/pending\', { headers: { \'Accept\': \'application/json\' }});
                if (res.ok) {
                    const data = await res.json();
                    this.adminPending = data.pending;
                    this.adminActive = data.active;
                }
            } catch (e) {}
        },

        async openAdminSupport(req, isActive) {
            this.stopPolling();
            this.isSupportMode = true;
            this.isAdminSupport = true;
            this.adminIsActive = isActive;
            this.activeChat = { id: req.id, name: \'طلب دعم: \' + req.user_name, avatar: req.user_avatar };
            this.partnerOnline = true; // For UI
            this.messages = [];
            this.loadingMessages = true;
            this.supportConversationId = req.id;

            if (isActive) {
                // If it is my active ticket, load messages
                try {
                    const res = await fetch(`/api/support/admin/messages/${req.id}`, { headers: { \'Accept\': \'application/json\' }});
                    const data = await res.json();
                    this.messages = data.messages;
                    if (this.messages.length > 0) {
                        this.lastMessageId = this.messages[this.messages.length - 1].id;
                    }
                    this.$nextTick(() => this.scrollToBottom());
                    this.startPolling();
                } catch (e) {}
            } else {
                // Just viewing a pending request. We can\'t see full messages until claimed.
                this.messages = [{
                    id: 999999,
                    body: "آخر رسالة من المستخدم: " + req.last_message,
                    is_mine: false,
                    is_system: true,
                    created_at: req.created_at,
                    date: ""
                }];
            }
            this.loadingMessages = false;
        },

        async claimAdminSupport() {
            try {
                const res = await fetch(`/api/support/admin/claim/${this.supportConversationId}`, {
                    method: \'POST\',
                    headers: {
                        \'X-CSRF-TOKEN\': document.querySelector(\'meta[name="csrf-token"]\')?.content,
                        \'Accept\': \'application/json\',
                    }
                });
                if (res.ok) {
                    Swal.fire({icon: \'success\', title:\'تم القبول\', toast:true, position:\'top-end\', timer: 2000, showConfirmButton:false});
                    this.openAdminSupport(this.activeChat, true); // reload as active
                    this.loadAdminSupportData(); // refresh list
                } else {
                    const data = await res.json();
                    Swal.fire({icon: \'error\', title:\'خطأ\', text: data.message || \'تعذر الاستلام\'});
                }
            } catch (e) {
                console.error(e);
            }
        },

        async sendAdminSupportMessage(body) {
            this.sendingMessage = true;
            try {
                const res = await fetch(\'/api/support/admin/send\', {
                    method: \'POST\',
                    headers: {
                        \'Content-Type\': \'application/json\',
                        \'X-CSRF-TOKEN\': document.querySelector(\'meta[name="csrf-token"]\')?.content,
                        \'Accept\': \'application/json\',
                    },
                    body: JSON.stringify({ conversation_id: this.supportConversationId, body }),
                });
                const msg = await res.json();
                if (res.ok) {
                    this.messages.push(msg);
                    this.lastMessageId = msg.id;
                    this.$nextTick(() => this.scrollToBottom());
                }
            } catch (e) {} finally {
                this.sendingMessage = false;
            }
        },

        async pollAdminSupport() {
            if (!this.adminIsActive) return;
            try {
                const res = await fetch(`/api/support/admin/poll/${this.supportConversationId}?after_id=${this.lastMessageId}`, {
                    headers: { \'Accept\': \'application/json\' }
                });
                const data = await res.json();
                if (data.messages && data.messages.length > 0) {
                    this.messages = [...this.messages, ...data.messages];
                    this.lastMessageId = this.messages[this.messages.length - 1].id;
                    this.$nextTick(() => this.scrollToBottom());
                }
            } catch (e) {}
        },

        async closeAdminSupport() {
            try {
                const res = await fetch(`/api/support/admin/close/${this.supportConversationId}`, {
                    method: \'POST\',
                    headers: { \'X-CSRF-TOKEN\': document.querySelector(\'meta[name="csrf-token"]\')?.content, \'Accept\': \'application/json\' }
                });
                if (res.ok) {
                    this.closeChat();
                    this.loadAdminSupportData();
                }
            } catch (e) {}
        },
';
// Place them right before scrollToBottom() function inside Alpine
$content = str_replace('scrollToBottom() {', $newFunctions . "\n        scrollToBottom() {", $content);

// Include Emoji module at the end of scripts section
$endSection = '@endsection';
$emojiScript = '<script type="module" src="https://unpkg.com/emoji-picker-element@^1"></script>
';
$content = str_replace($endSection, $endSection . "\n" . $emojiScript, $content);

file_put_contents($file, $content);
echo "Modification complete!\n";
