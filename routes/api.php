<?php

use App\Http\Controllers\Api\MerchantController;
use App\Http\Middleware\AuthenticateTenant;
use Illuminate\Support\Facades\Route;

Route::post('/merchants', [MerchantController::class, 'register'])->middleware('throttle:10,1');

Route::middleware(AuthenticateTenant::class)->group(function () {
    Route::get('/plans', [MerchantController::class, 'plans']);
    Route::post('/plans', [MerchantController::class, 'storePlan']);
    Route::put('/plans/{plan}', [MerchantController::class, 'updatePlan']);
    Route::post('/customers', [MerchantController::class, 'storeCustomer']);
    Route::post('/customers/{customer}/subscriptions', [MerchantController::class, 'subscribe']);
    Route::patch('/customers/{customer}/subscriptions/plan', [MerchantController::class, 'changePlan']);
    Route::post('/usage', [MerchantController::class, 'storeUsage'])->middleware('throttle:usage');
    Route::get('/merchants/{merchant}/dashboard', [MerchantController::class, 'dashboard']);

});
