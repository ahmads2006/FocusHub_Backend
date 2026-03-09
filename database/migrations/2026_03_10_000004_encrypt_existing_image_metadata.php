<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Encrypt existing plain JSON metadata in images table
        DB::table('images')->whereNotNull('metadata')->get()->each(function ($image) {
            $data = $image->metadata;
            
            // Try to see if it's already encrypted
            try {
                Crypt::decryptString($data);
                // If it doesn't throw, it's already encrypted
            } catch (\Exception $e) {
                // If it throws, it's likely plain JSON. Let's encrypt it.
                // We check if it's valid JSON first to be sure
                json_decode($data);
                if (json_last_error() === JSON_ERROR_NONE) {
                    DB::table('images')
                        ->where('id', $image->id)
                        ->update(['metadata' => Crypt::encryptString($data)]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Theoretically decrypt everything, but usually down migrations for data transformations are risky
        DB::table('images')->whereNotNull('metadata')->get()->each(function ($image) {
            try {
                $decrypted = Crypt::decryptString($image->metadata);
                DB::table('images')
                    ->where('id', $image->id)
                    ->update(['metadata' => $decrypted]);
            } catch (\Exception $e) {
                // Not encrypted or wrong key
            }
        });
    }
};
