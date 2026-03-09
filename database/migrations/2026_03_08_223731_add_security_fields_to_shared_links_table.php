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
            $table->string('session_id')->nullable()->after('password');
            $table->boolean('auto_rotate')->default(false)->after('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            $table->dropColumn(['session_id', 'auto_rotate']);
        });
    }
};
