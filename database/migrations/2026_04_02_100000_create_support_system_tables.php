<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('admin_id')->nullable(); // null = لم يُقبل بعد
            $table->enum('status', ['pending', 'active', 'closed'])->default('pending');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('admin_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'created_at']);
            $table->index('user_id');
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id');
            $table->uuid('sender_id')->nullable(); // null = رسالة نظام
            $table->text('body');
            $table->boolean('is_system')->default(false); // رسائل تلقائية
            $table->boolean('is_faq')->default(false); // أسئلة FAQ
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('support_conversations')->cascadeOnDelete();
            $table->foreign('sender_id')->references('id')->on('users')->nullOnDelete();
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
    }
};
