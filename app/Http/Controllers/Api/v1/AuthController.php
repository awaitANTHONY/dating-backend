<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserInformation;
use App\Models\AppModel;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\AbstractHandler;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Illuminate\Http\UploadedFile;
use App\Utils\Overrider;

class AuthController extends Controller
{
    public function signup(Request $request)
    {

        if($request->provider != 'email'){
            $request->merge([
                'password' => $request->device_token ?? rand(100000, 999999),
                'password_confirmation' => $request->device_token ?? rand(100000, 999999),
            ]);
        }

        $user = User::where('email', $request->email)->where('user_type', 'user')->first();
        if($request->provider != 'email' ){
            if($user){
                return $this->signin($request);
            }
        }

        if($user && $user->status == 3){
            $user->delete();
            $user = null;
        }

        $validator = \Validator::make($request->all(), [

            'name' => 'required|string|max:191',
            'email' => 'required|string|email|max:191|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'device_token' => 'required',
            'provider' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }
        
        $user = new User();

        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = \Hash::make($request->password);
        $user->user_type = 'user';
        $user->device_token = $request->device_token;
        $user->provider = $request->provider;
        $user->image = asset('public/default/profile.png');
        $user->status = $request->provider == 'email' ? 3 : 1;
        $user->last_activity = now(); // Set last activity on signup

        $isDevelopment = env('APP_DEBUG') == true;

        // Generate and send OTP if provider is email
        if ($request->provider == 'email') {
            $otp = $isDevelopment ? 111111 : rand(100000, 999999);
            $user->email_otp = \Hash::make($otp);
            $user->email_verified_at = null;
            if(!$isDevelopment){
                Overrider::load('Settings');
                \Mail::to($user->email)->send(new \App\Mail\EmailVerificationOtp($otp, $user->name));
            }
        }

        $user->save();

        $tokenResult = $user->createToken($request->device_token)->plainTextToken;
        
        $user->is_vip = (bool) $user->isVipActive();
        
        // Create Firebase custom token for non-email providers (email providers get it after verification)
        $firebaseToken = createFirebaseToken($user->id);
        
        return response()->json([
            'status' => true,
            'access_token' => $tokenResult,
            'firebase_token' => $firebaseToken,
            'data' =>  $user,
            'message' => $request->provider == 'email' ? 'Verification code sent to your email.' : 'Signup successful.'
        ]);
    }

    public function verification(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'otp' => 'required|digits:6',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found.']);
        }

        if (\Hash::check($request->otp, $user->email_otp)) {
            $user->email_verified_at = now();
            $user->status = 1;
            $user->email_otp = null;
            $user->save();

            $user->user_information = $user->user_information;
            $user->is_profile_completed = $user->user_information ? true : false;
            $user->is_vip = (bool) $user->isVipActive();

            // Create Firebase custom token
            $firebaseToken = createFirebaseToken($user->id);

            return response()->json([
                'status' => true, 
                'firebase_token' => $firebaseToken,
                'data' => $user, 
                'message' => 'Email verified successfully.'
            ]);
        } else {
            return response()->json(['status' => false, 'message' => 'Invalid OTP.']);
        }
    }

