<?php

use Illuminate\Support\Facades\Route;

Route::apiResource('products', App\Http\Controllers\ProductController::class);
Route::post('sales', [App\Http\Controllers\SaleController::class, 'store']);
