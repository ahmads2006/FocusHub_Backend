<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update shared_links table
        Schema::table('shared_links', function (Blueprint $table) {
            // Drop the unique constraint/index first, because TEXT columns cannot stay unique without prefix length in MySQL
            $table->dropUnique(['token']);
        });

        Schema::table('shared_links', function (Blueprint $table) {
            // Now safe to change to text for encrypted strings
            $table->text('token')->change();
            $table->text('session_id')->nullable()->change();
            
            // Add token_hash for secure indexing and lookups (Laravel encryption is non-deterministic)
            $table->string('token_hash')->nullable()->after('token')->index();
        });

        // 2. Update images table
        Schema::table('images', function (Blueprint $table) {
            // metadata will now store an encrypted JSON array (stored as string)
            $table->text('metadata')->nullable()->change();
        });

        // 3. Populate existing token hashes (if any)
        DB::table('shared_links')->get()->each(function ($link) {
            DB::table('shared_links')
                ->where('id', $link->id)
                ->update(['token_hash' => hash('sha256', $link->token)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            $table->string('token')->change();
            $table->string('session_id')->nullable()->change();
            $table->dropColumn('token_hash');
            
            // Restore unique constraint
            $table->unique('token');
        });

        Schema::table('images', function (Blueprint $table) {
            $table->json('metadata')->nullable()->change();
        });
    }
};
