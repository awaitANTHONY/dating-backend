<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banned_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_token')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('reason')->default('Banned by admin.');
            $table->timestamps();

            $table->index('device_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banned_devices');
    }
};
