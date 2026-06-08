<?php

namespace App\Listeners;

use App\Services\ReverbMonitorService;
use Laravel\Reverb\Events\ConnectionPruned;

class TrackConnectionPruned
{
    public function handle(ConnectionPruned $event): void
    {
        $channel = '';
        if ($data = $event->connection->data()) {
            $channel = is_string($data) ? $data : ($data['channel'] ?? '');
        }

        app(ReverbMonitorService::class)->decrementConnections((string) $channel);
    }
}
