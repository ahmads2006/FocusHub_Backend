<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Conversations table ──────────────────────────────────
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['direct', 'group'])->default('group');
            $table->string('name')->nullable();                          // Group name (defaults to album title)
            $table->boolean('is_name_custom')->default(false);           // True if user manually renamed the group
            $table->foreignUuid('album_id')->nullable()->constrained('albums')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable()->index();   // Denormalized for fast ordering
            $table->timestamps();

            $table->index('album_id');
        });

        // ── Conversation participants ────────────────────────────
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['owner', 'member'])->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'conversation_id']);
        });

        // ── Add conversation_id to existing messages table ───────
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignUuid('conversation_id')->nullable()->after('receiver_id')
                  ->constrained('conversations')->cascadeOnDelete();
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropColumn('conversation_id');
        });

        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
