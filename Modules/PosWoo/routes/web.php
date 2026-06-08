<?php

use Illuminate\Support\Facades\Route;
use Modules\PosWoo\Http\Controllers\PosWooController;

Route::middleware(['auth', 'verified'])->prefix('admin/pos-woo')->group(function () {
    Route::get('/', [PosWooController::class, 'dashboard'])->name('pos-woo.dashboard');
    Route::get('/pedidos', [PosWooController::class, 'ordersPage'])->name('pos-woo.orders');
    Route::get('/products', [PosWooController::class, 'searchProducts']);
    Route::get('/products/{product}/variations', [PosWooController::class, 'productVariations']);
    Route::get('/customers', [PosWooController::class, 'searchCustomers']);
    Route::get('/orders', [PosWooController::class, 'orders']);
    Route::post('/checkout', [PosWooController::class, 'checkout']);
});
