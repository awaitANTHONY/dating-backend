<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix coin economy: daily login must equal 1 contact request cost (5 coins).
     */
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'coin_daily_login'],
            ['value' => '5']
        );
    }

    public function down(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'coin_daily_login'],
            ['value' => '3']
        );
    }
};
