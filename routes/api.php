<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\UserBalanceController;
use App\Http\Controllers\Api\SalesOrderController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });

    // Business management routes
    Route::prefix('businesses')->group(function () {
        Route::get('/', [BusinessController::class, 'index']);
        Route::post('/', [BusinessController::class, 'store']);
        Route::get('/{id}', [BusinessController::class, 'show']);
        Route::put('/{id}', [BusinessController::class, 'update']);
        Route::delete('/{id}', [BusinessController::class, 'destroy']);
    });

    // User management routes
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/type/{type}', [UserController::class, 'getByType']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
    });

    // Categories management
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('/{id}', [CategoryController::class, 'show']);
        Route::put('/{id}', [CategoryController::class, 'update']);
        Route::delete('/{id}', [CategoryController::class, 'destroy']);
    });
    
    // Brands management
    Route::prefix('brands')->group(function () {
        Route::get('/', [BrandController::class, 'index']);
        Route::post('/', [BrandController::class, 'store']);
        Route::get('/{id}', [BrandController::class, 'show']);
        Route::put('/{id}', [BrandController::class, 'update']);
        Route::delete('/{id}', [BrandController::class, 'destroy']);
    });
    
    // Units management
    Route::prefix('units')->group(function () {
        Route::get('/', [UnitController::class, 'index']);
        Route::post('/', [UnitController::class, 'store']);
        Route::get('/{id}', [UnitController::class, 'show']);
        Route::put('/{id}', [UnitController::class, 'update']);
        Route::delete('/{id}', [UnitController::class, 'destroy']);
    });
    
    // Products management
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/low-stock', [ProductController::class, 'lowStock']);
        Route::get('/{id}', [ProductController::class, 'show']);
        Route::put('/{id}', [ProductController::class, 'update']);
        Route::delete('/{id}', [ProductController::class, 'destroy']);
    });

    // Payment Methods management
    Route::prefix('payment-methods')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index']);
        Route::post('/', [PaymentMethodController::class, 'store']);
        Route::get('/{id}', [PaymentMethodController::class, 'show']);
        Route::put('/{id}', [PaymentMethodController::class, 'update']);
        Route::delete('/{id}', [PaymentMethodController::class, 'destroy']);
    });

    // Purchase Order management
    Route::prefix('purchase-orders')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index']);
        Route::post('/', [PurchaseOrderController::class, 'store']);
        Route::get('/{id}', [PurchaseOrderController::class, 'show']);
        Route::put('/{id}', [PurchaseOrderController::class, 'update']);
        Route::put('/{id}/items', [PurchaseOrderController::class, 'updateItems']);
                
        // Shipment management
        Route::post('/{id}/shipments', [PurchaseOrderController::class, 'receiveShipment']);
        Route::get('/{id}/shipments', [PurchaseOrderController::class, 'getShipments']);
        
        // Payment management
        Route::post('/{id}/payments', [PurchaseOrderController::class, 'addPayment']);
        Route::put('/{id}/payments/{paymentId}/status', [PurchaseOrderController::class, 'updatePaymentStatus']);
        
        Route::put('/{id}/cancel', [PurchaseOrderController::class, 'cancel']);
    });

    // User Balance management
    Route::prefix('user-balances')->group(function () {
        Route::get('/summary', [UserBalanceController::class, 'getBusinessBalanceSummary']);
        Route::get('/{userId}', [UserBalanceController::class, 'getUserBalance']);
        Route::get('/{userId}/history', [UserBalanceController::class, 'getUserBalanceHistory']);
        Route::post('/{userId}/adjustment', [UserBalanceController::class, 'addBalanceAdjustment']);
        Route::get('/{userId}/payments', [UserBalanceController::class, 'getUserPaymentHistory']);
    });

    // Payment management
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::get('/purchase-orders/{id?}', [PaymentController::class, 'getPurchaseOrderPayments']);
        Route::get('/summary', [PaymentController::class, 'getSummary']);
        Route::get('/pending', [PaymentController::class, 'getPendingPayments']);
        Route::put('/{id}/status', [PaymentController::class, 'updateStatus']);
        Route::put('/bulk-status', [PaymentController::class, 'bulkUpdateStatus']);
    });

    // Sales Order management
    Route::prefix('sales-orders')->group(function () {
        Route::get('/', [SalesOrderController::class, 'index']);
        Route::post('/', [SalesOrderController::class, 'store']);
        Route::get('/{id}', [SalesOrderController::class, 'show']);
        Route::put('/{id}', [SalesOrderController::class, 'update']);
        Route::put('/{id}/items', [SalesOrderController::class, 'updateItems']);
        Route::put('/{id}/confirm', [SalesOrderController::class, 'confirm']);
        
        // Shipment management
        Route::post('/{id}/ship', [SalesOrderController::class, 'ship']);
        Route::get('/{id}/shipments', [SalesOrderController::class, 'getShipments']);
        
        // Payment management
        Route::post('/{id}/payments', [SalesOrderController::class, 'addPayment']);
        Route::put('/{id}/payments/{paymentId}/status', [SalesOrderController::class, 'updatePaymentStatus']);
        
        Route::put('/{id}/cancel', [SalesOrderController::class, 'cancel']);
    });
});
