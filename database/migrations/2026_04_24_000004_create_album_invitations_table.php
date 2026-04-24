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
        // 1. Create the new invitations table
        Schema::create('album_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('album_id')->constrained()->onDelete('cascade');
            $table->string('code', 64)->unique();
            $table->enum('role', ['admin', 'contributor', 'viewer'])->default('viewer');
            $table->integer('uses')->default(0);
            $table->integer('max_uses')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 2. Drop the temporary column from albums table (Cleanup)
        Schema::table('albums', function (Blueprint $table) {
            $table->dropColumn('invitation_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('album_invitations');

        Schema::table('albums', function (Blueprint $table) {
            $table->string('invitation_code', 32)->nullable()->unique();
        });
    }
};
