<?php

use Illuminate\Support\Facades\Route;

Route::post('register', [App\Http\Controllers\AuthController::class, 'register']);
Route::post('login', [App\Http\Controllers\AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [App\Http\Controllers\AuthController::class, 'logout']);
    Route::get('me', [App\Http\Controllers\AuthController::class, 'me']);

    Route::apiResource('products', App\Http\Controllers\ProductController::class);
    Route::post('sales', [App\Http\Controllers\SaleController::class, 'store']);
});
