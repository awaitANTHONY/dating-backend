<?php

namespace App\Http\Controllers\Api\v1\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class CoinRewardSettingsController extends Controller
{
    private array $fields = [
        'coin_daily_login',
        'coin_follow_social',
        'coin_referral',
        'coin_complete_profile',
        'coin_daily_premium',
        'coin_daily_gold',
        'coin_daily_vip',
    ];

    private array $defaults = [
        'coin_daily_login'      => '1',
        'coin_follow_social'    => '5',
        'coin_referral'         => '10',
        'coin_complete_profile' => '5',
        'coin_daily_premium'    => '2',
        'coin_daily_gold'       => '5',
        'coin_daily_vip'        => '10',
    ];

    /**
     * GET /api/v1/admin/coin-reward-settings
     * Returns current coin reward configuration.
     */
    public function index(): JsonResponse
    {
        $data = [];
        foreach ($this->fields as $field) {
            $data[$field] = (int) get_option($field, $this->defaults[$field] ?? '0');
        }

        return response()->json([
            'status' => true,
            'data'   => $data,
        ]);
    }

    /**
     * PUT /api/v1/admin/coin-reward-settings
     * Update coin reward configuration.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'coin_daily_login'      => 'sometimes|integer|min:0|max:100',
            'coin_follow_social'    => 'sometimes|integer|min:0|max:100',
            'coin_referral'         => 'sometimes|integer|min:0|max:100',
            'coin_complete_profile' => 'sometimes|integer|min:0|max:100',
            'coin_daily_premium'    => 'sometimes|integer|min:0|max:100',
            'coin_daily_gold'       => 'sometimes|integer|min:0|max:100',
            'coin_daily_vip'        => 'sometimes|integer|min:0|max:100',
        ]);

        $now = now();

        foreach ($this->fields as $field) {
            if ($request->has($field)) {
                DB::table('settings')->updateOrInsert(
                    ['name' => $field],
                    ['value' => (string) $request->input($field), 'updated_at' => $now]
                );
            }
        }

        // Clear settings cache so changes take effect immediately
        \Cache::forget('settings');

        // Return updated values
        $data = [];
        foreach ($this->fields as $field) {
            $data[$field] = (int) get_option($field, $this->defaults[$field] ?? '0');
        }

        return response()->json([
            'status'  => true,
            'message' => 'Coin reward settings updated successfully',
            'data'    => $data,
        ]);
    }
}
