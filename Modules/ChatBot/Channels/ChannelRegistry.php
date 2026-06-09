<?php

namespace Modules\ChatBot\Channels;

use Modules\ChatBot\Enums\ChannelType;

class ChannelRegistry
{
    /** @var array<string, ChannelInterface> */
    private array $channels = [];

    public function register(ChannelInterface $channel): void
    {
        $this->channels[$channel->type()->value] = $channel;
    }

    public function get(ChannelType|string $type): ?ChannelInterface
    {
        $key = $type instanceof ChannelType ? $type->value : $type;

        return $this->channels[$key] ?? null;
    }

    /**
     * @return array<string, ChannelInterface>
     */
    public function all(): array
    {
        return $this->channels;
    }
}
