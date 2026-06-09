<?php

use Illuminate\Support\Facades\Route;
use Modules\ChatBot\Http\Controllers\Api\EvolutionWebhookController;

Route::post('/webhooks/evolution', [EvolutionWebhookController::class, 'handle'])
    ->name('webhooks.evolution');
