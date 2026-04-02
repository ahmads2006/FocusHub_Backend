<?php

namespace App\Console\Commands;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoCloseSupportChats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'support:autoclose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically close support chats that are inactive or pending for too long';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        // 1. إغلاق المحادثات النشطة (active) التي لم يحدث فيها تفاعل لأكثر من 5 دقائق
        $inactiveActiveChats = SupportConversation::where('status', 'active')
            ->where('updated_at', '<', $now->copy()->subMinutes(5))
            ->get();

        /** @var SupportConversation $chat */
        foreach ($inactiveActiveChats as $chat) {
            $chat->update(['status' => 'closed']);
            
            SupportMessage::create([
                'conversation_id' => $chat->id,
                'sender_id' => null,
                'body' => 'تم إنهاء المحادثة آلياً بسبب عدم التفاعل لـ 5 دقائق. شكراً لتواصلك مع دعم OpticVault! 🙏',
                'is_system' => true,
            ]);
        }

        // 2. إغلاق المحادثات المعلقة (pending) التي لم يستلمها أحد لأكثر من 30 دقيقة
        $longPendingChats = SupportConversation::where('status', 'pending')
            ->where('created_at', '<', $now->copy()->subMinutes(30))
            ->get();

        /** @var SupportConversation $chat */
        foreach ($longPendingChats as $chat) {
            $chat->update(['status' => 'closed']);
            
            SupportMessage::create([
                'conversation_id' => $chat->id,
                'sender_id' => null,
                'body' => 'نعتذر بشدة، جميع ممثلي الدعم الفني مشغولون حالياً. تم إنهاء طلب الدعم بسبب الانتظار لأكثر من 30 دقيقة. يرجى المحاولة لاحقاً. ⏳',
                'is_system' => true,
            ]);
        }

        $this->info("Closed {$inactiveActiveChats->count()} inactive chats and {$longPendingChats->count()} stale pending chats.");
    }
}
