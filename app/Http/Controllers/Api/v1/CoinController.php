<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\DirectConnectRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CoinController extends Controller
{
    // ─────────────────────────────────────────────
    // GET /api/v1/coins/balance
    // Returns user's coin balance + daily free requests remaining
    // ─────────────────────────────────────────────
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();

        $freeRequestsRemaining = $this->getFreeRequestsLeft($user);

        return response()->json([
            'status' => true,
            'data'   => [
                'balance'                  => (int) $user->coin_balance,
                'free_requests_remaining'  => $freeRequestsRemaining,
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/v1/coin-packages
    // Returns available coin packages to purchase
    // ─────────────────────────────────────────────
    public function packages(): JsonResponse
    {
        $packages = Cache::remember('coin_packages', 300, function () {
            return \App\Models\Package::where('status', 1)
                ->orderBy('coins', 'asc')
                ->get(['id', 'coins', 'amount', 'product_id'])
                ->map(fn ($p) => [
                    'id'         => $p->id,
                    'coins'      => (int) $p->coins,
                    'amount'     => (float) $p->amount,
                    'product_id' => $p->product_id ?? '',
                ]);
        });

        return response()->json(['status' => true, 'data' => $packages]);
    }

    // ─────────────────────────────────────────────
    // POST /api/v1/coins/purchase
    // Body: { package_id, receipt / transaction_id }
    // Called by Flutter after in-app-purchase completes
    // ─────────────────────────────────────────────
    public function purchase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => 'required|integer|exists:packages,id',
        ]);

        $package = \App\Models\Package::find($validated['package_id']);
        if (!$package) {
            return response()->json(['status' => false, 'message' => 'Package not found.'], 404);
        }

        $user = $request->user();
        User::where('id', $user->id)->increment('coin_balance', $package->coins);

        CoinTransaction::create([
            'user_id' => $user->id,
            'amount'  => $package->coins,
            'status'  => 'Credit',
        ]);

        $user->refresh();

        return response()->json([
            'status'  => true,
            'message' => "{$package->coins} coins added to your balance.",
            'data'    => [
                'balance' => (int) $user->coin_balance,
                'coins_added' => (int) $package->coins,
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/v1/coin-rewards/status
    // Returns whether today's daily login reward is available
    // ─────────────────────────────────────────────
    public function rewardStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        $claimed = CoinTransaction::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->where('status', 'Credit')
            ->where('amount', $this->getDailyLoginCoins($user))
            ->exists();

        return response()->json([
            'status' => true,
            'data'   => [
                'can_claim'   => !$claimed,
                'reward_coins' => $this->getDailyLoginCoins($user),
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /api/v1/coin-rewards/claim
    // Claim daily login reward coins
    // ─────────────────────────────────────────────
    public function rewardClaim(Request $request): JsonResponse
    {
        $user = $request->user();
        $reward = $this->getDailyLoginCoins($user);

        // Check if already claimed today
        $alreadyClaimed = CoinTransaction::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->where('status', 'Credit')
            ->where('amount', $reward)
            ->exists();

        if ($alreadyClaimed) {
            return response()->json([
                'status'  => false,
                'message' => 'Daily reward already claimed.',
                'data'    => ['balance' => (int) $user->coin_balance],
            ], 409);
        }

        User::where('id', $user->id)->increment('coin_balance', $reward);
        CoinTransaction::create([
            'user_id' => $user->id,
            'amount'  => $reward,
            'status'  => 'Credit',
        ]);

        $user->refresh();

        return response()->json([
            'status'  => true,
            'message' => "{$reward} coins claimed!",
            'data'    => [
                'balance'      => (int) $user->coin_balance,
                'coins_earned' => $reward,
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function getFreeRequestsLeft(User $user): int
    {
        $tier  = $this->getUserTier($user);
        $key   = "dc_free_requests_{$tier}";
        $daily = (int) (Setting::where('name', $key)->value('value') ?? 0);

        if ($daily <= 0) return 0;

        $usedToday = DirectConnectRequest::where('requester_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        return max(0, $daily - $usedToday);
    }

    private function getUserTier(User $user): string
    {
        if ($user->isVipActive()) return 'vip';

        $sub = $user->subscription;
        if (!$sub || $sub->id === 0) return 'free';

        $name = strtolower($sub->name ?? '');
        if (str_contains($name, 'gold')) return 'gold';
        if (str_contains($name, 'premium') || str_contains($name, 'plus')) return 'premium';

        return 'free';
    }

    private function getDailyLoginCoins(User $user): int
    {
        $tier = $this->getUserTier($user);
        $keyMap = [
            'vip'     => 'coin_daily_vip',
            'gold'    => 'coin_daily_gold',
            'premium' => 'coin_daily_premium',
            'free'    => 'coin_daily_login',
        ];
        $key = $keyMap[$tier] ?? 'coin_daily_login';
        return (int) (Setting::where('name', $key)->value('value') ?? 5);
    }
}
