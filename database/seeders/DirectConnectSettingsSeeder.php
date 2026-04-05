<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class DirectConnectSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'enable_direct_connect' => '1',
            'dc_contact_limit_free' => '0',
            'dc_contact_limit_premium' => '2',
            'dc_contact_limit_gold' => '4',
            'dc_contact_limit_vip' => '100',
            'dc_free_requests_free' => '0',
            'dc_free_requests_premium' => '3',
            'dc_free_requests_gold' => '5',
            'dc_free_requests_vip' => '10',
            'dc_coin_cost_default' => '5',
            'dc_coin_cost_vip' => '3',
            'dc_request_expiry_hours' => '72',
        ];

        foreach ($defaults as $name => $value) {
            if (!Setting::where('name', $name)->exists()) {
                Setting::insert([
                    'name' => $name,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
