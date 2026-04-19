<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comprehensive Performance Indexing for OpalShot v2.
     * Target: Feed speed, Social lookups, and AI Metadata retrieval.
     */
    public function up(): void
    {
        // 1. Optimize Feed & Exploration
        Schema::table('images', function (Blueprint $table) {
            $indexes = Schema::getIndexes('images');
            $exists = collect($indexes)->contains('name', 'idx_images_discovery_v2');
            
            if (!$exists) {
                $table->index(['privacy', 'user_id', 'created_at'], 'idx_images_discovery_v2');
            }
        });

        // 2. Social Interaction Speed
        Schema::table('image_likes', function (Blueprint $table) {
            $indexes = Schema::getIndexes('image_likes');
            $exists = collect($indexes)->contains('name', 'idx_likes_user_created');
            
            if (!$exists) {
                $table->index(['user_id', 'created_at'], 'idx_likes_user_created');
            }
        });

        Schema::table('bookmarks', function (Blueprint $table) {
            $indexes = Schema::getIndexes('bookmarks');
            $exists = collect($indexes)->contains('name', 'idx_bookmarks_user_created');
            
            if (!$exists) {
                $table->index(['user_id', 'created_at'], 'idx_bookmarks_user_created');
            }
        });

        // 3. User Preferences Fast Retrieval
        Schema::table('user_preferences', function (Blueprint $table) {
            $indexes = Schema::getIndexes('user_preferences');
            $exists = collect($indexes)->contains('name', 'idx_prefs_user_id');
            
            if (!$exists) {
                $table->index('user_id', 'idx_prefs_user_id');
            }
        });

        // 4. Notifications
        Schema::table('notifications', function (Blueprint $table) {
            $indexes = Schema::getIndexes('notifications');
            $exists = collect($indexes)->contains('name', 'idx_notifications_user_date');
            
            if (!$exists) {
                $table->index(['notifiable_id', 'created_at'], 'idx_notifications_user_date');
            }
        });
        
        // 5. Activity Log
        Schema::table('activity_log', function (Blueprint $table) {
            $indexes = Schema::getIndexes('activity_log');
            $exists = collect($indexes)->contains('name', 'idx_activity_causer_subject');
            
            if (!$exists) {
                $table->index(['causer_id', 'subject_id'], 'idx_activity_causer_subject');
            }
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) { $table->dropIndex('idx_images_discovery_v2'); });
        Schema::table('image_likes', function (Blueprint $table) { $table->dropIndex('idx_likes_user_created'); });
        Schema::table('bookmarks', function (Blueprint $table) { $table->dropIndex('idx_bookmarks_user_created'); });
        Schema::table('user_preferences', function (Blueprint $table) { $table->dropIndex('idx_prefs_user_id'); });
        Schema::table('notifications', function (Blueprint $table) { $table->dropIndex('idx_notifications_user_date'); });
        Schema::table('activity_log', function (Blueprint $table) { $table->dropIndex('idx_activity_causer_subject'); });
    }
};
