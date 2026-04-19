<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_information', function (Blueprint $table) {
            $table->decimal('photo_quality_score', 4, 2)->default(5.00)->after('location_flags');
        });
    }

    public function down(): void
    {
        Schema::table('user_information', function (Blueprint $table) {
            $table->dropColumn('photo_quality_score');
        });
    }
};
