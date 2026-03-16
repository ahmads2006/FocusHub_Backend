<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop columns that were moved to image_storage, image_meta,
     * image_moderation, and image_settings tables.
     */
    public function up(): void
    {
        // Drop columns moved to image_settings
        // (copyright_enabled was never added — safe to skip)
        Schema::table('images', function (Blueprint $table) {
            $toDrop = ['allow_download', 'watermark_on_download', 'is_comparison', 'views_count', 'downloads_count'];
            $existing = array_filter($toDrop, fn($col) => Schema::hasColumn('images', $col));
            if (!empty($existing)) {
                $table->dropColumn(array_values($existing));
            }
        });

        // Drop columns moved to image_storage
        Schema::table('images', function (Blueprint $table) {
            $toDrop = ['path', 'original_path', 'imagekit_file_id', 'imagekit_file_path', 'md5_hash'];
            $existing = array_filter($toDrop, fn($col) => Schema::hasColumn('images', $col));
            if (!empty($existing)) {
                $table->dropColumn(array_values($existing));
            }
        });

        // Drop columns moved to image_meta
        Schema::table('images', function (Blueprint $table) {
            $toDrop = ['exif_data', 'technical_specs', 'specs', 'metadata'];
            $existing = array_filter($toDrop, fn($col) => Schema::hasColumn('images', $col));
            if (!empty($existing)) {
                $table->dropColumn(array_values($existing));
            }
        });

        // Drop columns moved to image_moderation
        Schema::table('images', function (Blueprint $table) {
            $toDrop = ['status', 'is_visible', 'is_sensitive', 'sensitivity_reason', 'ai_metadata'];
            $existing = array_filter($toDrop, fn($col) => Schema::hasColumn('images', $col));
            if (!empty($existing)) {
                $table->dropColumn(array_values($existing));
            }
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            // Restore storage fields
            $table->string('path')->nullable();
            $table->string('original_path')->nullable();
            $table->string('imagekit_file_id')->nullable();
            $table->string('imagekit_file_path')->nullable();
            $table->string('md5_hash')->nullable()->index();

            // Restore meta fields
            $table->json('exif_data')->nullable();
            $table->json('technical_specs')->nullable();
            $table->json('specs')->nullable();
            $table->mediumText('metadata')->nullable();

            // Restore moderation fields
            $table->enum('status', ['pending', 'approved', 'pending_review', 'rejected', 'under_review'])->default('approved');
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_sensitive')->default(false);
            $table->string('sensitivity_reason')->nullable();
            $table->json('ai_metadata')->nullable();

            // Restore settings fields
            $table->boolean('allow_download')->default(true);
            $table->boolean('watermark_on_download')->default(false);
            $table->boolean('copyright_enabled')->default(false);
            $table->boolean('is_comparison')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('downloads_count')->default(0);
        });
    }
};
