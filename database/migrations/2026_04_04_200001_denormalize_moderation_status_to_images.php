<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * v22.0 Performance Hardening: Denormalize moderation status into images table.
     * This eliminates the expensive whereHas('moderation') subquery from every single image query.
     */
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true)->after('privacy')->index('idx_images_is_visible');
            $table->string('moderation_status', 20)->default('approved')->after('is_visible');
        });

        // Sync existing data from image_moderation table
        DB::statement("
            UPDATE images i
            JOIN image_moderation im ON im.image_id = i.id
            SET i.is_visible = im.is_visible,
                i.moderation_status = im.status
        ");
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex('idx_images_is_visible');
            $table->dropColumn(['is_visible', 'moderation_status']);
        });
    }
};
