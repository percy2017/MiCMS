<?php

namespace App\Listeners;

use App\Services\ReverbMonitorService;
use Laravel\Reverb\Events\MessageReceived;

class TrackMessageReceived
{
    public function handle(MessageReceived $event): void
    {
        $channel = '';

        try {
            $parsed = json_decode($event->message, true, 2);
            $channel = $parsed['data']['channel'] ?? $parsed['event'] ?? '';
        } catch (\Throwable) {
            //
        }

        app(ReverbMonitorService::class)->incrementMessages($channel);
    }
}
