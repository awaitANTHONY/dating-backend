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
            $table->string('mood_text', 110)->nullable()->after('films_books'); // emoji + text
            $table->timestamp('mood_expires_at')->nullable()->after('mood_text');
            
            // Add index for efficient cleanup queries
            $table->index('mood_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_information', function (Blueprint $table) {
            $table->dropIndex(['mood_expires_at']);
            $table->dropColumn(['mood_text', 'mood_expires_at']);
        });
    }
};