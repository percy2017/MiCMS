<?php

namespace Modules\ChatBot\Inbox\Shared;

use InvalidArgumentException;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Inbox\Evolution\EvolutionInboxStrategy;
use Modules\ChatBot\Inbox\OpenWa\OpenWaInboxStrategy;

class InboxStrategyRegistry
{
    /** @var array<string, InboxStrategy> */
    private array $strategies = [];

    public function __construct(
        EvolutionInboxStrategy $evolution,
        OpenWaInboxStrategy $openwa,
    ) {
        $this->strategies[$evolution->type()->value] = $evolution;
        $this->strategies[$openwa->type()->value] = $openwa;
    }

    public function get(ChannelType|string $type): InboxStrategy
    {
        $key = $type instanceof ChannelType ? $type->value : $type;

        if (! isset($this->strategies[$key])) {
            throw new InvalidArgumentException("No InboxStrategy registered for type '{$key}'.");
        }

        return $this->strategies[$key];
    }

    /**
     * @return list<InboxStrategy>
     */
    public function all(): array
    {
        return array_values($this->strategies);
    }
}
