<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 1 Monetization Settings — deploy to VPS immediately.
     *
     * 1. Unlock contacts for free users (2 contacts)
     * 2. Give free users 1 free request/day
     * 3. Increase coin rewards
     * 4. Add approval limit settings (for Phase 2 app update)
     * 5. Add daily_like_limit and daily_chat_limit to settings table
     * 6. Add allow_contact_in_bio toggle
     */
    public function up(): void
    {
        $settings = [
            // ── Unlock contacts for free users ──────────────────────────────
            ['name' => 'dc_contact_limit_free', 'value' => '2'],
            ['name' => 'dc_contact_limit_premium', 'value' => '4'],
            ['name' => 'dc_contact_limit_gold', 'value' => '6'],

            // ── Give free users 1 free request/day ─────────────────────────
            ['name' => 'dc_free_requests_free', 'value' => '1'],
            ['name' => 'dc_free_requests_premium', 'value' => '5'],
            ['name' => 'dc_free_requests_gold', 'value' => '10'],

            // ── Increase coin rewards ───────────────────────────────────────
            ['name' => 'coin_daily_login', 'value' => '5'],
            ['name' => 'coin_complete_profile', 'value' => '10'],

            // ── Daily limits (now server-configurable) ─────────────────────
            ['name' => 'daily_like_limit', 'value' => '25'],
            ['name' => 'daily_chat_limit', 'value' => '5'],

            // ── Bio filter toggle (0=enforce, 1=allow contact info) ────────
            ['name' => 'allow_contact_in_bio', 'value' => '0'],

            // ── Approval limits (Phase 2 — prepared now) ───────────────────
            ['name' => 'dc_approval_limit_free', 'value' => '3'],
            ['name' => 'dc_approval_limit_premium', 'value' => '10'],
            ['name' => 'dc_approval_limit_gold', 'value' => '25'],
            ['name' => 'dc_approval_limit_vip', 'value' => '999'],
            ['name' => 'dc_approval_coin_cost', 'value' => '3'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['name' => $setting['name']],
                ['value' => $setting['value']]
            );
        }
    }

    /**
     * Reverse the migration — restore original values.
     */
    public function down(): void
    {
        $restoreSettings = [
            ['name' => 'dc_contact_limit_free', 'value' => '0'],
            ['name' => 'dc_contact_limit_premium', 'value' => '2'],
            ['name' => 'dc_contact_limit_gold', 'value' => '4'],
            ['name' => 'dc_free_requests_free', 'value' => '0'],
            ['name' => 'dc_free_requests_premium', 'value' => '3'],
            ['name' => 'dc_free_requests_gold', 'value' => '5'],
            ['name' => 'coin_daily_login', 'value' => '1'],
            ['name' => 'coin_complete_profile', 'value' => '5'],
        ];

        foreach ($restoreSettings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['name' => $setting['name']],
                ['value' => $setting['value']]
            );
        }

        // Remove new settings
        DB::table('settings')->whereIn('name', [
            'daily_like_limit',
            'daily_chat_limit',
            'allow_contact_in_bio',
            'dc_approval_limit_free',
            'dc_approval_limit_premium',
            'dc_approval_limit_gold',
            'dc_approval_limit_vip',
            'dc_approval_coin_cost',
        ])->delete();
    }
};
