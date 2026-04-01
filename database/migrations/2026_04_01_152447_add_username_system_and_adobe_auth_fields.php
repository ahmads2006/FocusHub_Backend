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
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('username')->unique()->nullable()->after('name')->index();
            $table->timestamp('username_last_changed_at')->nullable()->after('username');
        });

        Schema::table('user_oauth', function (Blueprint $table) {
            $table->string('adobe_id')->unique()->nullable()->after('google_id')->index();
        });

        // 2. Data Migration: Generate initial usernames for existing users
        $profiles = DB::table('user_profiles')->get();
        foreach ($profiles as $profile) {
            $user = DB::table('users')->where('id', $profile->user_id)->first();
            if (!$user) continue;

            $baseName = $profile->name ?: explode('@', $user->email)[0];
            $baseName = str_replace([' ', '.', '-'], '_', $baseName);
            $baseHandle = '@' . $baseName;
            
            $handle = $baseHandle;
            $counter = 1;
            while (DB::table('user_profiles')->where('username', $handle)->exists()) {
                $handle = $baseHandle . $counter;
                $counter++;
            }

            DB::table('user_profiles')->where('id', $profile->id)->update([
                'username' => $handle,
                'username_last_changed_at' => now()->subDays(31), // Allow immediate change if desired
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['username', 'username_last_changed_at']);
        });

        Schema::table('user_oauth', function (Blueprint $table) {
            $table->dropColumn('adobe_id');
        });
    }
};
