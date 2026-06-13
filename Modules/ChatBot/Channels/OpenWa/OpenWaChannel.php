<?php

namespace Modules\ChatBot\Channels\OpenWa;

use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Channels\ChannelInterface;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Enums\ConversationStatus;
use Modules\ChatBot\Enums\MessageType;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

/**
 * Orquestador del canal OpenWA.
 *
 * Configuración:
 *  - Credenciales (base_url, api_key, secret) → .env vía config('chatbot.openwa.*')
 *  - session_name → Channel.config (qué sesión específica de OpenWA)
 *
 * Componentes:
 *  - OpenWaApiClient        → cliente HTTP (lee credenciales de .env)
 *  - OpenWaMessageBuilder   → construye params de envío
 *  - OpenWaSendValidator    → valida antes de enviar
 *  - OpenWaMessageParser    → parsing puro del payload
 *  - OpenWaMediaEnricher    → descarga media desde la URL temporal
 *  - OpenWaUserLinker       → asocia usuarios a conversaciones
 *  - OpenWaReactionHandler  → maneja reacciones
 *  - OpenWaStatsProvider    → status + lista inboxes disponibles
 *  - OpenWaConfigProvider   → config fields
 */
class OpenWaChannel implements ChannelInterface
{
    public function __construct(
        private readonly OpenWaConfigProvider $configProvider = new OpenWaConfigProvider,
        private readonly OpenWaMessageBuilder $messageBuilder = new OpenWaMessageBuilder,
        private readonly OpenWaSendValidator $sendValidator = new OpenWaSendValidator,
        private readonly OpenWaMediaEnricher $mediaEnricher = new OpenWaMediaEnricher,
        private readonly OpenWaUserLinker $userLinker = new OpenWaUserLinker,
        private readonly OpenWaReactionHandler $reactionHandler = new OpenWaReactionHandler,
        private readonly OpenWaStatsProvider $statsProvider = new OpenWaStatsProvider,
    ) {}

    public function type(): ChannelType
    {
        return ChannelType::OpenWa;
    }

    public function name(): string
    {
        return 'WhatsApp (OpenWA)';
    }

    public function description(): string
    {
        return 'Canal de WhatsApp mediante OpenWA API. HMAC SHA-256, idempotencia, multi-sesión.';
    }

    public function icon(): string
    {
        return 'message-square';
    }

    public function accentColor(): string
    {
        return '#0066CC';
    }

    public function configFields(): array
    {
        return $this->configProvider->configFields();
    }

    public function settingsFields(): array
    {
        return $this->configProvider->settingsFields();
    }

    public function boot(Channel $channel): void {}

    public function shutdown(Channel $channel): void {}

    public function sendMessage(Conversation $conversation, Message $message): array
    {
        $validation = $this->sendValidator->validate($conversation, $message);
        if ($validation !== null) {
            return $validation;
        }

        $config = $conversation->channel->config ?? [];
        $sessionName = (string) ($config['session_name'] ?? '');

        $client = new OpenWaApiClient;
        if (! $client->isConfigured()) {
            return ['ok' => false, 'error' => 'OpenWA no está configurado en .env (OPENWA_BASE_URL / OPENWA_API_KEY).'];
        }

        $sessionId = $this->resolveSessionId($client, $sessionName);
        if (! $sessionId) {
            return ['ok' => false, 'error' => "La sesión '{$sessionName}' no existe en OpenWA."];
        }

        $params = $this->messageBuilder->buildParams($message, $conversation->external_id);

        $response = match ($message->type) {
            MessageType::Text => $client->sendText($sessionId, $params),
            MessageType::Image => $client->sendImage($sessionId, $params),
            MessageType::Video => $client->sendVideo($sessionId, $params),
            MessageType::Audio => $client->sendAudio($sessionId, $params),
            MessageType::File => $client->sendDocument($sessionId, $params),
            MessageType::Location => $client->sendLocation($sessionId, $params),
            MessageType::Contact => $client->sendContact($sessionId, $params),
            default => null,
        };

        if ($response === null) {
            return ['ok' => false, 'error' => "Tipo {$message->type->value} no soportado por OpenWaChannel."];
        }

        if ($response->successful()) {
            $body = $response->json();
            $providerId = is_array($body) ? ($body['data']['messageId'] ?? $body['messageId'] ?? null) : null;

            return ['ok' => true, 'provider_id' => $providerId, 'raw' => $body];
        }

        Log::warning('OpenWa sendMessage failed', [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id ?? null,
            'type' => $message->type->value,
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 500),
        ]);

