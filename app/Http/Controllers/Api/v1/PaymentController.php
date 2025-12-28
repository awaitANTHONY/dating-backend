<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceMail;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserInformation;
use App\Models\BoostPackage;
use App\Models\ProfileBoost;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Utils\Overrider;

class PaymentController extends Controller
{
    // Subscribe (RevenueCat Integration) - Moved from SubscriptionController
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|string',
            'transaction_id' => 'nullable|string',
            'original_transaction_id' => 'nullable|string',
            'amount' => 'nullable|string',
            'platform' => 'required|string|in:ios,android'
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();

        // Find subscription by product_id (RevenueCat product identifier)
        $subscription = Subscription::where('product_id', $request->product_id)->first();
        
        if (!$subscription) {
            return response()->json(['result' => false, 'message' => 'Product not found.']);
        }
    
        if($user->expired_at != null && $user->expired_at > now())
        {
            $expired_at = $user->expired_at;
            $date = date("Y-m-d H:i:s", strtotime("+{$subscription->duration} {$subscription->duration_type}", \Carbon\Carbon::parse($expired_at)->timestamp));
        } else {
            $date = date("Y-m-d H:i:s", strtotime("+{$subscription->duration} {$subscription->duration_type}", now()->timestamp));
        }

        $user->expired_at = $date;
        $user->subscription_id = $subscription->id;
        $user->save();

        // Create payment record
        $payment = new Payment();
        $payment->user_id = $user->id;
        $payment->title = $subscription->name;
        $payment->date = now();
        $payment->amount = $request->amount;
        $payment->platform = $request->platform;
        $payment->transaction_id = $request->transaction_id;
        $payment->original_transaction_id = $request->original_transaction_id;
        $payment->payment_type = 'subscription';
        $payment->save();

        Cache::forget("payments_" . $user->id);

        $user->subscription_name = $user->subscription->name;

        // Send Mail
        $this->send_mail($user, $payment);
        