    /**
     * Resend OTP to the authenticated user's email.
     * Requires Bearer token authentication.
     */
    public function resend_otp(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found.'], 401);
        }
        if ($user->provider !== 'email') {
            return response()->json(['status' => false, 'message' => 'OTP is only available for email provider.'], 400);
        }
        $otp = rand(100000, 999999);
        $user->email_otp = \Hash::make($otp);
        $user->email_verified_at = null;
        $user->save();
        \App\Utils\Overrider::load('Settings');
        \Mail::to($user->email)->send(new \App\Mail\EmailVerificationOtp($otp, $user->name));
        return response()->json([
            'status' => true,
            'message' => 'A new verification code has been sent to your email.'
        ]);
    }

    public function signin(Request $request)
    {
        $validator = \Validator::make($request->all(), [

            'email' => 'required|string|email',
            'password' => 'required|string|min:6',
            'device_token' => 'required',
            'provider' => 'required',
            
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = User::where('email', $request->email)->where('user_type', 'user')->first();

        if(!$user ){
            return response()->json([
                'status' => false,
                'message' => 'These credentials do not match our records.',
            ]);
        }

        if($user && $user->status == 3){
            $user->delete();
            $user = null;
            return response()->json([
                'status' => false,
                'message' => 'These credentials do not match our records.',
            ]);
        }

        if($user->provider == 'email'){
            if (!\Hash::check($request->password, $user->password) ) {
                return response()->json([
                    'status' => false,
                    'message' => 'These credentials do not match our records.',
                ]);
            }
        }
        
        $user->tokens()->delete();

        $user->device_token = $request->device_token;
        $user->last_activity = now(); // Update last activity on signin

        $user->save();

        $user->is_profile_completed = $user->user_information ? true : false;
        $user->is_vip = (bool) $user->isVipActive();

        if($user->is_profile_completed) {
            $user->user_information = $user->user_information;
            $user->user_information->religion = $user->user_information ? $user->user_information->religion : null;
        }

        $tokenResult = $user->createToken($request->device_token)->plainTextToken;
        
        // Create Firebase custom token
        $firebaseToken = createFirebaseToken($user->id);
        
        return response()->json([
            'status' => true,
            'access_token' => $tokenResult,
            'firebase_token' => $firebaseToken,
            'data' => $user,
        ]);
    }

    public function user_information(Request $request)
    {
        // Define all possible validation rules
        $rules = [
            'name' => 'nullable|string|max:191',
            'bio' => 'nullable|string|max:1000',
            'gender' => 'nullable|in:male,female,other',
            'religion_id' => 'nullable|exists:religions,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'search_preference' => 'nullable|in:male,female',
            'relation_goals' => 'nullable|array',
            'interests' => 'nullable|array',
            'languages' => 'nullable|array',
            'wallet_balance' => 'nullable|numeric|min:0',
            'image' => 'nullable|image',
            'images' => 'nullable|array|max:3',
            'images.*' => 'nullable|image',
            'country_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'is_zodiac_sign_matter' => 'nullable|boolean',
            'is_food_preference_matter' => 'nullable|boolean',
            'age' => 'nullable|integer|min:18|max:100',
            'relationship_status_id' => 'nullable|exists:relationship_statuses,id',
            'ethnicity_id' => 'nullable|exists:ethnicities,id',
            'alkohol' => 'nullable|in:dont_drink,drink_frequently,drink_socially,prefer_not_to_say',
            'smoke' => 'nullable|in:dont_smoke,smoke_regularly,smoke_occasionally,prefer_not_to_say',
            'education_id' => 'nullable|exists:educations,id',
            'preffered_age' => 'nullable|string|max:100',
            'height' => 'nullable|integer|min:10|max:500',
            'carrer_field_id' => 'nullable|exists:career_fields,id',
            'address' => 'nullable|string',
            'activities' => 'nullable|array',
            'activities.*' => 'nullable|string|max:255',
            'food_drinks' => 'nullable|array',
            'food_drinks.*' => 'nullable|string|max:255',
            'sport' => 'nullable|array',
            'sport.*' => 'nullable|string|max:255',
            'games' => 'nullable|array',
            'games.*' => 'nullable|string|max:255',
            'music' => 'nullable|array',
            'music.*' => 'nullable|string|max:255',
            'films_books' => 'nullable|array',
            'films_books.*' => 'nullable|string|max:255',
        ];

        // Only validate fields that are present in the request
        $fieldsToValidate = array_intersect_key($rules, $request->all());
        
        $validator = \Validator::make($request->all(), $fieldsToValidate);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        \DB::beginTransaction();

        $user = $request->user();

        // Update user name if provided
        if($request->has('name')){
            $user->name = $request->name;
            $user->save();
        }

        // Create user-specific directory for images
        $userPath = "public/uploads/images/users/{$user->id}/";
        if (!file_exists(base_path($userPath))) {
            mkdir(base_path($userPath), 0755, true);
        }

        // Handle single profile image upload
        if ($request->hasFile('image')) {
            $profileImage = $request->file('image');
            $extension = $profileImage->getClientOriginalExtension();
            $profileFileName = 'profile_' . time() . '_' . uniqid() . '.' . $extension;
            
            // Move profile image to user directory
            $profileImage->move(base_path($userPath), $profileFileName);
            
            $profileImagePath = $userPath . $profileFileName;
            
            // Moderate image synchronously
            $moderationResult = moderate_image($profileImagePath, $user->id, 'profile');
            
            if ($moderationResult['decision'] === 'rejected') {
                // Delete rejected image
                @unlink(base_path($profileImagePath));
                
                \DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Profile image rejected: ' . $this->getModerationMessage($moderationResult['reason']),
                    'moderation' => [
                        'decision' => 'rejected',
                        'reason' => $moderationResult['reason'],
                        'confidence' => $moderationResult['confidence']
                    ]
                ], 200);
            }
            
            // Update user's profile image (store relative path)
            $user->image = $profileImagePath;
            $user->save();
        }

        // Handle multiple gallery images upload
        $otherImages = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $imgFile) {
                $extension = $imgFile->getClientOriginalExtension();
                $imgName = 'gallery_' . time() . '_' . uniqid() . '_' . $index . '.' . $extension;
                $imgFile->move(base_path($userPath), $imgName);
                
                $galleryImagePath = $userPath . $imgName;
                
                // Moderate image synchronously
                $moderationResult = moderate_image($galleryImagePath, $user->id, 'gallery');
                
                if ($moderationResult['decision'] === 'rejected') {
                    // Delete rejected image
                    @unlink(base_path($galleryImagePath));
                    
                    \DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => 'Gallery image rejected: ' . $this->getModerationMessage($moderationResult['reason']),
                        'moderation' => [
                            'decision' => 'rejected',
                            'reason' => $moderationResult['reason'],
                            'confidence' => $moderationResult['confidence']
                        ]
                    ], 200);
                }
                
                $otherImages[] = $galleryImagePath;
            }
        }
        

        if ($user->user_information) {
            $userInformation = $user->user_information;
        } else {
            $userInformation = new UserInformation();
            $userInformation->user_id = $user->id;
        }
        
        // Only update fields that are provided in the request
        $fillableFields = [
            'bio', 'gender', 'date_of_birth', 'religion_id', 'latitude', 'longitude', 
            'search_radius', 'country_code', 'phone', 'search_preference', 'relation_goals', 
            'interests', 'languages', 'is_zodiac_sign_matter', 'is_food_preference_matter', 
            'age', 'relationship_status_id', 'ethnicity_id', 'alkohol', 'smoke', 
            'education_id', 'preffered_age', 'height', 'carrer_field_id', 'address', 
            'activities', 'food_drinks', 'sport', 'games', 'music', 'films_books'
        ];

        foreach ($fillableFields as $field) {
            if ($request->has($field)) {
                // Validate bio content for contact information
                if ($field === 'bio' && $request->has('bio')) {
                    $validationResult = $this->validateContent($request->bio);
                    if (!$validationResult['valid']) {
                        \DB::rollBack();
                        return response()->json([
                            'status' => false,
                            'message' => $validationResult['message']
                        ], 200);
                    }
                }
                $userInformation->$field = $request->$field;
            }
        }

        // Handle search_radius default value
        if ($request->has('search_radius')) {
            $userInformation->search_radius = $request->search_radius;
        } elseif ($userInformation->search_radius === null) {
            $userInformation->search_radius = 1000;
        }

        // Handle gallery images if uploaded
        if (!empty($otherImages)) {
            $existingImages =  $userInformation->images != null ? $userInformation->images : [];
            $allImages = array_merge($existingImages, $otherImages);
            $userInformation->images = $allImages;
        }

        $userInformation->save();

        \DB::commit();
        
        // Update user's last activity when profile information is updated
        $user->last_activity = now();
        $user->save();
        
        $user = User::find($user->id);
        $user->is_profile_completed = $user->user_information ? true : false;
        $user->is_vip = (bool) $user->isVipActive();

        return response()->json(['status' => true, 'user' => $user, 'message' => _lang('Information has been added sucessfully.')]);
    }

    public function user(Request $request)
    {
        $data = $request->user();
        
        // Update last activity when user info is fetched
        $data->last_activity = now();
        $data->save();
        
        $data->is_profile_completed = $data->user_information ? true : false;
        $data->is_vip = (bool) $data->isVipActive();
        
        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }

    public function user_update(Request $request)
    {
        
        $validator = \Validator::make($request->all(), [

            'fields' => 'required:json',

        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();

        $not_allow_keys = ['phone', 'user_type', 'email'];

        foreach (json_decode($request->fields) as $key => $value) {

            if(in_array($key, $not_allow_keys)){
                return response()->json(['status' => false, 'message' => "The selected $key is invalid."]);
            }
            $user->$key = $value;
        }

        // Update last activity when user data is updated
        $user->last_activity = now();
        $user->save();
        
        $user->is_vip = (bool) $user->isVipActive();
        
        return response()->json([
            'status' => true,
            'user' => $user,
        ]);
    }

    public function upload_profile(Request $request)
    {   
        $validator = \Validator::make($request->all(), [

            'image' => 'required|image',

        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();

        $receiver = new FileReceiver("image", $request, HandlerFactory::classFromRequest($request));
        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }
        $save = $receiver->receive();
        if ($save->isFinished()) {

            $file = $save->getFile();

            $path = 'public/images/users/';
            $extension = $file->getClientOriginalExtension();
            $fileName =  rand() . time() . "." . $extension;

            $file->move($path, $fileName);

            $user->image = $path . $fileName;
            $user->last_activity = now(); // Update last activity on profile image upload
            $user->save();
            
            $user->is_vip = (bool) $user->isVipActive();

            return response()->json([
                'status' => true,
                'user' => $user,
            ]);
        }
        $handler = $save->handler();
    }

    public function upload_images(Request $request)
    {   
        $validator = \Validator::make($request->all(), [
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240', // Single profile image
            'images' => 'nullable|array|max:10', // Multiple additional images
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max per image
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();
        $uploadedImages = [];
        $profileImageUpdated = false;

        try {
            \DB::beginTransaction();

            // Create user-specific directory
            $userPath = "public/uploads/images/users/{$user->id}/";
            if (!file_exists(base_path($userPath))) {
                mkdir(base_path($userPath), 0755, true);
            }

            // Handle single profile image upload
            if ($request->hasFile('image')) {
                $profileImage = $request->file('image');
                $extension = $profileImage->getClientOriginalExtension();
                $profileFileName = 'profile_' . time() . '_' . uniqid() . '.' . $extension;
                
                // Move profile image to user directory
                $profileImage->move(base_path($userPath), $profileFileName);
                
                $profileImagePath = $userPath . $profileFileName;
                
                // Moderate image synchronously
                $moderationResult = moderate_image($profileImagePath, $user->id, 'profile');
                
                if ($moderationResult['decision'] === 'rejected') {
                    // Delete rejected image
                    @unlink(base_path($profileImagePath));
                    
                    \DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => 'Profile image rejected: ' . $this->getModerationMessage($moderationResult['reason']),
                        'moderation' => [
                            'decision' => 'rejected',
                            'reason' => $moderationResult['reason'],
                            'confidence' => $moderationResult['confidence']
                        ]
                    ], 200);
                }
                
                // Update user's profile image (store relative path)
                $user->image = $profileImagePath;
                $user->save();
                $profileImageUpdated = true;
            }

            // Handle multiple images upload
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $imageFile) {
                    $extension = $imageFile->getClientOriginalExtension();
                    $fileName = 'gallery_' . time() . '_' . uniqid() . '_' . $index . '.' . $extension;
                    
                    // Move file to user directory
                    $imageFile->move(base_path($userPath), $fileName);
                    
                    $galleryImagePath = $userPath . $fileName;
                    
                    // Moderate image synchronously
                    $moderationResult = moderate_image($galleryImagePath, $user->id, 'gallery');
                    
                    if ($moderationResult['decision'] === 'rejected') {
                        // Delete rejected image
                        @unlink(base_path($galleryImagePath));
                        
                        \DB::rollBack();
                        return response()->json([
                            'status' => false,
                            'message' => 'Gallery image rejected: ' . $this->getModerationMessage($moderationResult['reason']),
                            'moderation' => [
                                'decision' => 'rejected',
                                'reason' => $moderationResult['reason'],
                                'confidence' => $moderationResult['confidence']
                            ]
                        ], 400);
                    }
                    
                    $uploadedImages[] = $galleryImagePath;
                }

                // Update user information with new gallery images
                $userInfo = $user->user_information;
                if ($userInfo) {
                    $existingImages =  $userInfo->images != null ? $userInfo->images : [];
                    $allImages = array_merge($existingImages, $uploadedImages);
                    $userInfo->images = $allImages;
                    $userInfo->save();
                }
            }

        

            // Update user's last activity
            $user->last_activity = now();
            $user->save();

            \DB::commit();

            $user = User::find($user->id);
            $user_information = $user->user_information;
            $user->is_vip = (bool) $user->isVipActive();

            $response = [
                'status' => true,
                'message' => 'Images uploaded successfully',
                'user' => $user
            ];
            return response()->json($response);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Image upload failed: ' . $e->getMessage(),
            ]);
        }
    }
    

    public function change_password(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'old_password' => 'required',
            'password' => 'required|string|min:6|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();

        if (\Hash::check($request->old_password, $user->password)) {

            if (!\Hash::check($request->password, $user->password)) {

                $user->password = \Hash::make($request->password);
                $user->last_activity = now(); // Update last activity on password change
                $user->save();

                return response()->json([
                    'status' => true,
                    'message' => 'Password has been changed!',
                ]);
            } else {

                return response()->json([
                    'status' => false,
                    'message' => 'New Password can not be the old password!',
                ]);
            }
        } else {

            return response()->json([
                'status' => false,
                'message' => 'Old Password not match!',
            ]);
        }
    }

    public function forget_password(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Please enter valid email address.',
            ]);
        }

        $otp = rand(100000, 999999);
        // Store OTP in a new column or reuse email_otp if you want to verify before reset
        $user->email_otp = \Hash::make($otp);
        $user->save();

        \App\Utils\Overrider::load('Settings');
        \Mail::to($user->email)->send(new \App\Mail\EmailVerificationOtp($otp, $user->name));

        return response()->json([
            'status' => true,
            'message' => 'A password reset code has been sent to your email address.',
        ]);
    }

    /**
     * Verify OTP for password reset (forget password flow)
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify_forget_password(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'email' => 'required|string|email',
            'otp' => 'required|digits:6',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = \App\Models\User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found.']);
        }

        if (\Hash::check($request->otp, $user->email_otp)) {
            $user->email_otp = null;
            $user->save();

            $tokenResult = $user->createToken($user->device_token)->plainTextToken;
            return response()->json(['status' => true, 'access_token' => $tokenResult, 'message' => 'OTP verified. You can now reset your password.']);
        } else {
            return response()->json(['status' => false, 'message' => 'Invalid OTP.']);
        }
    }

    /**
     * Reset password for authenticated user (Bearer token required)
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset_password(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'password' => 'required|string|min:6|confirmed',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found.'], 401);
        }

        $user->password = \Hash::make($request->password);
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Password has been reset successfully.'
        ]);
    }

    /**
     * Delete gallery images for authenticated user
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete_gallery_images(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'filename' => 'required', // Can be string or array
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found.'], 401);
        }

        $user_information = $user->user_information;

        try {
            \DB::beginTransaction();

            // Get current images array
            $currentImages = $user_information->images ?? [];
            if (empty($currentImages)) {
                return response()->json(['status' => false, 'message' => 'No gallery images found.'], 404);
            }

            // Handle both single filename and array of filenames
            $filenamesToDelete = $request->filename;
            if (!is_array($filenamesToDelete)) {
                $filenamesToDelete = [$filenamesToDelete];
            }

            // Process each filename for deletion
            foreach ($filenamesToDelete as $filename) {
                $filename = trim($filename);
                if (empty($filename)) {
                    continue;
                }

                // Find the image path that contains this filename
                $imageToDelete = null;
                $imageIndex = null;
                
                foreach ($currentImages as $index => $imagePath) {
                    if (strpos($imagePath, $filename) !== false) {
                        $imageToDelete = $imagePath;
                        $imageIndex = $index;
                        break;
                    }
                }

                

                // Delete physical file if it exists
                $fullPath = base_path($imageToDelete);
                if (file_exists($fullPath)) {
                    if (unlink($fullPath)) {
                        // Remove from array
                        unset($currentImages[$imageIndex]);
                    } else {
                        // Could not delete file, log error
                    }
                } else {
                    // File doesn't exist physically, but remove from database anyway
                    unset($currentImages[$imageIndex]);
                }
            }

            // Re-index array after unsetting elements
            $currentImages = count($currentImages) ? array_values($currentImages) : null;

            // Update user information with remaining images
            $user_information->images = $currentImages;
            $user_information->save();

            // Update user's last activity
            $user->last_activity = now();
            $user->save();

            \DB::commit();

            // Prepare response
            $response = [
                'status' => true,
                'message' => 'Gallery images deletion completed.',
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete gallery images: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user's mood (set or remove based on mood_text parameter)
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update_mood(Request $request)
    {
        $user = $request->user();
        $userInfo = $user->user_information;
            
        // Validate mood text
        $validator = \Validator::make($request->all(), [
            'mood' => 'required|string|max:110',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $moodText = $request->mood;

        // Skip validation if removing mood
        if ($moodText != 'none') {
            // Validate mood doesn't contain contact information
            $validationResult = $this->validateContent($moodText);
            if (!$validationResult['valid']) {
                return response()->json([
                    'status' => false,
                    'message' => $validationResult['message']
                ], 200);
            }
        }

        // Set mood with 24 hour expiry - handle "none" as null
        $userInfo->mood_text = $moodText == 'none' ? null : $moodText;
        $userInfo->mood_expires_at = $moodText == 'none' ? null : now()->addHours(24);
        $userInfo->save();

        // Update user's last activity
        $user->last_activity = now();
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Mood updated successfully.',
            'data' => [
                'mood' => $userInfo->mood
            ]
        ]);
    }

    /**
     * Validate mood content doesn't contain contact information
     * @param string $mood
     * @return array
     */
    private function validateContent(string $mood): array
    {
        $lowerMood = strtolower($mood);
        
        // Check for phone number patterns
        $phonePatterns = [
            '/\d{10,}/',                           // 10+ consecutive digits
            '/\d{3}[-.\s]\d{3}[-.\s]\d{4}/',      // xxx-xxx-xxxx format
            '/\(\d{3}\)\s*\d{3}[-.\s]\d{4}/',     // (xxx) xxx-xxxx format
            '/\+\d{1,3}\s*\d{9,}/',               // International format
            '/\d{3}\s\d{3}\s\d{4}/',              // xxx xxx xxxx format
        ];

        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $mood)) {
                return [
                    'valid' => false,
                    'message' => 'Phone numbers are not allowed in mood status.'
                ];
            }
        }

        // Check for contact-related keywords
        $contactKeywords = [
            'whatsapp', 'whats app', 'whatsap', 'watsapp', 'watsap', 'wa number', 'wa me',
            'telegram', 'telegrm', 'tg number', 't.me',
            'snapchat', 'snap chat', 'snapchat me', 'snap me', 'snap:',
            'instagram', 'insta', 'ig:', 'dm me', 'dm on',
            'facebook', 'fb', 'messenger',
            'wechat', 'we chat', 'line app', 'line:',
            'viber', 'skype', 'kik',
            'call me', 'text me', 'phone me', 'ring me',
            'my number', 'my phone', 'reach me at',
            '@gmail', '@yahoo', '@hotmail', '@outlook', 'email me', 'e-mail',
            'contact me', 'add me',
        ];

        foreach ($contactKeywords as $keyword) {
            if (strpos($lowerMood, $keyword) !== false) {
                return [
                    'valid' => false,
                    'message' => 'Contact information and social media handles are not allowed in mood status.'
                ];
            }
        }

        // Check for @ mentions (social media handles)
        if (preg_match('/@[a-zA-Z0-9_]{3,}/', $mood)) {
            return [
                'valid' => false,
                'message' => 'Social media handles are not allowed in mood status.'
            ];
        }

        // Check for URLs
        if (preg_match('/https?:\/\/|www\.|\.com|\.net|\.org|\.io/i', $mood)) {
            return [
                'valid' => false,
                'message' => 'URLs and website links are not allowed in mood status.'
            ];
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * Get user-friendly moderation message
     * @param string $reason
     * @return string
     */
    private function getModerationMessage(string $reason): string
    {
        $messages = [
            'no_human_face' => 'Please upload a photo that clearly shows your face. Images without a visible human face are not allowed.',
            'not_real_person' => 'Please upload a real photograph of yourself. Drawings, cartoons, or non-human images are not allowed.',
            'document_or_screenshot' => 'Documents, receipts, screenshots, and text images are not allowed. Please upload a photo of yourself.',
            'nsfw_content' => 'The image contains inappropriate content and cannot be uploaded.',
            'public_figure_or_model' => 'The image appears to be of a celebrity, model, or public figure. Please upload your own photo.',
            'ai_generated_image' => 'The image appears to be AI-generated. Please upload a real photo of yourself.',
            'not_personal_photo' => 'The image does not appear to be a personal photo. Please upload a photo of yourself.',
            'watermark_or_text_detected' => 'The image contains watermarks or text overlays. Please upload a clean photo without any text or watermarks.',
            'duplicate_rejected_image' => 'This image was previously rejected and cannot be uploaded again.',
            'file_not_found' => 'The uploaded file could not be found.',
            'invalid_image_file' => 'The uploaded file is not a valid image.',
            'unsupported_image_type' => 'The image format is not supported. Please use JPEG, PNG, or WEBP.',
            'resolution_too_low' => 'The image resolution is too low. Please upload an image at least 300x300 pixels.',
            'openai_failure' => 'Image moderation temporarily unavailable. Please try again later.',
            'moderation_error' => 'An error occurred during image moderation. Please try again.'
        ];

        return $messages[$reason] ?? 'The image could not be approved. Please try a different photo.';
    }
}
