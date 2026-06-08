<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// All API route names are prefixed with "api." so they never collide with the
// web (Inertia) route names used by route() in the Vue pages.
Route::prefix('v1')->name('api.')->group(function () {
    // Public
    Route::post('login', [AuthController::class, 'login'])->name('login');

    // Authenticated (Sanctum token)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::apiResource('users', UserController::class);

        // Catalog
        Route::apiResource('products', ProductController::class);
        Route::apiResource('product-categories', ProductCategoryController::class)->only(['index', 'store', 'destroy']);

        // Inventory
        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/', [InventoryController::class, 'index'])->name('index');
            Route::get('movements', [InventoryController::class, 'movements'])->name('movements');
            Route::post('stock-in', [InventoryController::class, 'stockIn'])->name('stock-in');
            Route::post('stock-out', [InventoryController::class, 'stockOut'])->name('stock-out');
            Route::post('transfer', [InventoryController::class, 'transfer'])->name('transfer');
            Route::post('adjust', [InventoryController::class, 'adjust'])->name('adjust');
        });
    });
});