        return ['ok' => false, 'error' => $response->body(), 'raw' => $response->json()];
    }

    public function processIncoming(array $payload, Channel $channel): ?Message
    {
        if (! OpenWaMessageParser::isMessageEvent($payload)) {
            return null;
        }

        $data = $payload['data'] ?? [];
        $fullId = $data['id'] ?? null;
        $chatId = OpenWaMessageParser::extractChatId($data);
        $isFromMe = OpenWaMessageParser::isFromMe($fullId);
        $waMessageId = OpenWaMessageParser::extractWaMessageId($fullId);

        if (! $chatId) {
            return null;
        }

        $conversation = Conversation::firstOrCreate(
            [
                'channel_id' => $channel->id,
                'external_id' => $chatId,
            ],
            [
                'visitor_name' => OpenWaMessageParser::extractPushName($data) ?? 'Visitante OpenWA',
                'visitor_email' => $chatId.'@openwa',
                'status' => ConversationStatus::Open,
                'last_message_at' => now(),
                'unread_by_admin' => $isFromMe ? 0 : 1,
            ]
        );

        if ($isFromMe) {
            $conversation->update([
                'last_message_at' => now(),
                'status' => ConversationStatus::Open,
            ]);

            if ($waMessageId && Message::withTrashed()->where('external_id', $waMessageId)->exists()) {
                return Message::withTrashed()->where('external_id', $waMessageId)->first();
            }

            $content = OpenWaMessageParser::extractContent($data);
            $type = OpenWaMessageParser::detectType($data);
            $mediaMeta = OpenWaMessageParser::extractMediaMeta($data);
            $quotedMsg = OpenWaMessageParser::extractQuotedMsg($data);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'role' => Message::ROLE_ADMIN,
                'type' => $type,
                'content' => $content,
                'external_id' => $waMessageId,
                'metadata' => array_merge(
                    [
                        'chatId' => $chatId,
                        'openwa_event' => $payload['event'] ?? null,
                        'fromWebhook' => true,
                    ],
                    $mediaMeta,
                    $quotedMsg ? ['quotedMsg' => $quotedMsg] : [],
                ),
            ]);

            Log::warning('OpenWaChannel: fromMe=true guardado como admin (echo)', [
                'message_id' => $message->id,
                'chat_id' => $chatId,
                'external_id' => $waMessageId,
            ]);

            $this->userLinker->linkOrCreate($conversation, $chatId, null);

            return $message;
        }

        if ($waMessageId && Message::withTrashed()->where('external_id', $waMessageId)->exists()) {
            return null;
        }

        $pushName = OpenWaMessageParser::extractPushName($data) ?? 'Visitante';
        $content = OpenWaMessageParser::extractContent($data);
        $type = OpenWaMessageParser::detectType($data);
        $mediaMeta = OpenWaMessageParser::extractMediaMeta($data);
        $quotedMsg = OpenWaMessageParser::extractQuotedMsg($data);

        if (! $conversation->wasRecentlyCreated) {
            $conversation->update([
                'last_message_at' => now(),
                'unread_by_admin' => $conversation->unread_by_admin + 1,
                'status' => ConversationStatus::Open,
            ]);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => Message::ROLE_USER,
            'type' => $type,
            'content' => $content,
            'external_id' => $waMessageId,
            'metadata' => array_merge(
                [
                    'pushName' => $pushName,
                    'chatId' => $chatId,
                    'openwa_event' => $payload['event'] ?? null,
                ],
                $mediaMeta,
                $quotedMsg ? ['quotedMsg' => $quotedMsg] : [],
            ),
        ]);

        $this->userLinker->linkOrCreate($conversation, $chatId, $pushName);

        if ($type !== MessageType::Text && $waMessageId) {
            $this->mediaEnricher->enrich($message, $channel);
        }

        return $message;
    }

    public function stats(Channel $channel): array
    {
        return $this->statsProvider->stats($channel);
    }

    private function resolveSessionId(OpenWaApiClient $client, string $sessionName): ?string
    {
        try {
            $response = $client->listSessions();
            if (! $response->successful()) {
                return null;
            }
            foreach (OpenWaApiClient::extractData($response) as $s) {
                if (($s['name'] ?? null) === $sessionName) {
                    return $s['id'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OpenWaChannel: resolveSessionId failed', [
                'session_name' => $sessionName,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
