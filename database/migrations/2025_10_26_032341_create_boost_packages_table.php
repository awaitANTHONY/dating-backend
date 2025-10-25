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
        Schema::create('boost_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Small Boost", "Medium Boost", "Large Boost"
            $table->text('description')->nullable(); // Package description
            $table->integer('boost_count'); // Number of boosts in package (3, 5, 10)
            $table->integer('boost_duration')->default(30); // Duration in minutes (30, 60, 120)
            $table->enum('platform', ['ios', 'android', 'both'])->default('both'); // Platform availability
            $table->string('product_id')->unique(); // Store product ID for in-app purchases
            $table->boolean('status')->default(1); // Active/Inactive
            $table->integer('position')->default(0); // For ordering packages
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boost_packages');
    }
};
