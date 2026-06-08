<?php

namespace App\Listeners;

use App\Services\ReverbMonitorService;
use Laravel\Reverb\Events\ChannelRemoved;

class TrackChannelRemoved
{
    public function handle(ChannelRemoved $event): void
    {
        app(ReverbMonitorService::class)->removeChannel(
            $event->channel->name()
        );
    }
}
