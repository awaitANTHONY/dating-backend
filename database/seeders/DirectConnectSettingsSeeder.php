<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class DirectConnectSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Feature toggle
            'enable_direct_connect'     => '1',

            // How many contact-slots a user can fill per tier
            'dc_contact_limit_free'     => '0',
            'dc_contact_limit_premium'  => '2',
            'dc_contact_limit_gold'     => '4',
            'dc_contact_limit_vip'      => '100',

            // Daily FREE request quota per tier (no coins needed within quota)
            'dc_free_requests_free'     => '0',
            'dc_free_requests_premium'  => '3',
            'dc_free_requests_gold'     => '5',
            'dc_free_requests_vip'      => '10',

            // Coin cost when free quota is exhausted
            'dc_coin_cost_default'      => '5',
            'dc_coin_cost_vip'          => '3',

            // How many approvals a user can give per day per tier
            'dc_approval_limit_free'    => '5',
            'dc_approval_limit_premium' => '10',
            'dc_approval_limit_gold'    => '20',
            'dc_approval_limit_vip'     => '100',

            // Cost for recipient to approve (0 = free to approve)
            'dc_approval_coin_cost'     => '0',

            // Hours before a pending request auto-expires
            'dc_request_expiry_hours'   => '72',

            // Daily login coin rewards per tier
            'coin_daily_login'   => '5',   // free
            'coin_daily_premium' => '5',
            'coin_daily_gold'    => '10',
            'coin_daily_vip'     => '15',
        ];

        foreach ($defaults as $name => $value) {
            Setting::firstOrCreate(
                ['name' => $name],
                ['value' => $value]
            );
        }
    }
}
