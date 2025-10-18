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
        Schema::create('user_interactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // User performing the action
            $table->unsignedBigInteger('target_user_id'); // User being swiped on
            $table->enum('action', ['like', 'dislike', 'pass']);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('target_user_id')->references('id')->on('users')->onDelete('cascade');

            // Unique constraint to prevent duplicate interactions
            $table->unique(['user_id', 'target_user_id'], 'unique_user_interaction');

            // Index for fast reverse lookup and match detection
            $table->index(['target_user_id', 'user_id', 'action'], 'idx_target_user_action');
            
            // Additional indexes for performance
            $table->index('user_id');
            $table->index('target_user_id');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_interactions');
    }
};
