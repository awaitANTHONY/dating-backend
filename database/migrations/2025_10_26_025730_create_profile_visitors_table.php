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
        Schema::create('profile_visitors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visitor_id'); // User who visited
            $table->unsignedBigInteger('visited_user_id'); // User whose profile was visited
            $table->timestamp('visited_at');
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['visited_user_id', 'visited_at']);
            $table->index(['visitor_id', 'visited_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_visitors');
    }
};
