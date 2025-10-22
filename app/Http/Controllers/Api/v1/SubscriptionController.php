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

    // Subscribe (RevenueCat Integration)
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
        $payment = new Payment();

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

        $payment->user_id = $user->id;
        $payment->subscription_id = $subscription->id;
        $payment->date = now();
        $payment->amount = $request->amount ;
        $payment->platform = $request->platform;
        $payment->transaction_id = $request->transaction_id;
        $payment->original_transaction_id = $request->original_transaction_id;

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

    // Subscription Restore
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
                        ->where('subscription_id', $subscription->id)
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

    // Payment
    public function payments(Request $request)
    {
        $user = $request->user();
        $payments = Cache::rememberForever("payments_{$user->id}", function () use ($user){
            $payments = Payment::select('date', 'amount', 'subscriptions.name AS subscription_name', 'duration', 'duration_type')
                            ->join('subscriptions', 'subscriptions.id', 'subscription_id')
                            ->where('user_id', $user->id)
                            ->orderBy('payments.id', 'DESC')
                            ->get();
            return $payments;
        });
        
        $status = true;

        return response()->json(['status' => $status, 'data' => $payments]);
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
