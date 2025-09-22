<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Setting;

use App\Models\Slider;
use App\Models\Interest;
use App\Models\Language;
use App\Models\RelationGoal;
use App\Models\Religion;

use Illuminate\Http\Request;
use Cache;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Models\Subscriber;

class ApiController extends Controller
{
    public function settings(Request $request)
    {
        $validator = \Validator::make($request->all(), [

            'platform' => 'required',
            
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()]);
        }

        $not_allowed_keys = [

            'site_title',
            'android_onesignal_app_id',
            'android_onesignal_api_key',
            'android_firebase_server_key',
            'android_firebase_topics',
            'ios_onesignal_app_id',
            'ios_onesignal_api_key',
            'ios_firebase_server_key',
            'ios_firebase_topics',

        ];

        $platform = $request->platform;
        $hidden_platform = ($platform == 'ios' ? 'android' : 'ios');
        
        $settings = Cache::rememberForever("settings", function () use ($request, $platform, $hidden_platform, $not_allowed_keys){
            $settings = Setting::select("*", \DB::raw("REPLACE(name, '{$platform}_', '') as name"))
                                ->where("name", "not like", "%{$hidden_platform}%")
                                ->whereNotIn("name", $not_allowed_keys)
                                ->pluck("value", "name")
                                ->toArray();
            return $settings;
        });

        return response()->json(['status' => true, 'data' => $settings]);
    }

    public function sliders(Request $request)
    {
        $base_url = url('/');
        
        $sliders = Cache::rememberForever("sliders", function (){
            $sliders = Slider::where('status', 1)
                                ->orderBy('id', 'DESC')
                                ->get();

            return $sliders;
        });

        return response()->json(['status' => true, 'data' => $sliders]);
    }

    public function interests(Request $request)
    {
        $base_url = url('/');

        $interests = Cache::rememberForever("interests", function (){
            $interests = Interest::where('status', 1)
                                    ->orderBy('id', 'DESC')
                                    ->get();

            return $interests;
        });

        return response()->json(['status' => true, 'data' => $interests]);
    }

    public function languages(Request $request)
    {
        $base_url = url('/');

        $languages = Cache::rememberForever("languages", function (){
            $languages = Language::where('status', 1)
                                    ->orderBy('id', 'DESC')
                                    ->get();

            return $languages;
        });

        return response()->json(['status' => true, 'data' => $languages]);
    }

    public function relation_goals(Request $request)
    {
        $base_url = url('/');

        $relation_goals = Cache::rememberForever("relation_goals", function (){
            $relation_goals = RelationGoal::where('status', 1)
                                    ->orderBy('id', 'DESC')
                                    ->get();

            return $relation_goals;
        });

        return response()->json(['status' => true, 'data' => $relation_goals]);
    }

    public function religions(Request $request)
    {
        $base_url = url('/');

        $religions = Cache::rememberForever("religions", function (){
            $religions = Religion::where('status', 1)
                                    ->orderBy('id', 'DESC')
                                    ->get();

            return $religions;
        });

        return response()->json(['status' => true, 'data' => $religions]);
    }

    public function pre_signup(Request $request)
    {
        $data = [
            'status' => true,
            'sliders' => $this->sliders($request)->getData()->data,
            'interests' => $this->interests($request)->getData()->data,
            'languages' => $this->languages($request)->getData()->data,
            'relation_goals' => $this->relation_goals($request)->getData()->data,
            'religions' => $this->religions($request)->getData()->data
        ];
        return response()->json(['status' => true, 'data' => $data]);
    }
}