<?php

use App\Http\Controllers\PingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', [PingController::class, 'ping']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum'); 