<?php

use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\PurchaseOrderController;
use App\Http\Controllers\API\SupplierController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/user/profile-update', [\App\Http\Controllers\UserController::class, 'profileUpdate']);


    Route::middleware(['role:admin'])->get('/admin', function () {
        return response()->json(['message' => 'Admin access granted']);
    });



    // Multiple roles
    Route::middleware(['role:admin|super-admin'])->get('/super', function () {
        // ...
    });

    Route::middleware(['auth:sanctum', 'role:admin|super-admin'])->group(function () {
        Route::apiResource('products', \App\Http\Controllers\API\ProductController::class);
        Route::apiResource('users', \App\Http\Controllers\API\UserController::class);
        Route::apiResource('payments', \App\Http\Controllers\API\PaymentController::class);
        Route::apiResource('categories', \App\Http\Controllers\API\CategoryController::class);
    });

    // routes/api.php
    Route::middleware(['auth:sanctum', 'role:admin|super-admin'])->prefix('inventory')->group(function () {
        Route::post('/products/{product}/add-stock', [InventoryController::class, 'addStock']);
        Route::post('/products/{product}/remove-stock', [InventoryController::class, 'removeStock']);
        Route::get('/products/{product}/stock-history', [InventoryController::class, 'stockHistory']);
        Route::get('/low-stock-alerts', [InventoryController::class, 'lowStockAlerts']);
    });

    // routes/api.php
    Route::middleware(['auth:sanctum', 'role:admin|super-admin'])->group(function () {
        // Suppliers
        Route::apiResource('suppliers', SupplierController::class);

        // Purchase Orders
        Route::apiResource('purchase-orders', PurchaseOrderController::class)
            ->except(['update', 'destroy']);

        Route::post(
            '/purchase-orders/{purchaseOrder}/deliver',
            [PurchaseOrderController::class, 'deliverOrder']
        );

        //        GET     /api/suppliers           List suppliers
        //            POST    /api/suppliers          Create new supplier
        //
        //POST    /api/purchase-orders    Create purchase order
        //GET     /api/purchase-orders    List all purchase orders
        //POST    /api/purchase-orders/{id}/deliver  Mark order as delivered
    });
    // Combined with other middleware
    Route::middleware(['auth:sanctum', 'role:manager'])->put('/update', function () {
        // ...
    });
});
