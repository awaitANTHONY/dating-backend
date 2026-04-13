<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_engagement_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();

            // Raw metrics (updated by scheduled command)
            $table->unsignedInteger('received_likes_count')->default(0);
            $table->unsignedInteger('received_likes_7d')->default(0);
            $table->unsignedInteger('sent_likes_count')->default(0);
            $table->unsignedInteger('match_count')->default(0);
            $table->decimal('match_rate', 5, 4)->default(0);
            $table->decimal('profile_completeness', 5, 4)->default(0);
            $table->decimal('response_rate', 5, 4)->default(0);
            $table->unsignedInteger('impressions_count')->default(0);
            $table->unsignedInteger('impressions_without_like')->default(0);

            // Computed sub-scores (0.0 to 1.0 each)
            $table->decimal('popularity_score', 5, 4)->default(0);
            $table->decimal('quality_score', 5, 4)->default(0);
            $table->decimal('activity_score', 5, 4)->default(0);
            $table->decimal('freshness_score', 5, 4)->default(1.0000);

            // Final composite (0.0 to 10.0)
            $table->decimal('engagement_score', 5, 2)->default(5.00);

            $table->timestamp('last_computed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('engagement_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_engagement_scores');
    }
};
