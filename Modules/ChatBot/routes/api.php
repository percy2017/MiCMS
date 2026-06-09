<?php

use Illuminate\Support\Facades\Route;
use Modules\ChatBot\Http\Controllers\Api\MessageController;
use Modules\ChatBot\Http\Controllers\Api\SessionController;

Route::prefix('chatbot')->name('chatbot.api.')->group(function () {
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('/widget', [SessionController::class, 'widget'])->name('widget');
    });

    Route::middleware(['web', 'throttle:10,1'])->group(function () {
        Route::post('/session', [SessionController::class, 'start'])->name('session.start');
        Route::get('/session', [SessionController::class, 'me'])->name('session.me');
    });

    Route::middleware(['web', 'auth', 'throttle:60,1'])->group(function () {
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])
            ->name('messages.store');
        Route::post('/conversations/{conversation}/typing', [MessageController::class, 'typing'])
            ->name('messages.typing');
    });
});
