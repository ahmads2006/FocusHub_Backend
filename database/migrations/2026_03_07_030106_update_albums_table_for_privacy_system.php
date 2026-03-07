<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            // Remove the old boolean privacy column
            if (Schema::hasColumn('albums', 'is_private')) {
                $table->dropColumn('is_private');
            }
            
            // Add the new enum privacy column
            $table->enum('privacy', ['public', 'private', 'hidden'])->default('private')->after('description');
            
            // is_collaborative already exists in the previous migration, 
            // but we'll ensure it's there or update it if needed.
            if (!Schema::hasColumn('albums', 'is_collaborative')) {
                $table->boolean('is_collaborative')->default(false)->after('privacy');
            }
            
            // Adding index for performance
            $table->index('privacy');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropColumn(['privacy']);
            $table->boolean('is_private')->default(false)->after('description');
        });
    }
};
