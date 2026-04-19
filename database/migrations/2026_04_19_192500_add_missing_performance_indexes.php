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
            // High-frequency query: Public images excluding current user
            if (!indexExists('images', 'idx_images_discovery_v2')) {
                $table->index(['privacy', 'user_id', 'created_at'], 'idx_images_discovery_v2');
            }
        });

        // 2. Social Interaction Speed (Checking if user liked/bookmarked/hidden)
        // Note: Unique constraints already create indexes, but we can add secondary ones if needed for sorting
        Schema::table('image_likes', function (Blueprint $table) {
            if (!indexExists('image_likes', 'idx_likes_user_created')) {
                $table->index(['user_id', 'created_at'], 'idx_likes_user_created');
            }
        });

        Schema::table('bookmarks', function (Blueprint $table) {
            if (!indexExists('bookmarks', 'idx_bookmarks_user_created')) {
                $table->index(['user_id', 'created_at'], 'idx_bookmarks_user_created');
            }
        });

        // 3. User Preferences Fast Retrieval
        Schema::table('user_preferences', function (Blueprint $table) {
            if (!indexExists('user_preferences', 'idx_prefs_user_id')) {
                $table->index('user_id', 'idx_prefs_user_id');
            }
        });

        // 4. Notifications (Ordering by date for specific user)
        Schema::table('notifications', function (Blueprint $table) {
            if (!indexExists('notifications', 'idx_notifications_user_date')) {
                $table->index(['notifiable_id', 'created_at'], 'idx_notifications_user_date');
            }
        });
        
        // 5. Activity Log (Standard Spatie improvement)
        Schema::table('activity_log', function (Blueprint $table) {
            if (!indexExists('activity_log', 'idx_activity_causer_subject')) {
                $table->index(['causer_id', 'subject_id'], 'idx_activity_causer_subject');
            }
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex('idx_images_discovery_v2');
        });
        Schema::table('image_likes', function (Blueprint $table) {
            $table->dropIndex('idx_likes_user_created');
        });
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->dropIndex('idx_bookmarks_user_created');
        });
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropIndex('idx_prefs_user_id');
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_user_date');
        });
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('idx_activity_causer_subject');
        });
    }
};

/**
 * Helper to prevent migration crashes if indexes already exist.
 */
function indexExists($table, $index)
{
    try {
        $indexes = Schema::getIndexes($table);
        foreach ($indexes as $i) {
            if ($i['name'] === $index) return true;
        }
    } catch (\Exception $e) {
        return false;
    }
    return false;
}
