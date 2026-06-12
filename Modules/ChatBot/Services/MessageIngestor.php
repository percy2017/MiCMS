<?php

namespace Modules\ChatBot\Services;

use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Channels\ChannelInterface;
use Modules\ChatBot\Channels\ChannelRegistry;
use Modules\ChatBot\Events\ChatBotMessageReceived;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Message;

class MessageIngestor
{
    public function __construct(
        private readonly ChannelRegistry $registry,
    ) {}

    public function ingest(Channel $channel, array $payload): ?Message
    {
        $driver = $this->registry->get($channel->type);
        if (! $driver instanceof ChannelInterface) {
            return null;
        }

        try {
            $message = $driver->processIncoming($payload, $channel);
        } catch (\Throwable $e) {
            Log::error('MessageIngestor::ingest failed', [
                'channel_id' => $channel->id,
                'event' => $payload['event'] ?? null,
                'remoteJid' => $payload['data']['key']['remoteJid'] ?? $payload['data']['remoteJid'] ?? null,
                'fromMe' => $payload['data']['key']['fromMe'] ?? $payload['data']['fromMe'] ?? null,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return null;
        }

        if ($message && $channel->enabled) {
            ChatBotMessageReceived::dispatch($message);
        }

        return $message;
    }
}
