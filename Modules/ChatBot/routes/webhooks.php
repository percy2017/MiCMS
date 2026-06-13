<?php

use Illuminate\Support\Facades\Route;
use Modules\ChatBot\Http\Controllers\Api\Evolution\EvolutionWebhookController;
use Modules\ChatBot\Http\Controllers\Api\OpenWa\OpenWaWebhookController;
use Modules\ChatBot\Http\Controllers\Api\WebWidget\WidgetWebhookController;

Route::post('/webhooks/evolution/{channel}', [EvolutionWebhookController::class, 'handle'])
    ->name('webhooks.evolution');

Route::post('/webhooks/openwa/{channel}', [OpenWaWebhookController::class, 'handle'])
    ->name('webhooks.openwa');

Route::any('/webhooks/widget/{channel}/{token}', [WidgetWebhookController::class, 'handle'])
    ->name('webhooks.widget');
