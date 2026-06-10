<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Yellow-zone (pending_review) images should appear in the public gallery
     * immediately with a frontend sensitive-content overlay — not stay hidden.
     */
    public function up(): void
    {
        DB::table('image_moderation')
            ->where('status', 'pending_review')
            ->update(['is_visible' => true]);

        DB::table('images')
            ->where('moderation_status', 'pending_review')
            ->update(['is_visible' => true]);
    }

    public function down(): void
    {
        DB::table('image_moderation')
            ->where('status', 'pending_review')
            ->update(['is_visible' => false]);

        DB::table('images')
            ->where('moderation_status', 'pending_review')
            ->update(['is_visible' => false]);
    }
};
