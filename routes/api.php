<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => ['x_check'], 'prefix' => 'v1'], function ()
{
    //Api Controller
    Route::get('settings', [Controllers\Api\v1\ApiController::class, 'settings']);
    Route::any('sliders', [Controllers\Api\v1\ApiController::class, 'sliders']);
    Route::any('interests', [Controllers\Api\v1\ApiController::class, 'interests']);
    Route::any('relation_goals', [Controllers\Api\v1\ApiController::class, 'relation_goals']);
    Route::any('religions', [Controllers\Api\v1\ApiController::class, 'religions']);
    Route::any('languages', [Controllers\Api\v1\ApiController::class, 'languages']);
    Route::any('relationship_statuses', [Controllers\Api\v1\ApiController::class, 'relationship_statuses']);
    Route::any('ethnicities', [Controllers\Api\v1\ApiController::class, 'ethnicities']);
    Route::any('educations', [Controllers\Api\v1\ApiController::class, 'educations']);
    Route::any('career_fields', [Controllers\Api\v1\ApiController::class, 'career_fields']);
    Route::any('onboarding', [Controllers\Api\v1\ApiController::class, 'onboarding']);
    
    //Auth Controller
    Route::post('signup', [Controllers\Api\v1\AuthController::class, 'signup']);
    Route::post('signin', [Controllers\Api\v1\AuthController::class, 'signin']);
    Route::post('signinWithPhone', [Controllers\Api\v1\AuthController::class, 'signinWithPhone']);
    Route::post('forget_password', [Controllers\Api\v1\AuthController::class, 'forget_password']);
    Route::post('verify_forget_password', [Controllers\Api\v1\AuthController::class, 'verify_forget_password']);
    //SubscriptionController
    Route::get('subscriptions', [Controllers\Api\v1\SubscriptionController::class, 'subscriptions']);



    //Auth Controller
    Route::middleware(['auth:sanctum'])->group( function () {
        
        Route::post('verification', [Controllers\Api\v1\AuthController::class, 'verification']);
        Route::post('resend_otp', [Controllers\Api\v1\AuthController::class, 'resend_otp']);
        Route::post('user_information', [Controllers\Api\v1\AuthController::class, 'user_information']);
        Route::get('user', [Controllers\Api\v1\AuthController::class, 'user']);
        Route::post('user_update', [Controllers\Api\v1\AuthController::class, 'user_update']);
        Route::post('upload_profile', [Controllers\Api\v1\AuthController::class, 'upload_profile']);
        
        // Image Upload Routes
        Route::post('upload_images', [Controllers\Api\v1\AuthController::class, 'upload_images']);
        Route::delete('delete_gallery_images', [Controllers\Api\v1\AuthController::class, 'delete_gallery_images']);
        
        
        Route::post('change_password', [Controllers\Api\v1\AuthController::class, 'change_password']);
        Route::post('reset_password', [Controllers\Api\v1\AuthController::class, 'reset_password']);

        // Mood Route
        Route::post('update_mood', [Controllers\Api\v1\AuthController::class, 'update_mood']);

        Route::get('profiles/recommendations', [Controllers\Api\v1\ProfileController::class, 'recommendations']);
        Route::get('profiles/completion', [Controllers\Api\v1\ProfileController::class, 'profile_completion']);
        Route::get('profiles/compatibility', [Controllers\Api\v1\ProfileController::class, 'profile_compatibility']);
        Route::get('profiles/details/{id}', [Controllers\Api\v1\ProfileController::class, 'details']);
        Route::get('profiles/soulmates', [Controllers\Api\v1\ProfileController::class, 'soulmates']);
        Route::get('profiles/visitors', [Controllers\Api\v1\ProfileController::class, 'profile_visitors']);
        
        // User Interactions
        Route::post('interactions', [Controllers\Api\v1\UserInteractionController::class, 'store']);
        Route::get('interactions', [Controllers\Api\v1\UserInteractionController::class, 'index']);
        Route::any('interactions/likes', [Controllers\Api\v1\UserInteractionController::class, 'getLikes']);
        
        // Matches
        Route::any('matches', [Controllers\Api\v1\UserInteractionController::class, 'getMatches']);

        // User Blocking System
        Route::post('blocks', [Controllers\Api\v1\UserBlockController::class, 'toggleBlock']);
        Route::get('blocks', [Controllers\Api\v1\UserBlockController::class, 'getBlockedUsers']);
       
        
        Route::post('favorite', [Controllers\Api\v1\ApiController::class, 'favorite']);
        Route::post('favorites', [Controllers\Api\v1\ApiController::class, 'favorites']);

        //SubscriptionController
        Route::post('subscription/expired', [Controllers\Api\v1\SubscriptionController::class, 'subscription_expired']);

        //PaymentController
        Route::post('payments/subscribe', [Controllers\Api\v1\PaymentController::class, 'subscribe']);
        Route::post('payments/verification', [Controllers\Api\v1\PaymentController::class, 'purchase_verification']);
        Route::post('payments/restore', [Controllers\Api\v1\PaymentController::class, 'subscription_restore']);
        Route::get('payments/history', [Controllers\Api\v1\PaymentController::class, 'payments']);
        Route::post('payments/boost', [Controllers\Api\v1\PaymentController::class, 'purchase_boost']);
        Route::post('payments/vip', [Controllers\Api\v1\PaymentController::class, 'purchase_vip']);
        
        // BoostController - Profile Boost Management
        Route::get('boosts/packages', [Controllers\Api\v1\BoostController::class, 'boost_packages']);
        Route::post('boosts/activate', [Controllers\Api\v1\BoostController::class, 'activate_boost']);
        Route::get('boosts/status', [Controllers\Api\v1\BoostController::class, 'boost_status']);


        // --- Chat API (Firebase Realtime) ---
        Route::any('/start-chat', [\App\Http\Controllers\Api\v1\ChatController::class, 'startChat']);
        // Send a message to a user (creates group if needed)
        Route::post('/send-message', [\App\Http\Controllers\Api\v1\ChatController::class, 'sendMessage']);
        // List all chat groups for the current user
        Route::get('/chat-list', [\App\Http\Controllers\Api\v1\ChatController::class, 'chatList']);
        // Get all messages for a grou
        Route::get('/messages/{group_id}', [\App\Http\Controllers\Api\v1\ChatController::class, 'messages']);

        Route::post('notification', [Controllers\Api\v1\ApiController::class, 'notification']);
    });
});



