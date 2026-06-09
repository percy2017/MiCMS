<?php

use Illuminate\Support\Facades\Route;
use Modules\ChatBot\Http\Controllers\Admin\ChannelAdminController;
use Modules\ChatBot\Http\Controllers\Admin\ChatController;
use Modules\ChatBot\Http\Controllers\Admin\WidgetController;

// Old routes - redirect 301 to new structure
Route::permanentRedirect('/admin/chatbot/config', '/admin/canales/web-widget');
Route::permanentRedirect('/admin/chatbot/chats', '/admin/chats');

// New admin routes
Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('chatbot.admin.')
    ->group(function () {
        // Channels list
        Route::get('/canales', [ChannelAdminController::class, 'index'])
            ->name('canales');

        // Web widget channels
        Route::post('/canales/web-widget', [WidgetController::class, 'store'])
            ->name('widget.store');
        Route::get('/canales/web-widget', [WidgetController::class, 'edit'])
            ->name('widget');
        Route::patch('/canales/web-widget', [WidgetController::class, 'update'])
            ->name('widget.update');

        // Evolution channels (order matters: specific routes before wildcard)
        Route::post('/canales/evolution', [ChannelAdminController::class, 'storeEvolution'])
            ->name('evolution.store');
        Route::post('/canales/evolution/fetch-instances', [ChannelAdminController::class, 'fetchInstances'])
            ->name('evolution.fetch-instances');
        Route::get('/canales/evolution/{evolution}', [ChannelAdminController::class, 'editEvolution'])
            ->name('evolution.edit');
        Route::patch('/canales/evolution/{evolution}', [ChannelAdminController::class, 'updateEvolution'])
            ->name('evolution.update');

        // Unified chats
        Route::get('/chats', [ChatController::class, 'index'])->name('chats');
        Route::get('/chats/{conversation}', [ChatController::class, 'show'])->name('chats.show');
        Route::post('/chats/{conversation}/reply', [ChatController::class, 'reply'])->name('chats.reply');
        Route::post('/chats/{conversation}/read', [ChatController::class, 'read'])->name('chats.read');
        Route::post('/chats/{conversation}/close', [ChatController::class, 'close'])->name('chats.close');
        Route::post('/chats/{conversation}/reopen', [ChatController::class, 'reopen'])->name('chats.reopen');
        Route::delete('/chats/{conversation}', [ChatController::class, 'destroy'])->name('chats.destroy');
    });
