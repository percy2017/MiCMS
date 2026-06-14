<?php

namespace Modules\ChatBot\Channels\Evolution;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class EvolutionApiClient
{
    public function __construct(
        private readonly string $serverUrl,
        private readonly string $apiKey,
        private readonly string $instanceName,
    ) {}

    private function headers(): array
    {
        return [
            'apikey' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    public function fetchInstances(): Response
    {
        return Http::withHeaders($this->headers())
            ->get("{$this->serverUrl}/instance/fetchInstances");
    }

    public function connectionState(): Response
    {
        return Http::withHeaders($this->headers())
            ->get("{$this->serverUrl}/instance/connectionState/{$this->instanceName}");
    }

    /**
     * @param  array{number: string, text: string, delay?: int, linkPreview?: bool, mentionsEveryOne?: bool, mentioned?: list<string>}  $params
     */
    public function sendText(array $params): Response
    {
        return Http::withHeaders($this->headers())
            ->post("{$this->serverUrl}/message/sendText/{$this->instanceName}", $params);
    }

    /**
     * @param  array{number: string, mediatype: string, mimetype: string, caption?: string, media?: string, fileName?: string}  $params
     */
    public function sendMedia(array $params): Response
    {
        return Http::withHeaders($this->headers())
            ->post("{$this->serverUrl}/message/sendMedia/{$this->instanceName}", $params);
    }

    /**
     * @param  array{number: string, sticker: string}  $params
     */
    public function sendSticker(array $params): Response
    {
        return Http::withHeaders($this->headers())
            ->post("{$this->serverUrl}/message/sendSticker/{$this->instanceName}", $params);
    }

    /**
     * @param  array{number: string, name: string, address?: string, latitude: float, longitude: float}  $params
     */
    public function sendLocation(array $params): Response
    {
        return Http::withHeaders($this->headers())
            ->post("{$this->serverUrl}/message/sendLocation/{$this->instanceName}", $params);
    }

    /**
     * @param  array{number: string, fullName: string, phoneNumber: string, organization?: string}  $params
     */
    public function sendContact(array $params): Response
    {
        return Http::withHeaders($this->headers())
            ->post("{$this->serverUrl}/message/sendContact/{$this->instanceName}", $params);
    }

    /**
     * Set the webhook for this instance. The Evolution API v2 accepts:
     *   - url (string, required)
     *   - events (array, required, min 1 item)
     *   - enabled (boolean, required)
     * webhookByEvents and webhookBase64 are NOT accepted by /webhook/set in
     * this Evolution version and are managed elsewhere.
     *
     * @param  array{url: string, events: list<string>, enabled?: bool}  $params
     */
    public function setWebhook(array $params): Response
    {
        $payload = array_merge(['enabled' => true], $params);

        return Http::withHeaders($this->headers())
            ->post("{$this->serverUrl}/webhook/set/{$this->instanceName}", ['webhook' => $payload]);
    }

    /**
     * Returns the webhook currently configured on the instance, or null if
     * none. Uses only the fields from the official Evolution v2 spec:
     *   - enabled
     *   - url
     *   - events
     *
     * @return array{enabled: bool, url: string, events: list<string>}|null
     */
    public function findWebhook(): ?array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->serverUrl}/webhook/find/{$this->instanceName}");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['url'])) {
            return null;
        }

        return [
            'enabled' => (bool) ($data['enabled'] ?? true),
            'url' => (string) $data['url'],
            'events' => array_values($data['events'] ?? []),
        ];
    }

    public function getBase64FromMediaMessage(string $messageKey): Response
    {
        return Http::withHeaders($this->headers())
            ->post("{$this->serverUrl}/chat/getBase64FromMediaMessage/{$this->instanceName}", [
                'message' => ['key' => ['id' => $messageKey]],
                'convertToMp4' => false,
            ]);
    }

    public function disconnectInstance(): Response
    {
        return Http::withHeaders($this->headers())
            ->delete("{$this->serverUrl}/instance/logout/{$this->instanceName}");
    }

    /**
     * @param  list<string>  $numbers
     */
    public function checkWhatsappNumbers(array $numbers): Response
    {
        return Http::withHeaders($this->headers())
            ->post("{$this->serverUrl}/chat/whatsappNumbers/{$this->instanceName}", [
                'numbers' => array_values($numbers),
            ]);
    }

    public function fetchProfile(string $number): Response
    {
        return Http::withHeaders($this->headers())
            ->post("{$this->serverUrl}/chat/fetchProfile/{$this->instanceName}", [
                'number' => $number,
            ]);
    }

    public function fetchBusinessProfile(string $number): Response
    {
        return Http::withHeaders($this->headers())
            ->post("{$this->serverUrl}/chat/fetchBusinessProfile/{$this->instanceName}", [
                'number' => $number,
            ]);
    }

    public function fetchProfilePictureUrl(string $number): Response
    {
        return Http::withHeaders($this->headers())
            ->post("{$this->serverUrl}/chat/fetchProfilePictureUrl/{$this->instanceName}", [
                'number' => $number,
            ]);
    }

    /**
     * Read the current Evolution instance settings.
     * Returns the full settings object (groupsIgnore, rejectCall, etc.) or null on failure.
     *
     * @return array<string, mixed>|null
     */
    public function getSettings(): ?array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->serverUrl}/settings/find/{$this->instanceName}");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Update Evolution instance settings. The live API requires ALL fields in the
     * payload, so we GET the current ones first and merge with the patch.
     *
     * @param  array<string, mixed>  $patch
     */
    public function setSettings(array $patch): Response
    {
        $current = $this->getSettings() ?? [];
        $merged = array_merge($current, $patch);

        return Http::withHeaders($this->headers())
            ->post("{$this->serverUrl}/settings/set/{$this->instanceName}", $merged);
    }
}
