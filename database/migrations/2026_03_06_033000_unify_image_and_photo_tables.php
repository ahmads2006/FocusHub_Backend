<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop foreign key explicitly if it exists
        if (Schema::hasTable('analytics')) {
            Schema::table('analytics', function (Blueprint $table) {
                try {
                    $table->dropForeign('analytics_photo_id_foreign');
                } catch (\Exception $e) {
                    // Ignore if it doesn't exist
                }
            });
            
            Schema::table('analytics', function (Blueprint $table) {
                if (Schema::hasColumn('analytics', 'photo_id')) {
                    $table->renameColumn('photo_id', 'image_id');
                }
            });

            Schema::table('analytics', function (Blueprint $table) {
                if (Schema::hasColumn('analytics', 'image_id')) {
                    $table->foreign('image_id')->references('id')->on('images')->cascadeOnDelete();
                }
            });
        }

        // 2. Clear any other constraints on photos
        // Check if albums has a foreign key to photos (it shouldn't, but let's check)
        
        // 3. Drop photos table
        Schema::dropIfExists('photos');
    }

    public function down(): void
    {
        Schema::table('analytics', function (Blueprint $table) {
            if (Schema::hasColumn('analytics', 'image_id')) {
                $table->dropForeign(['image_id']);
                $table->renameColumn('image_id', 'photo_id');
            }
        });
    }
};
