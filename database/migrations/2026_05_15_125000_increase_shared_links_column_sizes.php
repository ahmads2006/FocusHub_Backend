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
        Schema::table('shared_links', function (Blueprint $table) {
            // Increase sizes to accommodate Laravel encryption (JSON blobs)
            // A 64-char string encrypted is ~312 chars, so text is safer.
            $table->text('token')->change();
            $table->text('session_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            // Reverting to string (255) might truncate data if we don't clear it
            $table->string('token', 255)->change();
            $table->string('session_id', 255)->nullable()->change();
        });
    }
};
