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
        Schema::table('user_information', function (Blueprint $table) {
            $table->text('address')->nullable();
            $table->json('activities')->nullable();
            $table->json('food_drinks')->nullable();
            $table->json('sport')->nullable();
            $table->json('games')->nullable();
            $table->json('music')->nullable();
            $table->json('films_books')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_information', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'activities', 
                'food_drinks',
                'sport',
                'games',
                'music',
                'films_books'
            ]);
        });
    }
};
