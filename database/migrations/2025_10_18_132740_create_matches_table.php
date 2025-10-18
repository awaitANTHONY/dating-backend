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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Always the smaller user_id
            $table->unsignedBigInteger('target_user_id'); // Always the larger user_id
            $table->timestamps();
            $table->softDeletes(); // For "unmatch" functionality

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('target_user_id')->references('id')->on('users')->onDelete('cascade');

            // Unique constraint to prevent duplicate matches
            $table->unique(['user_id', 'target_user_id'], 'unique_match');

            // Indexes for performance
            $table->index('user_id');
            $table->index('target_user_id');
            $table->index('deleted_at');

            // Check constraint to ensure user_id < target_user_id
            $table->rawIndex('(user_id < target_user_id)', 'check_user_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
