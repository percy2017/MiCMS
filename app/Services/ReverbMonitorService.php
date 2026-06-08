<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ReverbMonitorService
{
    protected string $prefix = 'reverb_';

    protected const MAX_EVENTS = 100;

    protected const MESSAGE_SAMPLE_RATE = 20;

    public function incrementConnections(string $channel = ''): void
    {
        Cache::increment($this->prefix.'connections');

        $current = $this->getConnections();
        $peak = Cache::get($this->prefix.'peak', 0);

        if ($current > $peak) {
            Cache::forever($this->prefix.'peak', $current);
        }

        $this->push('connection_established', $channel, "Conexión establecida en {$channel}");
    }

    public function decrementConnections(string $channel = ''): void
    {
        if ($this->getConnections() > 0) {
            Cache::decrement($this->prefix.'connections');
        }

        $this->push('connection_closed', $channel, "Conexión cerrada en {$channel}");
    }

    public function incrementMessages(string $channel = ''): void
    {
        Cache::increment($this->prefix.'messages');

        $count = Cache::increment($this->prefix.'msg_count');

        if ($count % self::MESSAGE_SAMPLE_RATE === 0) {
            $this->push('message_handled', $channel, 'Mensaje manejado');
        }
    }

    public function addChannel(string $channelName): void
    {
        $channels = Cache::get($this->prefix.'channels', []);
        $channels[$channelName] = now()->toIso8601String();
        Cache::forever($this->prefix.'channels', $channels);

        $this->push('channel_created', $channelName, "Canal creado: {$channelName}");
    }

    public function removeChannel(string $channelName): void
    {
        $channels = Cache::get($this->prefix.'channels', []);
        unset($channels[$channelName]);
        Cache::forever($this->prefix.'channels', $channels);

        $this->push('channel_removed', $channelName, "Canal eliminado: {$channelName}");
    }

    public function getConnections(): int
    {
        return Cache::get($this->prefix.'connections', 0);
    }

    public function getMessages(): int
    {
        return Cache::get($this->prefix.'messages', 0);
    }

    public function getPeakConnections(): int
    {
        return Cache::get($this->prefix.'peak', 0);
    }

    public function getChannels(): array
    {
        return Cache::get($this->prefix.'channels', []);
    }

    public function getEvents(): array
    {
        return Cache::get($this->prefix.'events', []);
    }

    public function getStats(): array
    {
        return [
            'connections' => $this->getConnections(),
            'peak_connections' => $this->getPeakConnections(),
            'messages' => $this->getMessages(),
            'channels' => $this->getChannels(),
            'active_channels' => count($this->getChannels()),
            'events' => $this->getEvents(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public function reset(): void
    {
        Cache::forever($this->prefix.'connections', 0);
        Cache::forever($this->prefix.'messages', 0);
        Cache::forever($this->prefix.'peak', 0);
        Cache::forever($this->prefix.'msg_count', 0);
        Cache::forever($this->prefix.'channels', []);
        Cache::forever($this->prefix.'events', []);
    }

    protected function push(string $type, string $channel, string $details): void
    {
        $events = Cache::get($this->prefix.'events', []);

        $events[] = [
            'type' => $type,
            'channel' => $channel,
            'details' => $details,
            'timestamp' => now()->toIso8601String(),
            'time' => now()->format('H:i:s'),
        ];

        if (count($events) > self::MAX_EVENTS) {
            $events = array_slice($events, -self::MAX_EVENTS);
        }

        Cache::forever($this->prefix.'events', $events);
    }
}
