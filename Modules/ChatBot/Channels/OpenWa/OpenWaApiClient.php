<?php

namespace Modules\ChatBot\Channels\OpenWa;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP para OpenWA API.
 *
 * Credenciales leídas desde `config('chatbot.openwa.*')` que a su vez vienen de .env:
 *   OPENWA_BASE_URL
 *   OPENWA_API_KEY
 *   OPENWA_WEBHOOK_SECRET
 *   OPENWA_TIMEOUT
 *
 * No recibe credenciales por canal — son globales a toda la app.
 * Cada Channel solo guarda `session_name` (qué sesión específica de OpenWA usa).
 */
class OpenWaApiClient
{
    public function __construct(
        ?string $baseUrl = null,
        ?string $apiKey = null,
        ?int $timeout = null,
    ) {
        $this->baseUrl = $baseUrl ?? (string) config('chatbot.openwa.base_url', '');
        $this->apiKey = $apiKey ?? (string) config('chatbot.openwa.api_key', '');
        $this->timeout = $timeout ?? (int) config('chatbot.openwa.timeout', 15);
    }

    private readonly string $baseUrl;

    private readonly string $apiKey;

    private readonly int $timeout;

    public function isConfigured(): bool
    {
        return ! empty($this->baseUrl) && ! empty($this->apiKey);
    }

    private function headers(): array
    {
        return [
            'X-API-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Request-ID' => 'req_'.now()->getTimestampMs(),
        ];
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl ?? '', '/').'/'.ltrim($path, '/');
    }

    public function health(): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->get($this->url('health'));
    }

    public function listSessions(): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->get($this->url('sessions'));
    }

    public function getSession(string $sessionId): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->get($this->url("sessions/{$sessionId}"));
    }

    public function createSession(string $name): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->post($this->url('sessions'), ['name' => $name]);
    }

    public function startSession(string $sessionId): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->post($this->url("sessions/{$sessionId}/start"));
    }

    public function stopSession(string $sessionId): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->post($this->url("sessions/{$sessionId}/stop"));
    }

    public function getQrCode(string $sessionId): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->get($this->url("sessions/{$sessionId}/qr"));
    }

    public function deleteSession(string $sessionId): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->delete($this->url("sessions/{$sessionId}"));
    }

    /**
     * @param  array{chatId: string, text: string}  $params
     */
    public function sendText(string $sessionId, array $params): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->post($this->url("sessions/{$sessionId}/messages/send-text"), $params);
    }

    /**
     * @param  array{chatId: string, image: array{base64: string}, caption?: string, mimetype: string}  $params
     */
    public function sendImage(string $sessionId, array $params): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->post($this->url("sessions/{$sessionId}/messages/send-image"), $params);
    }

    /**
     * @param  array{chatId: string, video: array{base64: string}, caption?: string, mimetype: string}  $params
     */
    public function sendVideo(string $sessionId, array $params): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->post($this->url("sessions/{$sessionId}/messages/send-video"), $params);
    }

    /**
     * @param  array{chatId: string, audio: array{base64: string}, ptt?: bool, mimetype: string}  $params
     */
    public function sendAudio(string $sessionId, array $params): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->post($this->url("sessions/{$sessionId}/messages/send-audio"), $params);
    }

    /**
     * @param  array{chatId: string, document: array{base64: string}, filename: string, caption?: string, mimetype: string}  $params
     */
    public function sendDocument(string $sessionId, array $params): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->post($this->url("sessions/{$sessionId}/messages/send-document"), $params);
    }

    /**
     * @param  array{chatId: string, latitude: float, longitude: float, description?: string, address?: string}  $params
     */
    public function sendLocation(string $sessionId, array $params): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->post($this->url("sessions/{$sessionId}/messages/send-location"), $params);
    }

    /**
     * @param  array{chatId: string, contact: array{name: string, phone: string}}  $params
     */
    public function sendContact(string $sessionId, array $params): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->post($this->url("sessions/{$sessionId}/messages/send-contact"), $params);
    }

    /**
     * @param  array{url: string, events: list<string>, secret?: string, headers?: array<string,string>, retryCount?: int}  $params
     */
    public function createWebhook(string $sessionId, array $params): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->post($this->url("sessions/{$sessionId}/webhooks"), $params);
    }

    public function listWebhooks(string $sessionId): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->get($this->url("sessions/{$sessionId}/webhooks"));
    }

    public function deleteWebhook(string $sessionId, string $webhookId): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->delete($this->url("sessions/{$sessionId}/webhooks/{$webhookId}"));
    }

    /**
     * Extrae el array de datos del response, soportando tanto formato
     * envuelto (`{success, data: [...]}`) como plano (`[...]`).
     *
     * @return array<int|string, mixed>
     */
    public static function extractData(Response $response): array
    {
        $body = $response->json();
        if (is_array($body) && isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }
        if (is_array($body) && array_is_list($body)) {
            return $body;
        }

        return [];
    }
}
