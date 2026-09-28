<?php

use App\Http\Controllers\MerchantPortalController;
use App\Http\Middleware\AuthenticateMerchantWeb;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/login', [MerchantPortalController::class, 'loginPage'])->name('login');
Route::post('/login', [MerchantPortalController::class, 'login'])->middleware('throttle:10,1');
Route::get('/register', [MerchantPortalController::class, 'registerPage'])->name('register');
Route::post('/register', [MerchantPortalController::class, 'register'])->middleware('throttle:5,1');

Route::middleware(AuthenticateMerchantWeb::class)->group(function () {
    Route::get('/dashboard', [MerchantPortalController::class, 'dashboard'])->name('dashboard');
    Route::post('/logout', [MerchantPortalController::class, 'logout'])->name('logout');
});
