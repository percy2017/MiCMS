<?php

namespace Modules\ChatBot\Inbox\Shared;

use Illuminate\Support\Collection;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Models\Channel;

class ChannelInboxService
{
    /**
     * Find an existing inbox of the given type whose `config.<externalKey>` matches.
     * Defensive: works even if the column has malformed/missing JSON values.
     */
    public function findExisting(ChannelType $type, string $externalKey, ?int $excludeId = null, bool $enabledOnly = true): ?Channel
    {
        $strategy = app(InboxStrategyRegistry::class)->get($type);
        $configKey = $strategy->externalKeyName();

        return Channel::query()
            ->where('type', $type->value)
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->get()
            ->first(function (Channel $c) use ($configKey, $externalKey, $enabledOnly): bool {
                if ($enabledOnly && ! $c->enabled) {
                    return false;
                }

                $cfg = $c->config;
                if (is_string($cfg)) {
                    $cfg = json_decode($cfg, true);
                }
                if (! is_array($cfg)) {
                    return false;
                }

                return (string) ($cfg[$configKey] ?? '') === $externalKey;
            });
    }

    /**
     * List existing inboxes of a given type with their external key extracted safely.
     *
     * @return Collection<int, array{channel: Channel, external_key: string|null}>
     */
    public function listExisting(ChannelType $type): Collection
    {
        $strategy = app(InboxStrategyRegistry::class)->get($type);
        $configKey = $strategy->externalKeyName();

        return Channel::query()
            ->where('type', $type->value)
            ->orderBy('sort')
            ->get()
            ->map(function (Channel $c) use ($configKey): array {
                $cfg = $c->config;
                if (is_string($cfg)) {
                    $cfg = json_decode($cfg, true);
                }
                $externalKey = is_array($cfg) ? ($cfg[$configKey] ?? null) : null;

                return [
                    'channel' => $c,
                    'external_key' => is_string($externalKey) ? $externalKey : null,
                ];
            });
    }

    /**
     * List external keys already taken by existing inboxes of the given type.
     *
     * @return list<string>
     */
    public function takenExternalKeys(ChannelType $type): array
    {
        return $this->listExisting($type)
            ->pluck('external_key')
            ->filter()
            ->values()
            ->all();
    }
}
