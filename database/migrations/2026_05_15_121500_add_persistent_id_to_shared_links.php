<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('shared_links', 'persistent_id')) {
            Schema::table('shared_links', function (Blueprint $table) {
                $table->string('persistent_id', 64)->nullable()->after('token_hash')
                    ->index()
                    ->comment('Stable ID derived from original token hash. Survives token rotation for password auth keys.');
            });
        }
    }

    public function down(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            $table->dropColumn('persistent_id');
        });
    }
};
