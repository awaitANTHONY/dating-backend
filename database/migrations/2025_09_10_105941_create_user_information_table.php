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
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedBigInteger('religion_id')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->enum('search_preference', ['male', 'female'])->nullable();
            $table->json('relation_goals')->nullable();
            $table->json('interests')->nullable();
            $table->json('languages')->nullable();
            $table->string('search_radius')->default('100.0');
            $table->string('country_code')->nullable();
            $table->string('phone')->nullable();
            $table->json('images')->nullable(); 
            $table->boolean('is_zodiac_sign_matter')->default(false);
            $table->boolean('is_food_preference_matter')->default(false);
            $table->integer('age')->nullable();
            $table->unsignedBigInteger('relationship_status_id')->nullable();
            $table->unsignedBigInteger('ethnicity_id')->nullable();
            $table->enum('alkohol', ['dont_drink', 'drink_frequently', 'drink_socially', 'prefer_not_to_say'])->nullable();
            $table->enum('smoke', ['dont_smoke', 'smoke_regularly', 'smoke_occasionally', 'prefer_not_to_say'])->nullable();
            $table->unsignedBigInteger('education_id')->nullable();
            $table->string('preffered_age')->nullable();
            $table->integer('height')->nullable();
            $table->unsignedBigInteger('carrer_field_id')->nullable();
            
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
