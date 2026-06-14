<?php

namespace Modules\ChatBot\Inbox\OpenWa;

use Modules\ChatBot\Channels\OpenWa\OpenWaApiClient;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Inbox\Shared\ChannelInboxService;
use Modules\ChatBot\Inbox\Shared\InboxStrategy;
use Modules\ChatBot\Models\Channel;

class OpenWaInboxStrategy implements InboxStrategy
{
    public function __construct(
        private readonly ChannelInboxService $service,
    ) {}

    public function type(): ChannelType
    {
        return ChannelType::OpenWa;
    }

    public function externalKeyName(): string
    {
        return 'session_name';
    }

    public function listAvailable(): array
    {
        $client = new OpenWaApiClient;

        if (! $client->isConfigured()) {
            return [
                'configured' => false,
                'items' => [],
                'error' => 'OpenWA no está configurado. Define OPENWA_BASE_URL y OPENWA_API_KEY en .env.',
            ];
        }

        $response = $client->listSessions();
        if (! $response->successful()) {
            return [
                'configured' => true,
                'items' => [],
                'error' => 'Error al conectar con OpenWA: HTTP '.$response->status(),
            ];
        }

        $sessions = OpenWaApiClient::extractData($response);
        $taken = $this->service->takenExternalKeys($this->type());

        $items = [];
        foreach ($sessions as $s) {
            $name = $s['name'] ?? null;
            if (! $name) {
                continue;
            }
            $items[] = [
                'external_key' => $name,
                'external_id' => $s['id'] ?? null,
                'status' => $s['status'] ?? 'UNKNOWN',
                'phone' => $s['phone'] ?? null,
                'push_name' => $s['pushName'] ?? null,
                'connected_at' => $s['connectedAt'] ?? null,
                'taken' => in_array($name, $taken, true),
            ];
        }

        return ['configured' => true, 'items' => $items];
    }

    public function defaultsForExternalItem(string $externalKey, array $externalItem): array
    {
        return [
            'session_name' => $externalKey,
        ];
    }

    public function findExternalItem(string $externalKey): ?array
    {
        $result = $this->listAvailable();
        foreach ($result['items'] as $item) {
            if (($item['external_key'] ?? null) === $externalKey) {
                return $item;
            }
        }

        return null;
    }

    public function onInboxCreated(mixed $channel): void
    {
        // No remote side-effect required at create time for OpenWA.
    }

    public function integrationInfo(string $externalKey): ?array
    {
        return null;
    }
}
