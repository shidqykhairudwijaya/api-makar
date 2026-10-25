<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth Routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Category Routes (Public)
Route::get('categories', [CategoryController::class, 'index']);

// Product Routes (Public)
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product:slug}', [ProductController::class, 'show']);

// Product Routes (Protected - Seller)
Route::middleware(['auth:sanctum', 'role:seller'])->group(function () {
    Route::get('seller/products', [ProductController::class, 'sellerIndex']);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{product:slug}', [ProductController::class, 'update']);
    Route::delete('products/{product:slug}', [ProductController::class, 'destroy']);
});

// Cart Routes (Protected - Buyer & Admin)
Route::middleware(['auth:sanctum', 'role:buyer,admin'])->group(function () {
    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart', [CartController::class, 'store']);
    Route::delete('cart', [CartController::class, 'clear']);
    Route::put('cart/{cart}', [CartController::class, 'update']);
    Route::delete('cart/{cart}', [CartController::class, 'destroy']);
});

Route::get('/user', function (Request $request) {
    return new UserResource($request->user()->load('profile'));
})->middleware('auth:sanctum');
