<?php

use App\Http\Controllers\ChatBot\MessageReactionController;
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
        Route::delete('/canales/evolution/{evolution}', [ChannelAdminController::class, 'destroy'])
            ->name('evolution.destroy');

        // Unified chats
        Route::get('/chats', [ChatController::class, 'index'])->name('chats');
        Route::get('/chats/search', [ChatController::class, 'search'])->name('chats.search');
        Route::get('/chats/{conversation}/messages', [ChatController::class, 'show'])->name('chats.show');
        Route::get('/chats/{conversation}', [ChatController::class, 'index'])->name('chats.open');
        Route::post('/chats/{conversation}/reply', [ChatController::class, 'reply'])->name('chats.reply');
        Route::post('/chats/{conversation}/read', [ChatController::class, 'read'])->name('chats.read');
        Route::patch('/chats/{conversation}', [ChatController::class, 'update'])->name('chats.update');
        Route::delete('/chats/{conversation}', [ChatController::class, 'destroy'])->name('chats.destroy');

        Route::post('/chats/{conversation}/messages/{message}/reactions', [MessageReactionController::class, 'store'])
            ->name('chats.reactions.store');
        Route::delete('/chats/{conversation}/messages/{message}/reactions', [MessageReactionController::class, 'destroy'])
            ->name('chats.reactions.destroy');
    });
