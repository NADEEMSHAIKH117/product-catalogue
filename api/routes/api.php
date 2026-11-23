<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\EnquiryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// Mounted at /api/v1 (see bootstrap/app.php).

Route::middleware('throttle:api')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/featured', [ProductController::class, 'featured']);
    Route::get('/products/{id}', [ProductController::class, 'show'])->where('id', '[0-9]{1,9}');

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}/products', [CategoryController::class, 'products'])
        ->where('slug', '[a-z0-9-]+');
});

Route::post('/enquiries', [EnquiryController::class, 'store'])->middleware('throttle:enquiries');
