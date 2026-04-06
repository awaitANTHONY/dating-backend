<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('reward_type', 30); // daily_login, follow_instagram, follow_twitter, follow_tiktok, referral, complete_profile
            $table->integer('coins_earned');
            $table->string('reference')->nullable(); // e.g. referral code
            $table->timestamp('claimed_at');
            $table->timestamps();

            // Daily rewards: one claim per type per day
            $table->index(['user_id', 'reward_type', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_rewards');
    }
};
