<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



Route::group(['middleware' => ['install']], function () {

    try {
        date_default_timezone_set(get_option('timezone') ?? 'Asia/Dhaka');
    } catch (Exception $e) {
        //
    }

    Auth::routes(['register' => false]);
    Route::get('logout', [Controllers\Auth\LoginController::class, 'logout'])->name('logout');
    Route::get('/', function () {
        return redirect('dashboard');
    });

    //auth
    Route::group(['middleware' => ['auth']], function () {
        Route::get('/dashboard', [Controllers\DashboardController::class, 'index'])->name('dashboard');
        //Profile Controller
        Route::get('profile/show', [Controllers\ProfileController::class, 'show'])->name('profile.show');
        Route::get('profile/edit', [Controllers\ProfileController::class,'edit'])->name('profile.edit');
        Route::post('profile/update', [Controllers\ProfileController::class,'update'])->name('profile.update');
        Route::get('password/change', [Controllers\ProfileController::class,'password_change'])->name('password.change');
        Route::post('password/update', [Controllers\ProfileController::class,'update_password'])->name('password.update');

        //Settings Controller
        Route::any('general_settings', [Controllers\SettingController::class, 'general'])->name('general_settings');
        Route::any('app_settings', [Controllers\SettingController::class, 'app'])->name('app_settings');
        Route::post('store_settings', [Controllers\SettingController::class, 'store_settings'])->name('store_settings');

        //Backup Controller
        Route::any('database_backup', [Controllers\BackupController::class, 'index'])->name('database_backup');


        Route::get('notifications/deleteall', [Controllers\NotificationController::class, 'deleteall']);
        Route::resource('notifications', Controllers\NotificationController::class);

        //SystemUserController
        Route::resource('system_users', Controllers\SystemUserController::class);
        //UserController
        Route::resource('users', Controllers\UserController::class);
        // Coin management routes
        Route::get('users/{id}/coin-manage', [App\Http\Controllers\UserController::class, 'coin_manage'])->name('users.coin_manage');
        Route::post('users/{id}/update-coin', [App\Http\Controllers\UserController::class, 'update_coin'])->name('users.update_coin');

        // Wallet management routes
        Route::get('users/{id}/wallet-manage', [App\Http\Controllers\UserController::class, 'wallet_manage'])->name('users.wallet_manage');
        Route::post('users/{id}/update-wallet', [App\Http\Controllers\UserController::class, 'update_wallet'])->name('users.update_wallet');

        //Channel Controller
        Route::resource('sliders', Controllers\SliderController::class);
        Route::resource('relationship-statuses', Controllers\RelationshipStatusController::class);
        Route::resource('ethnicities', Controllers\EthnicityController::class);
        Route::resource('educations', Controllers\EducationController::class);
        Route::resource('career-fields', Controllers\CareerFieldController::class);
        Route::resource('interests', Controllers\InterestController::class);
        Route::resource('languages', Controllers\LanguageController::class);
        Route::resource('religions', Controllers\ReligionController::class);
        Route::resource('relation_goals', Controllers\RelationGoalController::class);
        Route::resource('gifts', Controllers\GiftController::class);
        Route::resource('faqs', Controllers\FaqController::class);
        
        // SubscriptionController
        Route::post('/subscriptions/reorder', [Controllers\SubscriptionController::class, 'reorder']);
        Route::resource('subscriptions', Controllers\SubscriptionController::class);

        // BoostPackageController
        Route::post('/boost-packages/reorder', [Controllers\BoostPackageController::class, 'reorder']);
        Route::resource('boost-packages', Controllers\BoostPackageController::class);

        // PaymentController
        Route::resource('payments', Controllers\PaymentController::class);

        // Package CRUD
        Route::resource('packages', Controllers\PackageController::class);
    // Fake User Generator
    Route::get('fake-user-generator', [App\Http\Controllers\FakeUserGeneratorController::class, 'index'])->name('fake-user-generator.index');
    Route::post('fake-user-generator/generate', [App\Http\Controllers\FakeUserGeneratorController::class, 'generate'])->name('fake-user-generator.generate');
        
    });
    
    Route::get('/privacy_policy', [Controllers\HomeController::class, 'privacy_policy'])->name('privacy_policy');
    Route::get('/terms_conditions', [Controllers\HomeController::class, 'terms_conditions'])->name('terms_conditions');
    
});

//Install Controller
Route::get('installation', [Controllers\InstallController::class, 'index']);
Route::any('installation/step/one', [Controllers\InstallController::class, 'database']);
Route::any('installation/step/two', [Controllers\InstallController::class, 'user']);
Route::any('installation/step/three', [Controllers\InstallController::class, 'settings']);

Route::any('cronjob/prediction_notification', [Controllers\CronJobController::class, 'prediction_notification']);

Route::get('/cache', function(){

    cache()->flush();
    cleanOthers();
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('view:clear');
    return redirect('dashboard')->with('success', _lang('Cache successfully clear.'));
});

