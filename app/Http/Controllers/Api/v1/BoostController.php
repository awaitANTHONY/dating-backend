<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BoostPackage;
use App\Models\ProfileBoost;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class BoostController extends Controller
{
    /**
     * Get Available Boost Packages
     */
    public function boost_packages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'required|string|in:ios,android'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $platform = $request->platform;

        $packages = Cache::rememberForever("boost_packages_$platform", function () use ($platform){
            $where = [];
            $where['status'] = 1;
            $where['platform'] = $platform;
            
            $packages = BoostPackage::where($where)
                                  ->orWhere(function($query) {
                                      $query->where('status', 1)
                                            ->where('platform', 'both');
                                  })
                                  ->orderBy('position', 'ASC')
                                  ->get();

            return $packages->map(function($package) {
                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'description' => $package->description,
                    'boost_count' => $package->boost_count,
                    'boost_duration' => $package->boost_duration,
                    'product_id' => $package->product_id,
                    'platform' => $package->platform
                ];
            });
        });
        
        $status = true;

        return response()->json([
            'status' => $status, 
            'data' => [
                'packages' => $packages
            ]
        ]);
    }

    /**
     * Activate Profile Boost
     */
    public function activate_boost(Request $request)
    {
        $user = $request->user();

        // Check if user already has an active boost
        $activeBoost = ProfileBoost::getActiveBoost($user->id);
        if ($activeBoost) {
            return response()->json([
                'status' => false,
                'message' => 'You already have an active boost.',
                'data' => [
                    'expires_at' => $activeBoost->expires_at->toISOString(),
                    'remaining_minutes' => $activeBoost->expires_at->diffInMinutes(now())
                ]
            ]);
        }

        // Get available boost (oldest purchased first)
        $availableBoost = ProfileBoost::where('user_id', $user->id)
                                    ->where('status', 'purchased')
                                    ->orderBy('created_at', 'asc')
                                    ->first();

        if (!$availableBoost) {
            return response()->json([
                'status' => false,
                'message' => 'No boosts available. Purchase boosts first.',
                'data' => [
                    'boost_packages' => BoostPackage::getPackagesForApi($request->header('Platform'))
                ]
            ]);
        }

        // Activate the boost
        if ($availableBoost->activate()) {
            // Clear recommendations cache to include boosted profile
            $this->clearRecommendationsCache();
            
            // Get boost duration from package
            $boostDuration = $availableBoost->boostPackage ? $availableBoost->boostPackage->boost_duration : 30;
            
            return response()->json([
                'status' => true,
                'message' => "Boost activated! You are now the top profile for {$boostDuration} minutes.",
                'data' => [
                    'boost_id' => $availableBoost->id,
                    'activated_at' => $availableBoost->activated_at->toISOString(),
                    'expires_at' => $availableBoost->expires_at->toISOString(),
                    'remaining_minutes' => $boostDuration,
                    'available_boosts' => $this->getAvailableBoosts($user->id)
                ]
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Failed to activate boost. Please try again.'
        ]);
    }

    /**
     * Get Boost Status
     */
    public function boost_status(Request $request)
    {
        $user = $request->user();

        $activeBoost = ProfileBoost::getActiveBoost($user->id);
        $availableBoosts = $this->getAvailableBoosts($user->id);

        return response()->json([
            'status' => true,
            'data' => [
                'has_active_boost' => $activeBoost !== null,
                'active_boost' => $activeBoost ? [
                    'boost_id' => $activeBoost->id,
                    'activated_at' => $activeBoost->activated_at->toISOString(),
                    'expires_at' => $activeBoost->expires_at->toISOString(),
                    'remaining_minutes' => max(0, $activeBoost->expires_at->diffInMinutes(now()))
                ] : null,
                'available_boosts' => $availableBoosts,
                'boost_packages' => BoostPackage::getPackagesForApi($request->header('Platform'))
            ]
        ]);
    }

    /**
     * Get Available Boosts Count
     */
    private function getAvailableBoosts($userId)
    {
        return ProfileBoost::where('user_id', $userId)
                         ->where('status', 'purchased')
                         ->count();
    }

    /**
     * Clear recommendations cache
     */
    private function clearRecommendationsCache()
    {
        // Clear all recommendation caches when boost is activated
        Cache::flush(); // You might want to be more specific here
    }
}
