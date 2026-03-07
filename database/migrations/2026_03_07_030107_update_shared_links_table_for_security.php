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
            $table->integer('access_count')->default(0)->after('expires_at');
            $table->integer('max_access')->nullable()->after('access_count');
            $table->timestamp('last_accessed_at')->nullable()->after('max_access');
            $table->timestamp('revoked_at')->nullable()->after('last_accessed_at');
            
            // Ensure token is indexed for fast lookup
            // (already done in previous migration, but good to be sure)
            if (!Schema::hasIndex('shared_links', ['token'])) {
                $table->index('token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            $table->dropColumn(['access_count', 'max_access', 'last_accessed_at', 'revoked_at']);
        });
    }
};
