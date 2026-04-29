<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            // Composite index for fast gallery filtering
            $table->index(['privacy', 'moderation_status', 'created_at'], 'idx_gallery_performance');
            
            // Index for user-specific galleries
            $table->index(['user_id', 'privacy', 'moderation_status'], 'idx_user_gallery');
            
            // Full-text index for fast title/description search (instead of slow LIKE)
            $table->fullText(['title', 'description'], 'idx_search_fulltext');
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex('idx_gallery_performance');
            $table->dropIndex('idx_user_gallery');
            $table->dropFullText('idx_search_fulltext');
        });
    }
};
