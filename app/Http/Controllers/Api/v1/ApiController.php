<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Setting;

use App\Models\Slider;
use App\Models\Interest;
use App\Models\Language;
use App\Models\RelationGoal;
use App\Models\Religion;
use App\Models\RelationshipStatus;
use App\Models\Ethnicity;
use App\Models\Education;
use App\Models\CareerField;

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
            'from_mail',
            "from_name",
            "smtp_host",
            "smtp_port",
            "smtp_username",
            "smtp_password",
            "smtp_encryption",
            "firebase_project_id",
            "firebase_database_url",
            "firebase_topics",

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

    public function relationship_statuses(Request $request)
    {
        $relationship_statuses = Cache::rememberForever("relationship_statuses", function (){
            $relationship_statuses = RelationshipStatus::where('status', 1)
                                    ->orderBy('id', 'DESC')
                                    ->get();

            return $relationship_statuses;
        });

        return response()->json(['status' => true, 'data' => $relationship_statuses]);
    }

    public function ethnicities(Request $request)
    {
        $ethnicities = Cache::rememberForever("ethnicities", function (){
            $ethnicities = Ethnicity::where('status', 1)
                                    ->orderBy('id', 'DESC')
                                    ->get();

            return $ethnicities;
        });

        return response()->json(['status' => true, 'data' => $ethnicities]);
    }

    public function educations(Request $request)
    {
        $educations = Cache::rememberForever("educations", function (){
            $educations = Education::where('status', 1)
                                    ->orderBy('id', 'DESC')
                                    ->get();

            return $educations;
        });

        return response()->json(['status' => true, 'data' => $educations]);
    }

    public function career_fields(Request $request)
    {
        $career_fields = Cache::rememberForever("career_fields", function (){
            $career_fields = CareerField::where('status', 1)
                                    ->orderBy('id', 'DESC')
                                    ->get();

            return $career_fields;
        });

        return response()->json(['status' => true, 'data' => $career_fields]);
    }

    public function onboarding(Request $request)
    {
        $data = [
            'status' => true,
            'sliders' => $this->sliders($request)->getData()->data,
            'interests' => $this->interests($request)->getData()->data,
            'languages' => $this->languages($request)->getData()->data,
            'relation_goals' => $this->relation_goals($request)->getData()->data,
            'religions' => $this->religions($request)->getData()->data,
            'relationship_statuses' => $this->relationship_statuses($request)->getData()->data,
            'ethnicities' => $this->ethnicities($request)->getData()->data,
            'educations' => $this->educations($request)->getData()->data,
            'career_fields' => $this->career_fields($request)->getData()->data
        ];
        return response()->json(['status' => true, 'data' => $data]);
    }

    /**
     * Send push notification to a device
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function notification(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'device_token' => 'required|string',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'image' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'status' => false, 
                    'message' => $validator->errors()->first(),
                ], 500
            );
        }

        try {
            $deviceToken = $request->device_token;
            $title = $request->title;
            $message = $request->message;
            $image = $request->image ?? null;

            $result = send_notification(
                'single',
                $title,
                $message,
                $image,
                ['device_token' => $deviceToken]
            );

            if ($result) {
                return response()->json([
                    'status' => true,
                    'message' => 'Notification sent successfully.'
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to send notification.'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
            ], 500);
        }
    }
}