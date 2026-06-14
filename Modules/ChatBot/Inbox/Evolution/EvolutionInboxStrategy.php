<?php

namespace Modules\ChatBot\Inbox\Evolution;

use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Channels\Evolution\EvolutionApiClient;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Inbox\Shared\ChannelInboxService;
use Modules\ChatBot\Inbox\Shared\InboxStrategy;
use Modules\ChatBot\Models\Channel;

class EvolutionInboxStrategy implements InboxStrategy
{
    public function __construct(
        private readonly ChannelInboxService $service,
    ) {}

    public function type(): ChannelType
    {
        return ChannelType::Evolution;
    }

    public function externalKeyName(): string
    {
        return 'instance_name';
    }

    public function listAvailable(): array
    {
        $serverUrl = (string) env('EVOLUTION_DEFAULT_SERVER_URL', '');
        $apiKey = (string) env('EVOLUTION_DEFAULT_API_KEY', '');

        if ($serverUrl === '' || $apiKey === '') {
            return [
                'configured' => false,
                'items' => [],
                'error' => 'Configura EVOLUTION_DEFAULT_SERVER_URL y EVOLUTION_DEFAULT_API_KEY en .env.',
            ];
        }

        $taken = $this->service->takenExternalKeys($this->type());

        try {
            $client = new EvolutionApiClient(
                serverUrl: rtrim($serverUrl, '/'),
                apiKey: $apiKey,
                instanceName: '',
            );

            $response = $client->fetchInstances();

            if (! $response->successful()) {
                return [
                    'configured' => true,
                    'items' => [],
                    'error' => 'Error Evolution API: HTTP '.$response->status(),
                ];
            }

            $instances = $response->json();
            if (! is_array($instances)) {
                $instances = [];
            }

            $items = [];
            foreach ($instances as $inst) {
                $name = $inst['name'] ?? null;
                if (! $name) {
                    continue;
                }
                $items[] = [
                    'external_key' => $name,
                    'external_id' => $inst['id'] ?? $inst['instanceId'] ?? null,
                    'status' => $inst['connectionStatus'] ?? 'unknown',
                    'owner' => $inst['ownerJid'] ?? null,
                    'profile_name' => $inst['profileName'] ?? null,
                    'profile_picture_url' => $inst['profilePicUrl'] ?? null,
                    'taken' => in_array($name, $taken, true),
                ];
            }

            return ['configured' => true, 'items' => $items];
        } catch (\Throwable $e) {
            return [
                'configured' => true,
                'items' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    public function defaultsForExternalItem(string $externalKey, array $externalItem): array
    {
        return [
            'server_url' => (string) env('EVOLUTION_DEFAULT_SERVER_URL', ''),
            'api_key' => (string) env('EVOLUTION_DEFAULT_API_KEY', ''),
            'instance_name' => $externalKey,
            'instance_id' => (string) ($externalItem['external_id'] ?? ''),
            'profile_name' => (string) ($externalItem['profile_name'] ?? ''),
            'profile_picture_url' => (string) ($externalItem['profile_picture_url'] ?? ''),
            'owner_jid' => (string) ($externalItem['owner'] ?? ''),
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
        if (! $channel instanceof Channel) {
            return;
        }

        $config = $channel->config;
        if (! is_array($config) || empty($config['server_url']) || empty($config['api_key']) || empty($config['instance_name'])) {
            return;
        }

        try {
            $client = new EvolutionApiClient(
                serverUrl: rtrim((string) $config['server_url'], '/'),
                apiKey: (string) $config['api_key'],
                instanceName: (string) $config['instance_name'],
            );

            // The system always registers the webhook with MESSAGES_UPSERT so
            // that incoming WhatsApp messages reach the inbox.
            $client->setWebhook([
                'url' => route('webhooks.evolution', $channel),
                'events' => ['MESSAGES_UPSERT'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Evolution setWebhook failed on inbox create', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Read the current webhook configuration for the given instance from
     * the Evolution API. Returns null if the instance has no webhook yet.
     *
     * @return array{url: string, events: list<string>, by_events: bool, base64: bool}|null
     */
    public function currentWebhook(string $instanceName): ?array
    {
        $serverUrl = (string) env('EVOLUTION_DEFAULT_SERVER_URL', '');
        $apiKey = (string) env('EVOLUTION_DEFAULT_API_KEY', '');

        if ($serverUrl === '' || $apiKey === '' || $instanceName === '') {
            return null;
        }

        try {
            $client = new EvolutionApiClient(
                serverUrl: rtrim($serverUrl, '/'),
                apiKey: $apiKey,
                instanceName: $instanceName,
            );

            return $client->findWebhook();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Read the current Evolution instance settings (groupsIgnore, rejectCall, etc.)
     * via GET /settings/find/{instance}. Returns null on failure.
     *
     * @return array<string, mixed>|null
     */
    public function currentSettings(string $instanceName): ?array
    {
        $serverUrl = (string) env('EVOLUTION_DEFAULT_SERVER_URL', '');
        $apiKey = (string) env('EVOLUTION_DEFAULT_API_KEY', '');

        if ($serverUrl === '' || $apiKey === '' || $instanceName === '') {
            return null;
        }

        try {
            $client = new EvolutionApiClient(
                serverUrl: rtrim($serverUrl, '/'),
                apiKey: $apiKey,
                instanceName: $instanceName,
            );

            return $client->getSettings();
        } catch (\Throwable) {
            return null;
        }
    }

    public function integrationInfo(string $externalKey): ?array
    {
        $hook = $this->currentWebhook($externalKey);
        $settings = $this->currentSettings($externalKey);

        if ($hook === null && $settings === null) {
            return null;
        }

        return [
            'webhook' => $hook,
            'settings' => $settings,
        ];
    }

    /**
     * Find an external instance and return it with the full API shape
     * (id, name, status, owner, profile_name, profile_picture_url, etc.).
     * Returns null if not found.
     *
     * @return array<string, mixed>|null
     */
    public function findExternalItemFull(string $externalKey): ?array
    {
        $available = $this->listAvailable();
        foreach ($available['items'] as $item) {
            if (($item['external_key'] ?? null) === $externalKey) {
                return $item;
            }
        }

        return null;
    }
}
