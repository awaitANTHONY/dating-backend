<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Total number of times verification has been rejected
            $table->unsignedTinyInteger('verification_attempts')->default(0)->after('verification_status');
            // When the cooldown period ends (set after 3 rejections)
            $table->timestamp('verification_cooldown_until')->nullable()->after('verification_attempts');
            // Permanent ban flag (set after 6 rejections)
            $table->boolean('is_banned')->default(false)->after('verification_cooldown_until');
            $table->string('ban_reason')->nullable()->after('is_banned');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'verification_attempts',
                'verification_cooldown_until',
                'is_banned',
                'ban_reason',
            ]);
        });
    }
};
