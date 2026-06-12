<?php

namespace Modules\ChatBot\Services;

use Modules\ChatBot\Channels\ChannelInterface;
use Modules\ChatBot\Channels\ChannelRegistry;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

class ChannelManager
{
    public function __construct(
        private readonly ChannelRegistry $registry,
    ) {}

    public function driver(Channel $channel): ChannelInterface
    {
        $driver = $this->registry->get($channel->type);

        if (! $driver) {
            throw new \RuntimeException("No driver registered for channel type [{$channel->type->value}].");
        }

        return $driver;
    }

    public function dispatch(Conversation $conversation, Message $message): array
    {
        if ($message->role !== Message::ROLE_ADMIN) {
            return ['ok' => false, 'error' => 'Message is not an admin message.'];
        }

        $result = $this->driver($conversation->channel)->sendMessage($conversation, $message);

        if (! empty($result['provider_id']) && $message->exists) {
            $message->update([
                'external_id' => $result['provider_id'],
                'delivered_at' => now(),
            ]);
        }

        if (! ($result['ok'] ?? false) && $message->exists) {
            $message->update([
                'metadata' => array_merge($message->metadata ?? [], [
                    'last_error' => $result['error'] ?? 'unknown',
                ]),
            ]);
        }

        return $result;
    }
}
