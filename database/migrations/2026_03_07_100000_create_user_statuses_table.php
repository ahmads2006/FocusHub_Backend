<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إنشاء جدول حالات المستخدمين (user_statuses) ونقل البيانات من users.
     * إضافة editor إلى enum role.
     */
    public function up(): void
    {
        Schema::create('user_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_banned')->default(false);
            $table->timestamp('banned_at')->nullable();
            $table->boolean('is_shadow_hidden')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        // نقل البيانات الحالية من users إلى user_statuses
        $users = DB::table('users')->get();
        foreach ($users as $u) {
            DB::table('user_statuses')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $u->id,
                'is_banned' => $u->is_banned ?? false,
                'banned_at' => $u->banned_at ?? null,
                'is_shadow_hidden' => $u->is_shadow_hidden ?? false,
                'is_deleted' => false,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // حذف الأعمدة من users
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_shadow_hidden', 'is_banned', 'banned_at']);
        });

        // إضافة editor إلى enum role
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'user', 'photographer', 'editor') DEFAULT 'user'");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_shadow_hidden')->default(false)->after('is_verified');
            $table->boolean('is_banned')->default(false)->after('is_shadow_hidden');
            $table->timestamp('banned_at')->nullable()->after('is_banned');
        });

        foreach (DB::table('user_statuses')->get() as $s) {
            DB::table('users')->where('id', $s->user_id)->update([
                'is_banned' => $s->is_banned,
                'banned_at' => $s->banned_at,
                'is_shadow_hidden' => $s->is_shadow_hidden,
            ]);
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'user', 'photographer') DEFAULT 'user'");
        Schema::dropIfExists('user_statuses');
    }
};
