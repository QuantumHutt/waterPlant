<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public
    Route::post('login', [AuthController::class, 'login'])->name('api.login');

    // Authenticated (Sanctum token)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('api.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('api.logout');

        Route::apiResource('users', UserController::class);

        // Catalog
        Route::apiResource('products', ProductController::class);
        Route::apiResource('product-categories', ProductCategoryController::class)->only(['index', 'store', 'destroy']);

        // Inventory
        Route::prefix('inventory')->group(function () {
            Route::get('/', [InventoryController::class, 'index'])->name('api.inventory.index');
            Route::get('movements', [InventoryController::class, 'movements'])->name('api.inventory.movements');
            Route::post('stock-in', [InventoryController::class, 'stockIn'])->name('api.inventory.stock-in');
            Route::post('stock-out', [InventoryController::class, 'stockOut'])->name('api.inventory.stock-out');
            Route::post('transfer', [InventoryController::class, 'transfer'])->name('api.inventory.transfer');
            Route::post('adjust', [InventoryController::class, 'adjust'])->name('api.inventory.adjust');
        });
    });
});
