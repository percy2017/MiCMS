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
    Route::get('/payment-gateways', [PosWooController::class, 'paymentGateways']);
    Route::post('/checkout', [PosWooController::class, 'checkout']);
    Route::post('/find-or-create-chat', [PosWooController::class, 'findOrCreateChat']);
    Route::get('/calendario', [PosWooController::class, 'subscriptionsPage'])->name('pos-woo.calendar');
    Route::get('/subscriptions', [PosWooController::class, 'subscriptions']);
    Route::get('/pedidos/{order}', [PosWooController::class, 'orderEdit'])->name('pos-woo.order-edit');
    Route::put('/pedidos/{order}', [PosWooController::class, 'orderUpdate'])->name('pos-woo.order-update');
});
