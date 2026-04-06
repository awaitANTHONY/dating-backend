<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $settings = [
        'coin_daily_login'      => '1',
        'coin_follow_social'    => '5',
        'coin_referral'         => '10',
        'coin_complete_profile' => '5',
        'coin_daily_premium'    => '2',
        'coin_daily_gold'       => '5',
        'coin_daily_vip'        => '10',
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->settings as $name => $value) {
            // Only insert if it doesn't already exist
            if (!DB::table('settings')->where('name', $name)->exists()) {
                DB::table('settings')->insert([
                    'name'       => $name,
                    'value'      => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->whereIn('name', array_keys($this->settings))
            ->delete();
    }
};
