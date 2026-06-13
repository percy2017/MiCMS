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

        // Web widget channels (multi-inbox)
        Route::get('/canales/web-widget', [WidgetController::class, 'index'])
            ->name('widget');
        Route::get('/canales/web-widget/nuevo', [WidgetController::class, 'create'])
            ->name('widget.create');
        Route::post('/canales/web-widget', [WidgetController::class, 'store'])
            ->name('widget.store');
        Route::get('/canales/web-widget/{webWidget}', [WidgetController::class, 'edit'])
            ->name('widget.edit');
        Route::patch('/canales/web-widget/{webWidget}', [WidgetController::class, 'update'])
            ->name('widget.update');
        Route::delete('/canales/web-widget/{webWidget}', [WidgetController::class, 'destroy'])
            ->name('widget.destroy');

        // Evolution channels (order matters: specific routes before wildcard)
        Route::post('/canales/evolution', [ChannelAdminController::class, 'storeEvolution'])
            ->name('evolution.store');
        Route::post('/canales/evolution/fetch-instances', [ChannelAdminController::class, 'fetchInstances'])
            ->name('evolution.fetch-instances');
        Route::get('/canales/evolution/seleccionar', [ChannelAdminController::class, 'evolutionSelector'])
            ->name('evolution.selector');
        Route::post('/canales/evolution/select-store', [ChannelAdminController::class, 'evolutionSelectStore'])
            ->name('evolution.select-store');
        Route::get('/canales/evolution/{evolution}', [ChannelAdminController::class, 'editEvolution'])
            ->name('evolution.edit');
        Route::patch('/canales/evolution/{evolution}', [ChannelAdminController::class, 'updateEvolution'])
            ->name('evolution.update');
        Route::delete('/canales/evolution/{evolution}', [ChannelAdminController::class, 'destroy'])
            ->name('evolution.destroy');

        // OpenWA channels
        Route::get('/canales/openwa/seleccionar', [ChannelAdminController::class, 'openwaSelector'])
            ->name('openwa.selector');
        Route::post('/canales/openwa', [ChannelAdminController::class, 'storeOpenWa'])
            ->name('openwa.store');
        Route::get('/canales/openwa/available', [ChannelAdminController::class, 'openwaAvailableSessions'])
            ->name('openwa.available');
        Route::get('/canales/openwa/{openwa}', [ChannelAdminController::class, 'editOpenWa'])
            ->name('openwa.edit');
        Route::patch('/canales/openwa/{openwa}', [ChannelAdminController::class, 'updateOpenWa'])
            ->name('openwa.update');
        Route::delete('/canales/openwa/{openwa}', [ChannelAdminController::class, 'destroy'])
            ->name('openwa.destroy');
        Route::get('/canales/openwa/{openwa}/stats', [ChannelAdminController::class, 'openwaStats'])
            ->name('openwa.stats');

        // Evolution selector (crear inbox seleccionando instancia)
        Route::get('/canales/evolution/seleccionar', [ChannelAdminController::class, 'evolutionSelector'])
            ->name('evolution.selector');
        Route::post('/canales/evolution/select-store', [ChannelAdminController::class, 'evolutionSelectStore'])
            ->name('evolution.select-store');

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
