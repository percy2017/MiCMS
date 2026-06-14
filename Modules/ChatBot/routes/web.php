<?php

use App\Http\Controllers\ChatBot\MessageReactionController;
use Illuminate\Support\Facades\Route;
use Modules\ChatBot\Http\Controllers\Admin\ChannelAdminController;
use Modules\ChatBot\Http\Controllers\Admin\ChatController;
use Modules\ChatBot\Http\Controllers\Admin\Evolution\EvolutionInboxController;
use Modules\ChatBot\Http\Controllers\Admin\OpenWa\OpenWaInboxController;
use Modules\ChatBot\Http\Controllers\Admin\QuickReplyController;
use Modules\ChatBot\Http\Controllers\Admin\WidgetController;
use Modules\ChatBot\Http\Controllers\Api\WebWidget\WidgetEmbedController;

Route::get('/embed/widget/{key}.js', WidgetEmbedController::class)
    ->name('embed.widget');

Route::permanentRedirect('/admin/chatbot/config', '/admin/canales/web-widget');
Route::permanentRedirect('/admin/chatbot/chats', '/admin/chats');

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('chatbot.admin.')
    ->group(function () {
        Route::get('/canales', [ChannelAdminController::class, 'index'])->name('canales');

        // Web widget channels (multi-inbox)
        Route::get('/canales/web-widget', [WidgetController::class, 'index'])->name('widget');
        Route::get('/canales/web-widget/nuevo', [WidgetController::class, 'create'])->name('widget.create');
        Route::post('/canales/web-widget', [WidgetController::class, 'store'])->name('widget.store');
        Route::get('/canales/web-widget/{webWidget}', [WidgetController::class, 'edit'])->name('widget.edit');
        Route::patch('/canales/web-widget/{webWidget}', [WidgetController::class, 'update'])->name('widget.update');
        Route::delete('/canales/web-widget/{webWidget}', [WidgetController::class, 'destroy'])->name('widget.destroy');

        // Evolution inboxes: ONE page (list + form), store -> index
        Route::get('/canales/evolution', [EvolutionInboxController::class, 'create'])->name('evolution.create');
        Route::post('/canales/evolution', [EvolutionInboxController::class, 'store'])->name('evolution.store');
        Route::post('/canales/evolution/fetch-instances', [EvolutionInboxController::class, 'fetchInstances'])->name('evolution.fetch-instances');
        Route::get('/canales/evolution/{evolution}/edit', [EvolutionInboxController::class, 'edit'])->name('evolution.edit');
        Route::patch('/canales/evolution/{evolution}', [EvolutionInboxController::class, 'update'])->name('evolution.update');
        Route::delete('/canales/evolution/{evolution}', [ChannelAdminController::class, 'destroy'])->name('evolution.destroy');

        // OpenWA inboxes: ONE page (list + form), store -> index
        Route::get('/canales/openwa', [OpenWaInboxController::class, 'create'])->name('openwa.create');
        Route::post('/canales/openwa', [OpenWaInboxController::class, 'store'])->name('openwa.store');
        Route::get('/canales/openwa/available', [OpenWaInboxController::class, 'fetchAvailable'])->name('openwa.available');
        Route::delete('/canales/openwa/{openwa}', [ChannelAdminController::class, 'destroy'])->name('openwa.destroy');
        Route::get('/canales/openwa/{openwa}/stats', [OpenWaInboxController::class, 'stats'])->name('openwa.stats');

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

        // Quick replies
        Route::get('/canales/respuestas-rapidas', [QuickReplyController::class, 'index'])->name('quick-replies.index');
        Route::get('/canales/respuestas-rapidas/nueva', [QuickReplyController::class, 'create'])->name('quick-replies.create');
        Route::post('/canales/respuestas-rapidas', [QuickReplyController::class, 'store'])->name('quick-replies.store');
        Route::get('/canales/respuestas-rapidas/{quickReply}/edit', [QuickReplyController::class, 'edit'])->name('quick-replies.edit');
        Route::patch('/canales/respuestas-rapidas/{quickReply}', [QuickReplyController::class, 'update'])->name('quick-replies.update');
        Route::delete('/canales/respuestas-rapidas/{quickReply}', [QuickReplyController::class, 'destroy'])->name('quick-replies.destroy');
    });
