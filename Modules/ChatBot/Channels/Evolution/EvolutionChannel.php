<?php

namespace Modules\ChatBot\Channels\Evolution;

use App\Jobs\FetchLinkPreviewsJob;
use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Channels\ChannelInterface;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Enums\ConversationStatus;
use Modules\ChatBot\Enums\MessageType;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

/**
 * Orquestador del canal Evolution (WhatsApp via Evolution API).
 *
 * Esta clase delega toda la lógica a componentes especializados:
 *  - EvolutionApiClient → cliente HTTP a Evolution
 *  - EvolutionMessageBuilder → construye params de envío
 *  - EvolutionSendValidator → valida antes de enviar
 *  - EvolutionMessageParser → parsing puro del payload (sin estado)
 *  - EvolutionMediaEnricher → descarga base64 desde Evolution
 *  - EvolutionUserLinker → asocia usuarios a conversaciones
 *  - EvolutionReactionHandler → maneja reacciones
 *  - EvolutionStatsProvider → estado de la conexión
 *  - EvolutionConfigProvider → config fields para el admin
 */
class EvolutionChannel implements ChannelInterface
{
    public function __construct(
        private readonly EvolutionConfigProvider $configProvider = new EvolutionConfigProvider,
        private readonly EvolutionMessageBuilder $messageBuilder = new EvolutionMessageBuilder,
        private readonly EvolutionSendValidator $sendValidator = new EvolutionSendValidator,
        private readonly EvolutionMediaEnricher $mediaEnricher = new EvolutionMediaEnricher,
        private readonly EvolutionUserLinker $userLinker = new EvolutionUserLinker,
        private readonly EvolutionReactionHandler $reactionHandler = new EvolutionReactionHandler,
        private readonly EvolutionStatsProvider $statsProvider = new EvolutionStatsProvider,
    ) {}

    public function type(): ChannelType
    {
        return ChannelType::Evolution;
    }

    public function name(): string
    {
        return 'WhatsApp (Evolution API)';
    }

    public function description(): string
    {
        return 'Canal de WhatsApp mediante Evolution API. Recibe y envía mensajes de WhatsApp.';
    }

    public function icon(): string
    {
        return 'message-circle';
    }

    public function accentColor(): string
    {
        return '#25D366';
    }

    public function configFields(): array
    {
        return $this->configProvider->configFields();
    }

    public function settingsFields(): array
    {
        return $this->configProvider->settingsFields();
    }

    public function boot(Channel $channel): void
    {
        // nothing to boot
    }

    public function shutdown(Channel $channel): void
    {
        // nothing to shutdown
    }

