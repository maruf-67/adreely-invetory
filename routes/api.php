<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // Business management routes
    Route::prefix('businesses')->group(function () {
        Route::get('/', [BusinessController::class, 'index']);
        Route::post('/', [BusinessController::class, 'store']);
        Route::get('/{business}', [BusinessController::class, 'show']);
        Route::put('/{business}', [BusinessController::class, 'update']);
        Route::delete('/{business}', [BusinessController::class, 'destroy']);
    });

    // User management routes
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/type/{type}', [UserController::class, 'getByType']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
    });

    // Routes that require business context
    Route::middleware('business')->group(function () {
        // Categories management
        Route::prefix('categories')->group(function () {
            Route::get('/', [CategoryController::class, 'index']);
            Route::post('/', [CategoryController::class, 'store']);
            Route::get('/{category}', [CategoryController::class, 'show']);
            Route::put('/{category}', [CategoryController::class, 'update']);
            Route::delete('/{category}', [CategoryController::class, 'destroy']);
        });
        
        // Brands management
        Route::prefix('brands')->group(function () {
            Route::get('/', [BrandController::class, 'index']);
            Route::post('/', [BrandController::class, 'store']);
            Route::get('/{brand}', [BrandController::class, 'show']);
            Route::put('/{brand}', [BrandController::class, 'update']);
            Route::delete('/{brand}', [BrandController::class, 'destroy']);
        });
        
        // Units management
        Route::prefix('units')->group(function () {
            Route::get('/', [UnitController::class, 'index']);
            Route::post('/', [UnitController::class, 'store']);
            Route::get('/{unit}', [UnitController::class, 'show']);
            Route::put('/{unit}', [UnitController::class, 'update']);
            Route::delete('/{unit}', [UnitController::class, 'destroy']);
        });
        
        // Products management
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index']);
            Route::post('/', [ProductController::class, 'store']);
            Route::get('/low-stock', [ProductController::class, 'lowStock']);
            Route::get('/{product}', [ProductController::class, 'show']);
            Route::put('/{product}', [ProductController::class, 'update']);
            Route::delete('/{product}', [ProductController::class, 'destroy']);
        });
    });
});
