<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coin_transactions', function (Blueprint $table) {
            $table->string('description')->nullable()->after('status');
            $table->string('reference_type')->nullable()->after('description');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
        });
    }

    public function down(): void
    {
        Schema::table('coin_transactions', function (Blueprint $table) {
            $table->dropColumn(['description', 'reference_type', 'reference_id']);
        });
    }
};
