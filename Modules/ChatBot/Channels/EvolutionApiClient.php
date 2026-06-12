<?php

namespace Modules\ChatBot\Channels;

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
     * @param  array{url: string, webhook_by_events?: bool, webhook_base64?: bool, webhook_events?: list<string>}  $params
     */
    public function setWebhook(array $params): Response
    {
        return Http::withHeaders($this->headers())
            ->put("{$this->serverUrl}/webhook/setWebhook/{$this->instanceName}", $params);
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
}