        return response()->json([
            'status' => true,
            'data' => $user->makeHidden(['id', 'user_type', 'created_at', 'updated_at', 'apps', 'app_id', 'email_verified_at', 'status', 'subscription', 'verification', 'forget_code']),
        ]);
    }

    // Verification Payment
    public function purchase_verification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string',
            'original_transaction_id' => 'nullable|string',
            'amount' => 'required|numeric', // Minimum verification amount
            'platform' => 'required|string|in:ios,android',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();

        // Check if user already verified
        $userInfo = $user->user_information;
        if ($userInfo && $userInfo->is_verified) {
            return response()->json([
                'status' => false, 
                'message' => 'User is already verified.'
            ]);
        }

        // Create verification payment record
        $payment = new Payment();
        $payment->user_id = $user->id;
        $payment->title = 'Account Verification';
        $payment->date = now();
        $payment->amount = $request->amount;
        $payment->platform = $request->platform;
        $payment->transaction_id = $request->transaction_id;
        $payment->original_transaction_id = $request->original_transaction_id;
        $payment->payment_type = 'verification';
        $payment->save();

        // Update user verification status
        if (!$userInfo) {
            $userInfo = new UserInformation();
            $userInfo->user_id = $user->id;
        }
        $userInfo->is_verified = true;
        $userInfo->save();

        // Clear caches
        Cache::forget("payments_" . $user->id);
        
        // Clear profile completion cache since verification status changed
        $this->clearProfileCompletionCache($user->id);

        return response()->json([
            'status' => true,
            'message' => 'Verification successful!',
            
        ]);
    }

    // Payment History
    public function payments(Request $request)
    {
        $user = $request->user();
        $payments = Cache::remember("payments_{$user->id}", 3600, function () use ($user){
            $payments = Payment::where('user_id', $user->id)
                ->orderBy('id', 'DESC')
                ->get();
                
            return $payments;
        });
        
        return response()->json(['status' => true, 'data' => $payments]);
    }

    // Subscription Restore - Moved from SubscriptionController
    public function subscription_restore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();

        $subscription = Subscription::where('product_id', $request->product_id)->first();
        
        if(!$subscription){
            return response()->json(['result' => false, 'message' => 'Product not found.']);
        }

        $payment = Payment::where('user_id', $user->id)
                        ->where('payment_type', 'subscription')
                        ->latest()
                        ->first();

        if(!$payment){
            return response()->json(['result' => false, 'message' => 'illegal activity.']);
        }
                      
        if($user->expired_at != null && $user->expired_at > now()){
            $expired_at = $user->expired_at;
            $date = date("Y-m-d H:i:s", strtotime("+{$subscription->duration} {$subscription->duration_type}", \Carbon\Carbon::parse($expired_at)->timestamp));
        }else{
            $date = date("Y-m-d H:i:s", strtotime("+{$subscription->duration} {$subscription->duration_type}", now()->timestamp));
        }

        $user->expired_at = $date;
        $user->subscription_id = $subscription->id;
        $user->save();

        $user->subscription_name = $user->subscription->name;
        
        return response()->json([
            'status' => true,
            'user' => $user->makeHidden(['id', 'user_type', 'created_at', 'updated_at', 'apps', 'app_id', 'email_verified_at', 'status', 'subscription']),
        ]);
    }

    // Purchase Profile Boost
    public function purchase_boost(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|string',
            'transaction_id' => 'required|string',
            'original_transaction_id' => 'nullable|string',
            'amount' => 'required|string',
            'platform' => 'required|string|in:ios,android',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();

        // Find boost package by product_id
        $boostPackage = BoostPackage::findByProductId($request->product_id);
        
        if (!$boostPackage) {
            return response()->json(['status' => false, 'message' => 'Invalid boost package.']);
        }

        // Verify platform compatibility
        if ($boostPackage->platform !== 'both' && $boostPackage->platform !== $request->platform) {
            return response()->json(['status' => false, 'message' => 'Package not available for this platform.']);
        }

        // Create boost record and auto-activate
        $boost = new ProfileBoost();
        $boost->user_id = $user->id;
        $boost->boost_package_id = $boostPackage->id;
        $boost->status = 'purchased';
        $boost->save();

        // Auto-activate the boost immediately
        $boost->activate();
        
        // Clear recommendations cache to prioritize boosted profile
        Cache::flush();

        // Create payment record for boost
        $payment = new Payment();
        $payment->user_id = $user->id;
        $payment->title = $boostPackage->name . ' - Profile Boost';
        $payment->date = now();
        $payment->amount = $request->amount;
        $payment->platform = $request->platform;
        $payment->transaction_id = $request->transaction_id;
        $payment->original_transaction_id = $request->original_transaction_id;
        $payment->payment_type = 'boost';
        $payment->save();

        // Clear cache
        Cache::forget("payments_" . $user->id);

        // Get boost duration
        $boostDuration = $boostPackage->boost_duration ?? 30;

        return response()->json([
            'status' => true,
            'message' => "Boost purchased and activated! You are top profile for {$boostDuration} minutes.",
            'data' => [
                'package_name' => $boostPackage->name,
                'boost_count' => $boostPackage->boost_count,
                'boost_duration' => $boostPackage->boost_duration,
                'boost_active' => true,
                'activated_at' => $boost->activated_at->toISOString(),
                'expires_at' => $boost->expires_at->toISOString(),
                'remaining_minutes' => $boostDuration,
                'available_boosts' => $this->getAvailableBoosts($user->id)
            ]
        ]);
    }

    // Purchase VIP Status
    public function purchase_vip(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|string',
            'platform' => 'required|string|in:ios,android',
            'transaction_id' => 'required|string',
            'original_transaction_id' => 'nullable|string',
            'duration' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();

        // Get VIP duration from request
        $vipDuration = $request->duration; // days
        
        // If user has active VIP, extend from current expiration date
        // Otherwise, start from now
        if ($user->is_vip && $user->vip_expire && $user->vip_expire > now()) {
            $vipExpireDate = Carbon::parse($user->vip_expire)->addDays($vipDuration);
        } else {
            $vipExpireDate = now()->addDays($vipDuration);
        }

        // Update user VIP status
        $user->is_vip = true;
        $user->vip_expire = $vipExpireDate;
        $user->save();

        // Create payment record for VIP
        $payment = new Payment();
        $payment->user_id = $user->id;
        $payment->title = 'VIP Membership';
        $payment->date = now();
        $payment->amount = $request->amount;
        $payment->platform = $request->platform;
        $payment->transaction_id = $request->transaction_id;
        $payment->original_transaction_id = $request->original_transaction_id;
        $payment->payment_type = 'vip';
        $payment->save();

        // Clear cache
        Cache::forget("payments_" . $user->id);

        return response()->json([
            'status' => true,
            'message' => "VIP membership activated for {$vipDuration} days!",
            'data' => [
                'is_vip' => true,
                'vip_expire' => $user->vip_expire->toISOString(),
                'activated_at' => now()->toISOString(),
                'duration_days' => $vipDuration,
                'remaining_days' => $vipDuration
            ]
        ]);
    }

    // Clear profile completion cache
    private function clearProfileCompletionCache($userId)
    {
        Cache::forget("profile_completion_user_{$userId}");
    }

    // Get available boosts for user
    private function getAvailableBoosts($userId)
    {
        return ProfileBoost::where('user_id', $userId)
            ->where('status', 'purchased')
            ->count();
    }

    // Send Mail
    public function send_mail($user, $payment)
    {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        \App\Utils\Overrider::load('Settings');

        try {
            Mail::to($user->email)->send(new InvoiceMail($user, $payment));
            return json_encode(array('result' => true, 'message' => _lang('Your Message send sucessfully.')));
        } catch (\Exception $e) {
            return json_encode(array('result' => false, 'message' => _lang('Error Occured, Please try again !')));
        }
    }
}
