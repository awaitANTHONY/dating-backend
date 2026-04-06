<?php

namespace App\Console\Commands;

use App\Models\CoinTransaction;
use App\Models\User;
use App\Services\SubscriptionTierService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GrantDailySubscriberCoins extends Command
{
    protected $signature = 'coins:grant-daily-subscriber';
    protected $description = 'Grant daily free coins to subscribers based on their tier';

    public function handle(): void
    {
        $tierAmounts = [
            'premium' => (int) get_option('coin_daily_premium', '2'),
            'gold'    => (int) get_option('coin_daily_gold', '5'),
            'vip'     => (int) get_option('coin_daily_vip', '10'),
        ];

        $today   = Carbon::today();
        $granted = 0;

        // Get all potentially active subscribers:
        // - Users with an active subscription (subscription_id set, not expired)
        // - Users with active VIP (is_vip = true, vip_expire in future)
        $users = User::where(function ($q) {
            $q->where(function ($sub) {
                $sub->where('subscription_id', '!=', 0)
                    ->whereNotNull('subscription_id');
            })->orWhere(function ($vip) {
                $vip->where('is_vip', true)
                    ->where('vip_expire', '>', now());
            });
        })->get();

        foreach ($users as $user) {
            // Determine actual tier using shared service (no RevenueCat in CLI context)
            $tier = SubscriptionTierService::getTier($user);
            if (!$tier) continue;

            $coins = $tierAmounts[$tier] ?? 0;
            if ($coins <= 0) continue;

            // Skip if already granted today
            $alreadyGranted = CoinTransaction::where('user_id', $user->id)
                ->where('type', 'daily_grant')
                ->whereDate('created_at', $today)
                ->exists();

            if ($alreadyGranted) continue;

            CoinTransaction::create([
                'user_id'     => $user->id,
                'amount'      => $coins,
                'type'        => 'daily_grant',
                'description' => 'Daily ' . ucfirst($tier) . ' coin grant',
            ]);

            $user->increment('coin_balance', $coins);
            $granted++;
        }

        $this->info("Granted daily coins to {$granted} subscribers.");
    }
}
