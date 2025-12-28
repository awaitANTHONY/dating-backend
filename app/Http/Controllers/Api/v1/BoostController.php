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
            'data' => $packages
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
}
