<?php

namespace Modules\ChatBot\Services;

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

        $message = $driver->processIncoming($payload, $channel);

        if ($message && $channel->enabled) {
            broadcast(new ChatBotMessageReceived($message))->toOthers();
        }

        return $message;
    }
}
