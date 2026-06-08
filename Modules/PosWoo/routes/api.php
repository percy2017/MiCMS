<?php

use Illuminate\Support\Facades\Route;
use Modules\PosWoo\Http\Controllers\PosWooController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('poswoos', PosWooController::class)->names('poswoo');
});
