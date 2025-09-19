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
        Schema::create('user_information', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('bio')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedBigInteger('religion_id')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->string('search_preference')->nullable();
            $table->json('relation_goals')->nullable();
            $table->json('interests')->nullable();
            $table->json('languages')->nullable();
            $table->string('search_radius')->default('100.0');
            $table->string('country_code')->nullable();
            $table->string('phone')->nullable();
            $table->json('images')->nullable(); 
            $table->string('wallet_balance')->default('0.00');
            
            // Index for better performance
            $table->index('user_id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_information');
    }
};
