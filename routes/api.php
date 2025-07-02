<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserAllergyController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/ping', [PingController::class, 'ping']);

// API v1 routes
Route::prefix('v1')->group(function () {
    // Authentication routes with rate limiting
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/auth/signup', [AuthController::class, 'signup']);
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/email/resend', [AuthController::class, 'resendVerificationEmail']);
    });

    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // Email verification routes
    Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Protected user routes
    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('/user', [UserController::class, 'profile']);
        Route::get('/user/product-safety/{id}', [UserController::class, 'checkProductSafety'])->where('id', '[0-9]+');
    });

    // Product routes with rate limiting
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/search', [ProductController::class, 'search']);
        Route::get('/products/allergens', [ProductController::class, 'getByAllergens']);
        Route::get('/products/{id}', [ProductController::class, 'show'])->where('id', '[0-9]+');
        Route::get('/products/upc/{upcCode}', [ProductController::class, 'getByUpc']);
    });

    Route::post('/products', [ProductController::class, 'store'])->middleware(['auth:sanctum', 'verified', 'throttle:10,1']);

    // User allergy routes (protected)
    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('/user/allergies', [UserAllergyController::class, 'index']);
        Route::post('/user/allergies', [UserAllergyController::class, 'store']);
        Route::put('/user/allergies/{id}', [UserAllergyController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/user/allergies/{id}', [UserAllergyController::class, 'destroy'])->where('id', '[0-9]+');
    });
});

// Legacy routes (for backward compatibility) - consider deprecating
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/auth/signup', [AuthController::class, 'signup']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/email/resend', [AuthController::class, 'resendVerificationEmail']);
});

Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/user', [UserController::class, 'profile']);
    Route::get('/user/product-safety/{id}', [UserController::class, 'checkProductSafety'])->where('id', '[0-9]+');
});

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/allergens', [ProductController::class, 'getByAllergens']);
    Route::get('/products/{id}', [ProductController::class, 'show'])->where('id', '[0-9]+');
    Route::get('/products/upc/{upcCode}', [ProductController::class, 'getByUpc']);
});

Route::post('/products', [ProductController::class, 'store'])->middleware(['auth:sanctum', 'verified', 'throttle:10,1']);

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/user/allergies', [UserAllergyController::class, 'index']);
    Route::post('/user/allergies', [UserAllergyController::class, 'store']);
    Route::put('/user/allergies/{id}', [UserAllergyController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/user/allergies/{id}', [UserAllergyController::class, 'destroy'])->where('id', '[0-9]+');
});
