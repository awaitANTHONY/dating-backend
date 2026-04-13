<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_interactions', function (Blueprint $table) {
            $table->unsignedTinyInteger('interaction_count')->default(1)->after('action');
        });
    }

    public function down(): void
    {
        Schema::table('user_interactions', function (Blueprint $table) {
            $table->dropColumn('interaction_count');
        });
    }
};
