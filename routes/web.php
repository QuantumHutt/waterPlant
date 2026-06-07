<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\AccountingController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DeliveryController;
use App\Http\Controllers\Web\EmployeeController;
use App\Http\Controllers\Web\ExpenseController;
use App\Http\Controllers\Web\GallonController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\ProductCategoryController;
use App\Http\Controllers\Web\PurchaseController;
use App\Http\Controllers\Web\QualityController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\SaleController;
use App\Http\Controllers\Web\SupplierController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ProductionController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // User management
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);

    // Products & categories
    Route::resource('products', ProductController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('product-categories', [ProductCategoryController::class, 'store'])->name('product-categories.store');
    Route::delete('product-categories/{category}', [ProductCategoryController::class, 'destroy'])->name('product-categories.destroy');

    // Inventory
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('inventory/stock-in', [InventoryController::class, 'stockIn'])->name('inventory.stock-in');
    Route::post('inventory/stock-out', [InventoryController::class, 'stockOut'])->name('inventory.stock-out');
    Route::post('inventory/transfer', [InventoryController::class, 'transfer'])->name('inventory.transfer');
    Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');

    // Customers
    Route::resource('customers', CustomerController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('customers/{customer}/ledger', [CustomerController::class, 'ledger'])->name('customers.ledger');
    Route::post('customers/{customer}/payment', [CustomerController::class, 'payment'])->name('customers.payment');

    // Suppliers
    Route::resource('suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('suppliers/{supplier}/payment', [SupplierController::class, 'payment'])->name('suppliers.payment');

    // Purchases
    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');

    // Sales / POS
    Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
    Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
    Route::post('sales/{invoice}/payment', [SaleController::class, 'payment'])->name('sales.payment');
    Route::post('sales/{invoice}/return', [SaleController::class, 'returnInvoice'])->name('sales.return');

    // Production
    Route::get('production', [ProductionController::class, 'index'])->name('production.index');
    Route::post('production', [ProductionController::class, 'store'])->name('production.store');

    // Gallons
    Route::get('gallons', [GallonController::class, 'index'])->name('gallons.index');
    Route::post('gallons', [GallonController::class, 'store'])->name('gallons.store');

    // Accounting
    Route::get('accounting', [AccountingController::class, 'index'])->name('accounting.index');
    Route::post('accounting/journal', [AccountingController::class, 'storeJournal'])->name('accounting.journal');

    // Expenses
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');

    // Employees
    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::post('employees/attendance', [EmployeeController::class, 'attendance'])->name('employees.attendance');
    Route::post('employees/advance', [EmployeeController::class, 'advance'])->name('employees.advance');
    Route::post('employees/{employee}/payroll', [EmployeeController::class, 'payroll'])->name('employees.payroll');

    // Water Quality
    Route::get('quality', [QualityController::class, 'index'])->name('quality.index');
    Route::post('quality/test', [QualityController::class, 'storeTest'])->name('quality.test');
    Route::post('quality/schedule', [QualityController::class, 'storeSchedule'])->name('quality.schedule');
    Route::post('quality/schedule/{schedule}/log', [QualityController::class, 'logMaintenance'])->name('quality.log');

    // Delivery
    Route::get('delivery', [DeliveryController::class, 'index'])->name('delivery.index');
    Route::post('delivery/routes', [DeliveryController::class, 'storeRoute'])->name('delivery.routes.store');
    Route::post('delivery/assignments', [DeliveryController::class, 'storeAssignment'])->name('delivery.assignments.store');
    Route::post('delivery/items/{item}/confirm', [DeliveryController::class, 'confirm'])->name('delivery.confirm');

    // Reports
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
});

require __DIR__.'/auth.php';
