<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

// The same origin Angular proxy uses Laravel sessions and CSRF protection.
Route::middleware('web')->group(function () {
    Route::get('/auth/csrf', fn () => response()->noContent());
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

    Route::middleware('auth')->group(function () {
        // Logout stays available even after shop access is disabled.
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::middleware('shop.access')->group(function () {
            Route::get('/auth/me', [AuthController::class, 'me']);
            Route::get('/shop', [ShopController::class, 'show']);
            Route::patch('/shop', [ShopController::class, 'update'])->middleware('shop.permission:shop.update');
            Route::middleware('shop.permission:staff.manage')->group(function () {
                Route::get('/staff', [StaffController::class, 'index']);
                Route::post('/staff', [StaffController::class, 'store'])->middleware('throttle:20,1');
                Route::patch('/staff/{staff}', [StaffController::class, 'update'])->whereNumber('staff');
            });
        });
    });
});
