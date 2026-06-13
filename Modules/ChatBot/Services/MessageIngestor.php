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
        $event = $payload['event'] ?? null;
        $remoteJid = $payload['data']['key']['remoteJid'] ?? $payload['data']['remoteJid'] ?? null;
        $fromMe = $payload['data']['key']['fromMe'] ?? $payload['data']['fromMe'] ?? null;
        $messageId = $payload['data']['key']['id'] ?? $payload['data']['id'] ?? null;

        Log::warning('MessageIngestor: iniciando', [
            'channel_id' => $channel->id,
            'event' => $event,
            'remoteJid' => $remoteJid,
            'fromMe' => $fromMe,
            'message_id' => $messageId,
        ]);

        $driver = $this->registry->get($channel->type);
        if (! $driver instanceof ChannelInterface) {
            Log::warning('MessageIngestor: driver no encontrado', [
                'channel_id' => $channel->id,
                'type' => $channel->type->value,
            ]);

            return null;
        }

        try {
            $message = $driver->processIncoming($payload, $channel);
        } catch (\Throwable $e) {
            Log::error('MessageIngestor::ingest failed', [
                'channel_id' => $channel->id,
                'event' => $event,
                'remoteJid' => $remoteJid,
                'fromMe' => $fromMe,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return null;
        }

        if ($message && $channel->enabled) {
            try {
                ChatBotMessageReceived::dispatch($message);
                Log::warning('MessageIngestor: evento broadcast despachado', [
                    'message_id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('MessageIngestor: fallo al despachar evento', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } elseif (! $message) {
            Log::warning('MessageIngestor: driver no retorno mensaje (ignorado)', [
                'event' => $event,
                'remoteJid' => $remoteJid,
            ]);
        }

        return $message;
    }
}
