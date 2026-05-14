<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('shared_links', 'token_hash')) {
            Schema::table('shared_links', function (Blueprint $table) {
                $table->string('token_hash')->nullable()->after('token')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            $table->dropColumn('token_hash');
        });
    }
};
