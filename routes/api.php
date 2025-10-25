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
        
        
        Route::post('change_password', [Controllers\Api\v1\AuthController::class, 'change_password']);
        Route::post('reset_password', [Controllers\Api\v1\AuthController::class, 'reset_password']);

        Route::get('profiles/recommendations', [Controllers\Api\v1\ProfileController::class, 'recommendations']);
        Route::get('profiles/compatibility', [Controllers\Api\v1\ProfileController::class, 'profile_compatibility']);
        Route::get('profiles/details/{id}', [Controllers\Api\v1\ProfileController::class, 'details']);
        
        // User Interactions
        Route::post('interactions', [Controllers\Api\v1\UserInteractionController::class, 'store']);
        Route::get('interactions', [Controllers\Api\v1\UserInteractionController::class, 'index']);
        Route::any('interactions/likes', [Controllers\Api\v1\UserInteractionController::class, 'getLikes']);
        
        // Matches
        Route::any('matches', [Controllers\Api\v1\UserInteractionController::class, 'getMatches']);
        Route::get('matches/stats', [Controllers\Api\v1\UserInteractionController::class, 'getMatchStats']);
        Route::get('matches/check/{target_user_id}', [Controllers\Api\v1\UserInteractionController::class, 'checkMatch']);
        Route::delete('matches/{target_user_id}', [Controllers\Api\v1\UserInteractionController::class, 'unmatch']);
        
        // User Blocking System
        Route::post('blocks', [Controllers\Api\v1\UserBlockController::class, 'toggleBlock']);
        Route::get('blocks', [Controllers\Api\v1\UserBlockController::class, 'getBlockedUsers']);
       
        
        Route::post('favorite', [Controllers\Api\v1\ApiController::class, 'favorite']);
        Route::post('favorites', [Controllers\Api\v1\ApiController::class, 'favorites']);

        //SubscriptionController
        Route::post('subscription/subscribe', [Controllers\Api\v1\SubscriptionController::class, 'subscribe']);
        Route::post('subscription/expired', [Controllers\Api\v1\SubscriptionController::class, 'subscription_expired']);
        Route::post('subscription/restore', [Controllers\Api\v1\SubscriptionController::class, 'subscription_restore']);
        Route::get('subscription/history', [Controllers\Api\v1\SubscriptionController::class, 'payments']);


        // --- Chat API (Firebase Realtime) ---
        Route::any('/start-chat', [\App\Http\Controllers\Api\v1\ChatController::class, 'startChat']);
        // Send a message to a user (creates group if needed)
        Route::post('/send-message', [\App\Http\Controllers\Api\v1\ChatController::class, 'sendMessage']);
        // List all chat groups for the current user
        Route::get('/chat-list', [\App\Http\Controllers\Api\v1\ChatController::class, 'chatList']);
        // Get all messages for a grou
        Route::get('/messages/{group_id}', [\App\Http\Controllers\Api\v1\ChatController::class, 'messages']);
    });
});



