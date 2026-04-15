<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'enable_popular'],
            ['value' => '1']
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('name', 'enable_popular')->delete();
    }
};
