<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceMail;
use App\Models\Subscription;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Utils\Overrider;

class SubscriptionController extends Controller
{
    // Subscription List
    public function subscriptions(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'platform' => 'required|string|in:ios,android'
            
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $platform = $request->platform;

        $subscriptions = Cache::rememberForever("subscriptions_$platform", function () use ($platform){

            $where = [];
            $where['status'] = 1;
            $where['platform'] = $platform;
            $subscriptions = Subscription::where($where)
                                            ->orderBy('position', 'ASC')
                                            ->get();

            return $subscriptions;
        });
        
        $status = true;

        return response()->json(['status' => $status, 'data' => $subscriptions]);
    }

    // Subscription Expired
    public function subscription_expired(Request $request)
    {
        $user = $request->user();

        $user->subscription_id = 0;
        $user->expired_at = null;

        $user->save();

        $user->email = $user->email;
        $user->subscription_name = $user->subscription->name;
        
        return response()->json([
            'status' => true,
            'data' => $user->makeHidden(['id', 'user_type', 'created_at', 'updated_at', 'apps', 'app_id', 'email_verified_at', 'status', 'subscription']),
        ]);
    }
}
