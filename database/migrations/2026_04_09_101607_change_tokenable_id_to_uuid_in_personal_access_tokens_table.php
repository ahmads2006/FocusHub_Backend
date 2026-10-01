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
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE uuid USING (tokenable_id::text::uuid)');
        } else {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->uuid('tokenable_id')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('tokenable_id')->change();
        });
    }
};
