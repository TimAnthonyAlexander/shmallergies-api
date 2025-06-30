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

// Protected user routes
Route::get('/user', [UserController::class, 'profile'])->middleware('auth:sanctum');

Route::get('/user/product-safety/{id}', [UserController::class, 'checkProductSafety'])->where('id', '[0-9]+')->middleware('auth:sanctum');

// Product routes
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products/allergens', [ProductController::class, 'getByAllergens']);
Route::get('/products/{id}', [ProductController::class, 'show'])->where('id', '[0-9]+');
Route::get('/products/upc/{upcCode}', [ProductController::class, 'getByUpc']);
Route::post('/products', [ProductController::class, 'store'])->middleware('auth:sanctum');

// User allergy routes (protected)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/allergies', [UserAllergyController::class, 'index']);
    Route::post('/user/allergies', [UserAllergyController::class, 'store']);
    Route::put('/user/allergies/{id}', [UserAllergyController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/user/allergies/{id}', [UserAllergyController::class, 'destroy'])->where('id', '[0-9]+');
}); 