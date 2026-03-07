<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إزالة جدول photos إن وُجد (المشروع يستخدم images فقط).
     */
    public function up(): void
    {
        Schema::dropIfExists('photos');
    }

    public function down(): void
    {
        // لا حاجة لإعادة إنشاء photos - غير مستخدم
    }
};
