<?php

use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Courier\CourierDeliveryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Seller\SellerOrderController;
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

// Product & Order Routes (Protected - Seller)
Route::middleware(['auth:sanctum', 'role:seller'])->group(function () {
    Route::get('seller/products', [ProductController::class, 'sellerIndex']);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{product:slug}', [ProductController::class, 'update']);
    Route::delete('products/{product:slug}', [ProductController::class, 'destroy']);

    Route::get('seller/orders', [SellerOrderController::class, 'index']);
    Route::get('seller/incoming-items', [SellerOrderController::class, 'incomingItems']);
    Route::patch('seller/order-items/{orderItem}/status', [SellerOrderController::class, 'updateItemStatus']);
});

// Cart Routes (Protected - Buyer & Admin)
Route::middleware(['auth:sanctum', 'role:buyer,admin'])->group(function () {
    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart', [CartController::class, 'store']);
    Route::delete('cart', [CartController::class, 'clear']);
    Route::put('cart/{cart}', [CartController::class, 'update']);
    Route::delete('cart/{cart}', [CartController::class, 'destroy']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders/checkout', [OrderController::class, 'checkout']);
    Route::post('orders/{order:order_number}/cancel', [OrderController::class, 'cancel']);
    Route::post('orders/{order:order_number}/payment', [PaymentController::class, 'submit']);
    Route::get('orders/{order:order_number}/payment', [PaymentController::class, 'show']);
});

// Admin Payment Routes (Protected - Admin only)
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('admin/payments', [AdminPaymentController::class, 'index']);
    Route::post('admin/payments/{payment}/verify', [AdminPaymentController::class, 'verify']);
});

// Order Detail Route (Protected - Policy based access)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('orders/{order:order_number}', [OrderController::class, 'show']);
});

// Courier Delivery Routes (Protected - Courier & Admin)
Route::middleware(['auth:sanctum', 'role:courier,admin'])->group(function () {
    Route::get('courier/deliveries', [CourierDeliveryController::class, 'index']);
    Route::get('courier/deliveries/{order:order_number}', [CourierDeliveryController::class, 'show']);
    Route::post('courier/deliveries/{order:order_number}/claim', [CourierDeliveryController::class, 'claim']);
    Route::patch('courier/order-items/{orderItem}/pickup', [CourierDeliveryController::class, 'updateItemPickup']);
    Route::patch('courier/deliveries/{order:order_number}/status', [CourierDeliveryController::class, 'updateDeliveryStatus']);
});

// Profile Routes (Protected - All Authenticated Roles)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('profile/password', [ProfileController::class, 'updatePassword']);
});

Route::get('/user', function (Request $request) {
    return new UserResource($request->user()->load('profile'));
})->middleware('auth:sanctum');
