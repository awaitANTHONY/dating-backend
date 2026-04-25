<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('incognito_mode')->default(false)->after('last_activity');
            $table->boolean('hide_online_status')->default(false)->after('incognito_mode');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['incognito_mode', 'hide_online_status']);
        });
    }
};