    public function sendMessage(Conversation $conversation, Message $message): array
    {
        $validation = $this->sendValidator->validate($conversation, $message);
        if ($validation !== null) {
            return $validation;
        }

        $config = $conversation->channel->config ?? [];
        $client = new EvolutionApiClient(
            serverUrl: rtrim($config['server_url'] ?? '', '/'),
            apiKey: $config['api_key'] ?? '',
            instanceName: $config['instance_name'] ?? '',
        );
        $number = $conversation->external_id;

        $params = $this->messageBuilder->buildParams($message, $number);

        $response = $message->type === MessageType::Text
            ? $client->sendText($params)
            : $client->sendMedia($params);

        if ($response->successful()) {
            $body = $response->json();

            return ['ok' => true, 'provider_id' => $body['key']['id'] ?? null, 'raw' => $body];
        }

        Log::warning('Evolution sendMessage failed', [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id ?? null,
            'type' => $message->type->value,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return ['ok' => false, 'error' => $response->body(), 'raw' => $response->json()];
    }

    public function processIncoming(array $payload, Channel $channel): ?Message
    {
        $event = $payload['event'] ?? '';

        if ($event === 'messages.reaction') {
            $this->reactionHandler->process($payload, $channel);

            return null;
        }

        if (! in_array($event, ['messages.upsert', 'messages.update', 'connection.update'], true)) {
            return null;
        }

        if ($event === 'connection.update') {
            return null;
        }

        $data = $payload['data'] ?? [];
        $key = $data['key'] ?? [];
        $remoteJid = $key['remoteJid'] ?? null;
        $fromMe = (bool) ($key['fromMe'] ?? false);
        $messageId = $key['id'] ?? null;

        if (! $remoteJid) {
            return null;
        }

        $conversation = Conversation::firstOrCreate(
            [
                'channel_id' => $channel->id,
                'external_id' => $remoteJid,
            ],
            [
                'visitor_name' => $data['pushName'] ?? 'Visitante',
                'visitor_email' => "{$remoteJid}@whatsapp",
                'status' => ConversationStatus::Open,
                'last_message_at' => now(),
                'unread_by_admin' => $fromMe ? 0 : 1,
            ]
        );

        if ($fromMe) {
            $conversation->update([
                'last_message_at' => now(),
                'status' => ConversationStatus::Open,
            ]);

            if ($messageId) {
                $existing = Message::withTrashed()->where('external_id', $messageId)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $messageData = $data['message'] ?? [];
            $unwrapped = EvolutionMessageParser::unwrapMessageData($messageData);
            $content = EvolutionMessageParser::extractContent($unwrapped);
            [$type, $mediaData, $mediaKind] = EvolutionMessageParser::detectType($unwrapped);
            $mediaMeta = match ($mediaKind) {
                'location' => EvolutionMessageParser::extractLocationMeta($mediaData),
                'contact' => EvolutionMessageParser::extractContactMeta($mediaData),
                default => $mediaData !== [] ? EvolutionMessageParser::extractMediaMeta($mediaKind, $mediaData) : [],
            };

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'role' => Message::ROLE_ADMIN,
                'type' => $type,
                'content' => $content,
                'external_id' => $messageId,
                'metadata' => array_merge(
                    ['remoteJid' => $remoteJid, 'source' => $data['source'] ?? null, 'fromWebhook' => true],
                    $mediaMeta,
                ),
            ]);

            $phonePart = explode('@', $remoteJid)[0] ?? null;
            $profileName = $this->fetchProfileName($channel, $phonePart);
            $this->userLinker->linkOrCreate($conversation, $remoteJid, $profileName, $phonePart);

            if ($type->isMediaDownloadable() && $messageId) {
                $this->mediaEnricher->enrich($message, $channel, $messageId);
            }

            $this->maybeDispatchLinkPreview($message);

            Log::warning('EvolutionChannel: fromMe=true guardado como admin (echo desde WhatsApp)', [
                'message_id' => $message->id,
                'remoteJid' => $remoteJid,
                'external_id' => $messageId,
                'profile_name' => $profileName,
            ]);

            return $message;
        }

        if ($messageId && Message::withTrashed()->where('external_id', $messageId)->exists()) {
            return null;
        }

        $pushName = $data['pushName'] ?? 'Visitante';
        $messageData = $data['message'] ?? [];

        $unwrapped = EvolutionMessageParser::unwrapMessageData($messageData);
        $content = EvolutionMessageParser::extractContent($unwrapped);
        [$type, $mediaData, $mediaKind] = EvolutionMessageParser::detectType($unwrapped);
        $mediaMeta = match ($mediaKind) {
            'location' => EvolutionMessageParser::extractLocationMeta($mediaData),
            'contact' => EvolutionMessageParser::extractContactMeta($mediaData),
            default => $mediaData !== [] ? EvolutionMessageParser::extractMediaMeta($mediaKind, $mediaData) : [],
        };

        $phonePart = explode('@', $remoteJid)[0] ?? null;

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
            'external_id' => $messageId,
            'metadata' => array_merge(
                ['pushName' => $pushName, 'remoteJid' => $remoteJid, 'source' => $data['source'] ?? null],
                $mediaMeta,
            ),
        ]);

        $this->userLinker->linkOrCreate($conversation, $remoteJid, $pushName, $phonePart);

        if ($type->isMediaDownloadable() && $messageId) {
            $this->mediaEnricher->enrich($message, $channel, $messageId);
        }

        $this->maybeDispatchLinkPreview($message);

        return $message;
    }

    public function stats(Channel $channel): array
    {
        return $this->statsProvider->stats($channel);
    }

    /**
     * Obtiene el nombre real del perfil de WhatsApp del destinatario
     * usando la instancia del webhook actual.
     */
    private function fetchProfileName(Channel $channel, ?string $phonePart): ?string
    {
        if (! $phonePart) {
            return null;
        }

        $serverUrl = (string) ($channel->config['server_url'] ?? '');
        $apiKey = (string) ($channel->config['api_key'] ?? '');
        $instanceName = (string) ($channel->config['instance_name'] ?? '');

        if ($serverUrl === '' || $apiKey === '' || $instanceName === '') {
            return null;
        }

        try {
            $client = new EvolutionApiClient(
                serverUrl: rtrim($serverUrl, '/'),
                apiKey: $apiKey,
                instanceName: $instanceName,
            );
            $response = $client->fetchProfile($phonePart);

            if (! $response->successful()) {
                return null;
            }

            $profile = $response->json();
            $name = $profile['name'] ?? $profile['pushName'] ?? $profile['verifiedName'] ?? null;

            return is_string($name) && trim($name) !== '' ? trim($name) : null;
        } catch (\Throwable $e) {
            Log::warning('EvolutionChannel: fetchProfile failed', [
                'channel_id' => $channel->id,
                'phone' => $phonePart,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Si el mensaje es de texto y contiene URLs, dispara el job de link preview.
     */
    private function maybeDispatchLinkPreview(Message $message): void
    {
        if ($message->type !== MessageType::Text) {
            return;
        }

        $content = (string) $message->content;
        if ($content === '' || ! preg_match('#https?://[^\s<>"\'\\)\]]+#i', $content)) {
            return;
        }

        try {
            FetchLinkPreviewsJob::dispatch([$message->id]);
        } catch (\Throwable $e) {
            Log::warning('EvolutionChannel: failed to dispatch FetchLinkPreviewsJob', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
