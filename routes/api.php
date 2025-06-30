<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserAllergyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/ping', [PingController::class, 'ping']);

// Authentication routes
Route::post('/auth/signup', [AuthController::class, 'signup']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Protected user routes
Route::get('/user', function (Request $request) {
    return response()->json([
        'message' => 'User profile retrieved successfully',
        'user' => $request->user()->load('allergies'),
    ]);
})->middleware('auth:sanctum');

Route::get('/user/product-safety/{id}', function (Request $request, int $id) {
    $user = $request->user()->load('allergies');
    $product = \App\Models\Product::with(['ingredients.allergens'])->find($id);
    
    if (!$product) {
        return response()->json(['message' => 'Product not found'], 404);
    }
    
    $userAllergens = $user->allergies->pluck('allergy_text')->map(function ($allergy) {
        return strtolower(trim($allergy));
    });
    
    $productAllergens = $product->ingredients
        ->flatMap(function ($ingredient) {
            return $ingredient->allergens->pluck('name');
        })
        ->map(function ($allergen) {
            return strtolower(trim($allergen));
        });
    
    $conflicts = $userAllergens->filter(function ($userAllergen) use ($productAllergens) {
        return $productAllergens->some(function ($productAllergen) use ($userAllergen) {
            return str_contains($productAllergen, $userAllergen) || str_contains($userAllergen, $productAllergen);
        });
    });
    
    return response()->json([
        'message' => 'Product safety check completed',
        'product' => [
            'id' => $product->id,
            'name' => $product->name,
            'upc_code' => $product->upc_code,
        ],
        'is_safe' => $conflicts->isEmpty(),
        'potential_conflicts' => $conflicts->values(),
        'product_allergens' => $productAllergens->unique()->values(),
    ]);
})->where('id', '[0-9]+')->middleware('auth:sanctum');

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