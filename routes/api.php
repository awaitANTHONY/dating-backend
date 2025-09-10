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
    Route::post('settings', [Controllers\Api\v1\ApiController::class, 'settings']);
    Route::post('sliders', [Controllers\Api\v1\ApiController::class, 'sliders']);
    //Auth Controller
    Route::post('signup', [Controllers\Api\v1\AuthController::class, 'signup']);
    Route::post('signin', [Controllers\Api\v1\AuthController::class, 'signin']);
    Route::post('signinWithPhone', [Controllers\Api\v1\AuthController::class, 'signinWithPhone']);

    //SubscriptionController
    Route::post('subscriptions', [Controllers\Api\v1\SubscriptionController::class, 'subscriptions']);

    //Auth Controller
    Route::middleware('auth:sanctum')->group( function () {
        Route::post('user', [Controllers\Api\v1\AuthController::class, 'user']);
        Route::post('user_update', [Controllers\Api\v1\AuthController::class, 'user_update']);
        Route::post('upload_profile', [Controllers\Api\v1\AuthController::class, 'upload_profile']);
        Route::post('change_password', [Controllers\Api\v1\AuthController::class, 'change_password']);
        
        Route::post('favorite', [Controllers\Api\v1\ApiController::class, 'favorite']);
        Route::post('favorites', [Controllers\Api\v1\ApiController::class, 'favorites']);

        //SubscriptionController
        Route::post('subscription_update', [Controllers\Api\v1\SubscriptionController::class, 'subscription_update']);
        Route::post('subscription_expired', [Controllers\Api\v1\SubscriptionController::class, 'subscription_expired']);
        Route::post('subscription_restore', [Controllers\Api\v1\SubscriptionController::class, 'subscription_restore']);
        Route::post('payments', [Controllers\Api\v1\SubscriptionController::class, 'payments']);
    });
});



