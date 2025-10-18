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
        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // User who is blocking
            $table->unsignedBigInteger('blocked_user_id'); // User being blocked
            $table->text('reason')->nullable(); // Optional reason for blocking
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('blocked_user_id')->references('id')->on('users')->onDelete('cascade');

            // Unique constraint to prevent duplicate blocks
            $table->unique(['user_id', 'blocked_user_id'], 'unique_user_block');

            // Indexes for performance
            $table->index('user_id');
            $table->index('blocked_user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
    }
};
