<?php

namespace App\Console\Commands;

use App\Services\ReverbMonitorService;
use Illuminate\Console\Command;

class ReverbTestEvent extends Command
{
    protected $signature = 'reverb:test-event {type=connection_established : Tipo de evento (connection_established, connection_closed, channel_created, channel_removed, message_handled)} {channel=test-channel}';

    protected $description = 'Envía un evento de prueba a Reverb';

    public function handle(ReverbMonitorService $service): void
    {
        $type = $this->argument('type');
        $channel = $this->argument('channel');

        match ($type) {
            'connection_established' => $service->incrementConnections($channel),
            'connection_closed' => $service->decrementConnections($channel),
            'channel_created' => $service->addChannel($channel),
            'channel_removed' => $service->removeChannel($channel),
            'message_handled' => $service->incrementMessages($channel),
            default => throw new \InvalidArgumentException("Tipo inválido: {$type}"),
        };

        $this->info("Evento '{$type}' enviado en canal '{$channel}'.");
    }
}
