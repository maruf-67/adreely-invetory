<?php

use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\PurchaseOrderController;
use App\Http\Controllers\API\SupplierController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // UserController group for admin, super-admin, and staff
    Route::middleware(['role:admin|super-admin|staff'])->controller(UserController::class)->prefix('user')->group(function () {
        Route::put('/profile-update', 'profileUpdate');
        Route::get('/profile', 'profile');
        Route::get('/{id}', 'getUser');
        Route::post('/create', 'createUser');
        Route::put('/update/{id}', 'updateUser');
        Route::delete('/{id}', 'deleteUser');
    });

    Route::middleware(['auth:sanctum', 'role:admin|super-admin'])->group(function () {
        // Products
        Route::post('/products', [ProductController::class, 'create']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{id}', [ProductController::class, 'show']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'delete']);

        // Inventory
        Route::prefix('inventory')->group(function () {
            Route::post('/products/{product}/add-stock', [InventoryController::class, 'addStock']);
            Route::post('/products/{product}/remove-stock', [InventoryController::class, 'removeStock']);
            Route::get('/products/{product}/stock-history', [InventoryController::class, 'stockHistory']);
            Route::get('/low-stock-alerts', [InventoryController::class, 'lowStockAlerts']);
        });

        // Suppliers
        Route::apiResource('suppliers', SupplierController::class);

        // Purchase Orders
        Route::apiResource('purchase-orders', PurchaseOrderController::class)
            ->except(['update', 'destroy']);
        Route::post('/purchase-orders/{purchaseOrder}/deliver', [PurchaseOrderController::class, 'deliverOrder']);
    });
});
