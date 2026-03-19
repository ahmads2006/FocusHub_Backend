<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create new tables
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->text('bio')->nullable();
            $table->string('profile_picture')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('user_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('dynamic_watermark')->default(false);
            $table->string('watermark_text_color')->default('#FFFFFF');
            $table->string('watermark_neon_color')->default('#00FFFF');
            $table->float('watermark_opacity')->default(0.5);
            $table->boolean('auto_orient_default')->default(true);
            $table->boolean('stay_logged_in')->default(false);
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('user_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('verification_code')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('user_oauth', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('google_id')->nullable();
            $table->text('provider_token')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        // 2. Transfer data
        $users = DB::table('users')->get();

        foreach ($users as $user) {
            // Profile
            DB::table('user_profiles')->insert([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'name' => $user->name ?? null,
                'bio' => $user->bio ?? null,
                'profile_picture' => $user->profile_picture ?? null,
                'avatar' => $user->avatar ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Settings
            DB::table('user_settings')->insert([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'dynamic_watermark' => $user->dynamic_watermark ?? false,
                'watermark_text_color' => $user->watermark_text_color ?? '#FFFFFF',
                'watermark_neon_color' => $user->watermark_neon_color ?? '#00FFFF',
                'watermark_opacity' => $user->watermark_opacity ?? 0.5,
                'auto_orient_default' => $user->auto_orient_default ?? true,
                'stay_logged_in' => $user->stay_logged_in ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Verification
            DB::table('user_verifications')->insert([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'verification_code' => $user->verification_code ?? null,
                'is_verified' => $user->is_verified ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // OAuth
            if (!empty($user->google_id)) {
                DB::table('user_oauth')->insert([
                    'id' => Str::uuid(),
                    'user_id' => $user->id,
                    'google_id' => $user->google_id,
                    'provider_token' => $user->provider_token ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 3. Drop columns from users table
        Schema::table('users', function (Blueprint $table) {
            $columnsToDrop = [
                'name', 'bio', 'profile_picture', 'avatar',
                'dynamic_watermark', 'watermark_text_color', 'watermark_neon_color', 'watermark_opacity',
                'auto_orient_default', 'stay_logged_in',
                'verification_code', 'is_verified',
                'google_id', 'provider_token'
            ];

            // Only drop if they exist to prevent errors in different environments
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Add columns back to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->text('bio')->nullable();
            $table->string('profile_picture')->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('dynamic_watermark')->default(false);
            $table->string('watermark_text_color')->default('#FFFFFF');
            $table->string('watermark_neon_color')->default('#00FFFF');
            $table->float('watermark_opacity')->default(0.5);
            $table->boolean('auto_orient_default')->default(true);
            $table->boolean('stay_logged_in')->default(false);
            $table->string('verification_code')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->string('google_id')->nullable();
            $table->text('provider_token')->nullable();
        });

        // 2. Restore data
        $profiles = DB::table('user_profiles')->get();
        foreach ($profiles as $p) {
            DB::table('users')->where('id', $p->user_id)->update([
                'name' => $p->name,
                'bio' => $p->bio,
                'profile_picture' => $p->profile_picture,
                'avatar' => $p->avatar,
            ]);
        }

        $settings = DB::table('user_settings')->get();
        foreach ($settings as $s) {
            DB::table('users')->where('id', $s->user_id)->update([
                'dynamic_watermark' => $s->dynamic_watermark,
                'watermark_text_color' => $s->watermark_text_color,
                'watermark_neon_color' => $s->watermark_neon_color,
                'watermark_opacity' => $s->watermark_opacity,
                'auto_orient_default' => $s->auto_orient_default,
                'stay_logged_in' => $s->stay_logged_in,
            ]);
        }

        $verifications = DB::table('user_verifications')->get();
        foreach ($verifications as $v) {
            DB::table('users')->where('id', $v->user_id)->update([
                'verification_code' => $v->verification_code,
                'is_verified' => $v->is_verified,
            ]);
        }

        $oauths = DB::table('user_oauth')->get();
        foreach ($oauths as $o) {
            DB::table('users')->where('id', $o->user_id)->update([
                'google_id' => $o->google_id,
                'provider_token' => $o->provider_token,
            ]);
        }

        // 3. Drop new tables
        Schema::dropIfExists('user_oauth');
        Schema::dropIfExists('user_verifications');
        Schema::dropIfExists('user_settings');
        Schema::dropIfExists('user_profiles');
    }
};
