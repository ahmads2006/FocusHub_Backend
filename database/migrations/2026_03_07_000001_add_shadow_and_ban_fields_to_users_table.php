<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Shadow Privacy: عند true يختفي المستخدم ومحتواه من النتائج العامة والبحث.
     * Ban: يمنع تسجيل الدخول تماماً.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_shadow_hidden')->default(false)->after('is_verified');
            $table->boolean('is_banned')->default(false)->after('is_shadow_hidden');
            $table->timestamp('banned_at')->nullable()->after('is_banned');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_shadow_hidden', 'is_banned', 'banned_at']);
        });
    }
};
