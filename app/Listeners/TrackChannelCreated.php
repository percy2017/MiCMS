<?php

namespace App\Listeners;

use App\Services\ReverbMonitorService;
use Laravel\Reverb\Events\ChannelCreated;

class TrackChannelCreated
{
    public function handle(ChannelCreated $event): void
    {
        app(ReverbMonitorService::class)->addChannel(
            $event->channel->name()
        );
    }
}
