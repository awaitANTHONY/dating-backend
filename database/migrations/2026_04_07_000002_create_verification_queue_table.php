<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('selfie_image');
            $table->enum('status', ['pending', 'approved', 'rejected', 'auto_approved'])->default('pending');
            $table->float('ai_confidence')->default(0);
            $table->json('ai_response')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('approved_by_admin')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('manual_notes')->nullable();
            $table->timestamps();

            // Indices for faster queries
            $table->index('user_id');
            $table->index('status');
            $table->index(['status', 'ai_confidence']);
            $table->index('approved_by_admin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_queue');
    }
};
