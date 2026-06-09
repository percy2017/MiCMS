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
}
