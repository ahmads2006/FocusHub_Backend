<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Migrate existing data from images table to the new normalized tables.
     * Each image gets a corresponding row in image_storage, image_meta,
     * image_moderation, and image_settings.
     */
    public function up(): void
    {
        $images = DB::table('images')->get();

        foreach ($images as $image) {
            // 1. image_storage
            DB::table('image_storage')->insert([
                'id'                  => (string) Str::uuid(),
                'image_id'            => $image->id,
                'path'                => $image->path ?? null,
                'original_path'       => $image->original_path ?? null,
                'imagekit_file_id'    => $image->imagekit_file_id ?? null,
                'imagekit_file_path'  => $image->imagekit_file_path ?? null,
                'md5_hash'            => $image->md5_hash ?? null,
                'created_at'          => $image->created_at,
                'updated_at'          => $image->updated_at,
            ]);

            // 2. image_meta
            DB::table('image_meta')->insert([
                'id'              => (string) Str::uuid(),
                'image_id'        => $image->id,
                'exif_data'       => $image->exif_data ?? null,
                'technical_specs' => $image->technical_specs ?? null,
                'specs'           => $image->specs ?? null,
                'metadata'        => $image->metadata ?? null,
                'created_at'      => $image->created_at,
                'updated_at'      => $image->updated_at,
            ]);

            // 3. image_moderation
            DB::table('image_moderation')->insert([
                'id'                 => (string) Str::uuid(),
                'image_id'           => $image->id,
                'status'             => $image->status ?? 'approved',
                'is_visible'         => $image->is_visible ?? 1,
                'is_sensitive'       => $image->is_sensitive ?? 0,
                'sensitivity_reason' => $image->sensitivity_reason ?? null,
                'ai_metadata'        => $image->ai_metadata ?? null,
                'created_at'         => $image->created_at,
                'updated_at'         => $image->updated_at,
            ]);

            // 4. image_settings
            DB::table('image_settings')->insert([
                'id'                    => (string) Str::uuid(),
                'image_id'              => $image->id,
                'allow_download'        => $image->allow_download ?? 1,
                'watermark_on_download' => $image->watermark_on_download ?? 0,
                'copyright_enabled'     => $image->copyright_enabled ?? 0,
                'is_comparison'         => $image->is_comparison ?? 0,
                'views_count'           => $image->views_count ?? 0,
                'downloads_count'       => $image->downloads_count ?? 0,
                'created_at'            => $image->created_at,
                'updated_at'            => $image->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        // Rollback: delete all rows in child tables — they'll need to be rebuilt
        DB::table('image_storage')->delete();
        DB::table('image_meta')->delete();
        DB::table('image_moderation')->delete();
        DB::table('image_settings')->delete();
    }
};
