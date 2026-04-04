<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v22.0 Performance Hardening: Composite indexes for gallery, feed, and search queries.
     */
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            // Gallery & Feed: WHERE privacy=public ORDER BY created_at DESC
            $table->index(['privacy', 'created_at'], 'idx_images_privacy_created');

            // User vault: WHERE user_id=? ORDER BY created_at DESC
            $table->index(['user_id', 'created_at'], 'idx_images_user_created');

            // Album browsing: WHERE album_id=? ORDER BY created_at DESC
            $table->index(['album_id', 'created_at'], 'idx_images_album_created');
        });

        Schema::table('image_moderation', function (Blueprint $table) {
            // Global scope visibility filter: WHERE is_visible=1
            $table->index(['image_id', 'is_visible'], 'idx_moderation_image_visible');
        });

        Schema::table('media_ai_metadata', function (Blueprint $table) {
            // AI search: WHERE media_id=? (already has a composite, add category for search)
            $table->index(['category'], 'idx_ai_metadata_category');
        });

        Schema::table('image_likes', function (Blueprint $table) {
            // Feed anti-duplicate: WHERE user_id=? (covered by unique, but add for count queries)
            $table->index(['image_id'], 'idx_likes_image_id');
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex('idx_images_privacy_created');
            $table->dropIndex('idx_images_user_created');
            $table->dropIndex('idx_images_album_created');
        });

        Schema::table('image_moderation', function (Blueprint $table) {
            $table->dropIndex('idx_moderation_image_visible');
        });

        Schema::table('media_ai_metadata', function (Blueprint $table) {
            $table->dropIndex('idx_ai_metadata_category');
        });

        Schema::table('image_likes', function (Blueprint $table) {
            $table->dropIndex('idx_likes_image_id');
        });
    }
};
