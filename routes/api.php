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
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\InvestorController;
use App\Http\Controllers\Api\ExchangeController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::controller(AuthController::class)->prefix('auth')->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::controller(AuthController::class)->prefix('auth')->group(function () {
        Route::get('/profile', 'profile');
        Route::put('/profile', 'updateProfile');
        Route::post('/change-password', 'changePassword');
        Route::post('/logout', 'logout');
        Route::post('/logout-all', 'logoutAll');
    });

    // Business management routes
    Route::controller(BusinessController::class)->prefix('businesses')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    // User management routes
    Route::controller(UserController::class)->prefix('users')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/type/{type}', 'getByType');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    // Categories management
    Route::controller(CategoryController::class)->prefix('categories')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });
    
    // Brands management
    Route::controller(BrandController::class)->prefix('brands')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });
    
    // Units management
    Route::controller(UnitController::class)->prefix('units')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });
    
    // Products management
    Route::controller(ProductController::class)->prefix('products')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/low-stock', 'lowStock');
        Route::get('/in-stock', 'inStock');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        // New endpoints for inventory features
        Route::get('/{id}/stock-history', 'stockHistory');
        Route::post('/{id}/adjust-stock', 'adjustStock');
    });

    // Payment Methods management
    Route::controller(PaymentMethodController::class)->prefix('payment-methods')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    // Purchase Order management
    Route::controller(PurchaseOrderController::class)->prefix('purchase-orders')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::put('/{id}/items', 'updateItems');
                
        // Shipment management
        Route::post('/{id}/shipments', 'receiveShipment');
        Route::get('/{id}/shipments', 'getShipments');
        
        // Payment management
        Route::post('/{id}/payments', 'addPayment');
        Route::put('/{id}/payments/{paymentId}/status', 'updatePaymentStatus');
        
        Route::put('/{id}/cancel', 'cancel');
    });

    // User Balance management
    Route::controller(UserBalanceController::class)->prefix('user-balances')->group(function () {
        Route::get('/summary', 'getBusinessBalanceSummary');
        Route::get('/{userId}', 'getUserBalance');
        Route::get('/{userId}/history', 'getUserBalanceHistory');
        Route::post('/{userId}/adjustment', 'addBalanceAdjustment');
        Route::get('/{userId}/payments', 'getUserPaymentHistory');
    });

    // Payment management
    Route::controller(PaymentController::class)->prefix('payments')->group(function () {
        Route::get('/', 'index');
        Route::get('/purchase-orders/{id?}', 'getPurchaseOrderPayments');
        Route::get('/summary', 'getSummary');
        Route::get('/pending', 'getPendingPayments');
        Route::put('/{id}/status', 'updateStatus');
        Route::put('/bulk-status', 'bulkUpdateStatus');
    });

    // Sales Order management
    Route::controller(SalesOrderController::class)->prefix('sales-orders')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/update/{id}', 'update');
        Route::put('/{id}/items', 'updateItems');
        Route::put('/{id}/confirm', 'confirm');
        
        // Shipment management
        Route::post('/{id}/ship', 'ship');
        Route::get('/{id}/shipments', 'getShipments');
        
        // Payment management
        Route::post('/{id}/payments', 'addPayment');
        Route::put('/{id}/payments/{paymentId}/status', 'updatePaymentStatus');
        
        Route::put('/{id}/cancel', 'cancel');
    });

    // Expense Categories management
    Route::controller(ExpenseCategoryController::class)->prefix('expense-categories')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    // Expenses management
    Route::controller(ExpenseController::class)->prefix('expenses')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/reports', 'reports');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    // Report management
    Route::controller(ReportController::class)->prefix('reports')->group(function () {
        Route::get('/daily-transaction', 'dailyTransaction');
        Route::get('/daily-income-expense', 'dailyIncomeExpense');
    });

    // Employee Management
    Route::controller(EmployeeController::class)->prefix('employees')->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
        Route::get('/{id}/salary-history', 'salaryHistory');
        Route::post('/{id}/salary', 'addSalary');
        Route::put('/{id}/salary/{salaryId}', 'updateSalary');
        Route::delete('/{id}/salary/{salaryId}', 'deleteSalary');
        Route::get('/{id}/salary-summary', 'salarySummary');
    });

     // Investor Management
    Route::controller(InvestorController::class)->prefix('investors')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/summary', 'summary');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::patch('/{id}/close', 'close');
        Route::patch('/{id}/extend', 'extend');
        Route::get('/{id}/profit', 'calculateProfit');
    });

    // Exchange Management
    Route::controller(ExchangeController::class)->prefix('exchanges')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::post('/{id}/payments', 'addPayment');
        Route::put('/{id}/status', 'updateStatus');
    });
});
