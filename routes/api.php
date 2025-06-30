<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserAllergyController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/ping', [PingController::class, 'ping']);

// Authentication routes
Route::post('/auth/signup', [AuthController::class, 'signup']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Email verification routes
Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');
Route::post('/auth/email/resend', [AuthController::class, 'resendVerificationEmail']);

// Debug route (remove in production)
Route::get('/debug/url-config', function () {
    return response()->json([
        'app_url'                 => config('app.url'),
        'app_name'                => config('app.name'),
        'sample_verification_url' => route('verification.verify', ['id' => 1, 'hash' => 'sample-hash']),
        'current_domain'          => request()->getHost(),
        'current_scheme'          => request()->getScheme(),
        'full_current_url'        => request()->getSchemeAndHttpHost(),
    ]);
});

// Test email verification (remove in production)
Route::get('/debug/send-verification/{userId}', function ($userId) {
    $user = \App\Models\User::find($userId);
    if (! $user) {
        return response()->json(['error' => 'User not found'], 404);
    }

    $user->sendEmailVerificationNotification();

    return response()->json([
        'message' => 'Verification email sent',
        'user_id' => $user->id,
        'email'   => $user->email,
    ]);
});

// Protected user routes
Route::get('/user', [UserController::class, 'profile'])->middleware(['auth:sanctum', 'verified']);

Route::get('/user/product-safety/{id}', [UserController::class, 'checkProductSafety'])->where('id', '[0-9]+')->middleware(['auth:sanctum', 'verified']);

// Product routes
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products/allergens', [ProductController::class, 'getByAllergens']);
Route::get('/products/{id}', [ProductController::class, 'show'])->where('id', '[0-9]+');
Route::get('/products/upc/{upcCode}', [ProductController::class, 'getByUpc']);
Route::post('/products', [ProductController::class, 'store'])->middleware(['auth:sanctum', 'verified']);

// User allergy routes (protected)
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/user/allergies', [UserAllergyController::class, 'index']);
    Route::post('/user/allergies', [UserAllergyController::class, 'store']);
    Route::put('/user/allergies/{id}', [UserAllergyController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/user/allergies/{id}', [UserAllergyController::class, 'destroy'])->where('id', '[0-9]+');
});
