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
        Schema::create('image_appeals', function (Blueprint $table) {
            $table->id();
            $table->uuid('image_id')->constrained('images')->onDelete('cascade');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Sender information
            $table->string('contact_name');
            $table->string('contact_email');
            
            // The appeal details
            $table->text('reason');
            
            // Admin review details
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            
            $table->timestamps();
            
            // Allow only one pending appeal per image at a time
            $table->unique(['image_id', 'status'], 'unique_pending_appeal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_appeals');
    }
};
