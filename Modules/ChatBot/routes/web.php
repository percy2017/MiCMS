<?php

use Illuminate\Support\Facades\Route;
use Modules\ChatBot\Http\Controllers\Admin\ChatController;
use Modules\ChatBot\Http\Controllers\Admin\WidgetController;

Route::middleware(['auth', 'verified'])
    ->prefix('admin/chatbot')
    ->name('chatbot.admin.')
    ->group(function () {
        Route::get('/config', [WidgetController::class, 'edit'])->name('widget');
        Route::patch('/config', [WidgetController::class, 'update'])->name('widget.update');

        Route::get('/chats', [ChatController::class, 'index'])->name('chats');
        Route::post('/chats/{conversation}/reply', [ChatController::class, 'reply'])->name('chats.reply');
        Route::post('/chats/{conversation}/read', [ChatController::class, 'read'])->name('chats.read');
        Route::post('/chats/{conversation}/close', [ChatController::class, 'close'])->name('chats.close');
        Route::post('/chats/{conversation}/reopen', [ChatController::class, 'reopen'])->name('chats.reopen');
        Route::delete('/chats/{conversation}', [ChatController::class, 'destroy'])->name('chats.destroy');
    });
