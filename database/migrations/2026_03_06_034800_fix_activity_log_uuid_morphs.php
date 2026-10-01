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
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE uuid USING (subject_id::text::uuid)');
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE activity_log ALTER COLUMN causer_id TYPE uuid USING (causer_id::text::uuid)');
        } else {
            Schema::table('activity_log', function (Blueprint $table) {
                // Change id columns to UUID/String to support our UUID models
                $table->uuid('subject_id')->nullable()->change();
                $table->uuid('causer_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable()->change();
            $table->unsignedBigInteger('causer_id')->nullable()->change();
        });
    }
};
