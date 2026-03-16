<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $albums = DB::table('albums')->get();

        foreach ($albums as $album) {
            DB::table('album_settings')->insert([
                'id' => (string) Str::uuid(),
                'album_id' => $album->id,
                'privacy' => $album->privacy ?? 'public',
                'is_collaborative' => $album->is_collaborative ?? false,
                'cover_image' => $album->cover_image ?? null,
                'status' => $album->status ?? null,
                'created_at' => $album->created_at,
                'updated_at' => $album->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('album_settings')->truncate();
    }
};
