<?php

namespace Modules\ChatBot\Channels;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Enums\ConversationStatus;
use Modules\ChatBot\Enums\MessageType;
use Modules\ChatBot\Events\ChatBotMessageReaction as ChatBotMessageReactionEvent;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;
use Modules\ChatBot\Models\MessageReaction;
use Spatie\Permission\Models\Role;

class EvolutionChannel implements ChannelInterface
{
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
        return [
            [
                'key' => 'server_url',
                'label' => 'URL del servidor Evolution',
                'type' => 'text',
                'required' => true,
                'help' => 'Ej: https://evolution.tudominio.com',
                'placeholder' => 'https://evolution.percyalvarez.lat',
            ],
            [
                'key' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'help' => 'API key de la instancia Evolution',
            ],
            [
                'key' => 'instance_name',
                'label' => 'Nombre de instancia',
                'type' => 'text',
                'required' => true,
                'help' => 'Usa "Listar instancias" para ver las disponibles',
            ],
        ];
    }

    public function settingsFields(): array
    {
        return [
            [
                'key' => 'display_name',
                'label' => 'Nombre mostrado',
                'type' => 'text',
                'required' => false,
                'help' => 'Nombre que verán los admins',
            ],
            [
                'key' => 'auto_reply',
                'label' => 'Respuesta automática',
                'type' => 'textarea',
                'required' => false,
                'help' => 'Mensaje automático al recibir mensaje nuevo',
            ],
        ];
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
        $config = $conversation->channel->config ?? [];
        $client = $this->buildClient($config);

        $number = $conversation->external_id;

        if (! $number) {
            return ['ok' => false, 'error' => 'No hay número de destino (external_id) en la conversación.'];
        }

        if ($message->type === MessageType::Text) {
            $response = $client->sendText([
                'number' => $number,
                'text' => $message->content,
            ]);
        } else {
            $response = $client->sendMedia([
                'number' => $number,
                'mediatype' => $this->mapMediaType($message->type),
                'mimetype' => $this->guessMime($message->type),
                'caption' => $message->content,
            ]);
        }

        if ($response->successful()) {
            $body = $response->json();

            return ['ok' => true, 'provider_id' => $body['key']['id'] ?? null, 'raw' => $body];
        }

        Log::warning('Evolution sendMessage failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return ['ok' => false, 'error' => $response->body(), 'raw' => $response->json()];
    }

    public function processIncoming(array $payload, Channel $channel): ?Message
    {
        $event = $payload['event'] ?? '';

        if ($event === 'messages.reaction') {
            $this->processReaction($payload, $channel);

            return null;
        }

        if (! in_array($event, ['messages.upsert', 'messages.update', 'connection.update'])) {
            return null;
        }

        if ($event === 'connection.update') {
            return null;
        }

        $data = $payload['data'] ?? [];
        $key = $data['key'] ?? [];
        $remoteJid = $key['remoteJid'] ?? null;
        $fromMe = $key['fromMe'] ?? false;

        if ($fromMe) {
            return null;
        }

        if (! $remoteJid) {
            return null;
        }

        $pushName = $data['pushName'] ?? 'Visitante';
        $messageData = $data['message'] ?? [];
        $messageType = $data['messageType'] ?? 'conversation';

        $messageId = $key['id'] ?? null;

        if ($messageId && Message::withTrashed()->where('external_id', $messageId)->exists()) {
            return null;
        }

        $unwrapped = $this->unwrapMessageData($messageData);

        $content = match (true) {
            ! empty($unwrapped['conversation']) => $unwrapped['conversation'],
            ! empty($unwrapped['extendedTextMessage']['text']) => $unwrapped['extendedTextMessage']['text'],
            ! empty($unwrapped['imageMessage']['caption']) => $unwrapped['imageMessage']['caption'],
            ! empty($unwrapped['videoMessage']['caption']) => $unwrapped['videoMessage']['caption'],
            ! empty($unwrapped['ptvMessage']['caption']) => $unwrapped['ptvMessage']['caption'],
            ! empty($unwrapped['documentWithCaptionMessage']['caption']) => $unwrapped['documentWithCaptionMessage']['caption'],
            ! empty($unwrapped['documentMessage']['caption']) => $unwrapped['documentMessage']['caption'],
            ! empty($unwrapped['contactMessage']['displayName']) => '[Contacto] '.$unwrapped['contactMessage']['displayName'],
            ! empty($unwrapped['locationMessage']) => '[Ubicación]',
            ! empty($unwrapped['liveLocationMessage']) => '[Ubicación en vivo]',
            ! empty($unwrapped['imageMessage']) => '[Imagen]',
            ! empty($unwrapped['videoMessage']) => '[Video]',
            ! empty($unwrapped['ptvMessage']) => '[Video]',
            ! empty($unwrapped['audioMessage']) => '[Audio]',
            ! empty($unwrapped['documentMessage']) => '[Documento]',
            ! empty($unwrapped['documentWithCaptionMessage']) => '[Documento]',
            ! empty($unwrapped['stickerMessage']) => '[Sticker]',
            ! empty($unwrapped['lottieStickerMessage']) => '[Sticker]',
            ! empty($unwrapped['reactionMessage']['text']) => '[Reacción: '.$unwrapped['reactionMessage']['text'].']',
            ! empty($unwrapped['reactionMessage']) => '[Reacción removida]',
            ! empty($unwrapped['pollCreationMessage']['name']) => '[Encuesta: '.$unwrapped['pollCreationMessage']['name'].']',
            ! empty($unwrapped['pollCreationMessage']) => '[Encuesta]',
            ! empty($unwrapped['eventMessage']['name']) => '[Evento: '.$unwrapped['eventMessage']['name'].']',
            ! empty($unwrapped['eventMessage']) => '[Evento]',
            ! empty($unwrapped['orderMessage']['orderTitle']) => '[Pedido: '.$unwrapped['orderMessage']['orderTitle'].']',
            ! empty($unwrapped['orderMessage']) => '[Pedido]',
            ! empty($unwrapped['productMessage']['product']['title']) => '[Producto: '.$unwrapped['productMessage']['product']['title'].']',
            ! empty($unwrapped['productMessage']) => '[Producto]',
            default => '[Mensaje no soportado]',
        };

        $type = MessageType::Text;
        $mediaMeta = [];
        if (! empty($unwrapped['imageMessage'])) {
            $type = MessageType::Image;
            $mediaMeta = $this->extractMediaMeta('image', $unwrapped['imageMessage']);
        } elseif (! empty($unwrapped['videoMessage']) || ! empty($unwrapped['ptvMessage'])) {
            $type = MessageType::Video;
            $videoData = $unwrapped['videoMessage'] ?? $unwrapped['ptvMessage'];
            $mediaMeta = $this->extractMediaMeta('video', $videoData);
        } elseif (! empty($unwrapped['audioMessage'])) {
            $type = MessageType::Audio;
            $mediaMeta = $this->extractMediaMeta('audio', $unwrapped['audioMessage']);
        } elseif (! empty($unwrapped['documentMessage']) || ! empty($unwrapped['documentWithCaptionMessage'])) {
            $type = MessageType::File;
            $docData = $unwrapped['documentMessage'] ?? $unwrapped['documentWithCaptionMessage'];
            $mediaMeta = $this->extractMediaMeta('document', $docData);
        } elseif (! empty($unwrapped['stickerMessage']) || ! empty($unwrapped['lottieStickerMessage'])) {
            $type = MessageType::Sticker;
            $stickerData = $unwrapped['stickerMessage'] ?? $unwrapped['lottieStickerMessage'];
            $mediaMeta = $this->extractMediaMeta('sticker', $stickerData);
        } elseif (! empty($unwrapped['locationMessage']) || ! empty($unwrapped['liveLocationMessage'])) {
            $type = MessageType::Location;
        } elseif (! empty($unwrapped['contactMessage'])) {
            $type = MessageType::Contact;
        }

        $phonePart = explode('@', $remoteJid)[0] ?? null;

        $conversation = Conversation::firstOrCreate(
            [
                'channel_id' => $channel->id,
                'external_id' => $remoteJid,
            ],
            [
                'visitor_name' => $pushName,
                'visitor_email' => "{$remoteJid}@whatsapp",
                'status' => ConversationStatus::Open,
                'last_message_at' => now(),
                'unread_by_admin' => 1,
            ]
        );

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

        $this->linkOrCreateUser($conversation, $remoteJid, $pushName, $phonePart);

        if ($type !== MessageType::Text && $messageId && ! empty($mediaMeta)) {
            $this->enrichWithBase64Media($message, $channel, $messageId);
        }

        return $message;
    }

    /**
     * Descarga el media desde Evolution (base64) y lo guarda en metadata.media_base64.
     * Si falla (media expirado, instance offline, etc.), deja la media_url existente.
     */
    private function enrichWithBase64Media(Message $message, Channel $channel, string $messageId): void
    {
        try {
            $config = $channel->config ?? [];
            if (empty($config['server_url']) || empty($config['api_key']) || empty($config['instance_name'])) {
                return;
            }

            $client = new EvolutionApiClient(
                serverUrl: rtrim($config['server_url'], '/'),
                apiKey: $config['api_key'],
                instanceName: $config['instance_name'],
            );

            $response = $client->getBase64FromMediaMessage($messageId);
            if (! $response->successful()) {
                Log::info('EvolutionChannel: media enrichment skipped', [
                    'message_id' => $message->id,
                    'status' => $response->status(),
                ]);

                return;
            }

            $body = $response->json();
            $base64 = $body['base64'] ?? null;
            if (! $base64) {
                return;
            }

            $meta = $message->metadata ?? [];
            $meta['media_base64'] = $base64;
            $meta['media_mimetype'] = $body['mimetype'] ?? ($meta['media_mimetype'] ?? null);
            $meta['media_filename'] = $body['fileName'] ?? ($meta['media_filename'] ?? null);
            $size = $body['size']['fileLength']['low'] ?? null;
            if (is_numeric($size)) {
                $meta['media_size'] = (int) $size;
            }

            $message->forceFill(['metadata' => $meta])->save();
        } catch (\Throwable $e) {
            Log::warning('EvolutionChannel: media enrichment failed', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Garantiza que la conversación tenga un User asociado.
     *
     * Reglas:
     *  - Si la conversación ya tiene user_id, no hace nada.
     *  - Busca primero por phone (sin el sufijo @s.whatsapp.net) y luego por whatsapp_jid.
     *  - Si no encuentra ninguno, AUTO-CREA el User usando los datos del webhook:
     *      name        = pushName (o "Visitante WhatsApp" como fallback)
     *      phone       = parte numérica del remoteJid
     *      whatsapp_jid = remoteJid completo
     *      email       = derivado del JID para mantener unicidad
     *      password    = aleatorio (el user no se loguea con esto)
     *      role        = "user" (rol por defecto; ver RoleSeeder)
     *  - Si encuentra un user existente, NUNCA sobrescribe su name.
     *  - Si encuentra un user por phone pero le falta whatsapp_jid, lo backfilea.
     */
    private function linkOrCreateUser(Conversation $conversation, ?string $remoteJid, ?string $pushName, ?string $phonePart): void
    {
        if ($conversation->user_id !== null) {
            return;
        }

        $user = null;

        if ($phonePart && $phonePart !== '') {
            $user = User::where('phone', $phonePart)->first();
        }

        if (! $user && $remoteJid) {
            $user = User::where('whatsapp_jid', $remoteJid)->first();
        }

        if (! $user) {
            $user = User::create([
                'name' => $pushName ?: 'Visitante WhatsApp',
                'email' => $remoteJid ? $remoteJid.'@whatsapp.user' : null,
                'phone' => $phonePart,
                'whatsapp_jid' => $remoteJid,
                'password' => Hash::make(Str::random(40)),
                'is_whatsapp_business' => false,
            ]);

            $defaultRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
            $user->assignRole($defaultRole);
        } else {
            $dirty = false;
            if ($remoteJid && empty($user->whatsapp_jid)) {
                $user->whatsapp_jid = $remoteJid;
                $dirty = true;
            }
            if ($dirty) {
                $user->save();
            }
        }

        $conversation->forceFill(['user_id' => $user->id])->save();
    }

    /**
     * Procesa un evento `messages.reaction` de Evolution API.
     *
     * Payload típico:
     *   {
     *     "event": "messages.reaction",
     *     "data": {
     *       "key": { "remoteJid": "...", "fromMe": false, "id": "MSG_ID" },
     *       "reaction": {
     *         "text": "❤️",                       // emoji (vacío = remoción)
     *         "key": { "id": "REACTION_ID", "remoteJid": "...", "fromMe": false }
     *       }
     *     }
     *   }
     *
     * @return array{action: string, message: ?Message, reaction: ?MessageReaction}
     */
    public function processReaction(array $payload, Channel $channel): array
    {
        $data = $payload['data'] ?? [];
        $key = $data['key'] ?? [];
        $reaction = $data['reaction'] ?? [];

        $messageId = $key['id'] ?? null;
        $emoji = $reaction['text'] ?? null;
        $reactionId = $reaction['key']['id'] ?? null;
        $fromMe = (bool) ($key['fromMe'] ?? $reaction['key']['fromMe'] ?? false);
        $remoteJid = $key['remoteJid'] ?? $reaction['key']['remoteJid'] ?? null;

        Log::info('EvolutionChannel reaction received', [
            'channel_id' => $channel->id,
            'message_id' => $messageId,
            'remote_jid' => $remoteJid,
            'emoji' => $emoji,
            'reaction_id' => $reactionId,
            'from_me' => $fromMe,
        ]);

        if (! $messageId || $remoteJid === null) {
            return ['action' => 'skipped', 'message' => null, 'reaction' => null];
        }

        $message = $this->findMessageByExternalId($messageId, $remoteJid, $channel->id);
        if (! $message) {
            Log::info('EvolutionChannel: reaction received for unknown message', [
                'message_id' => $messageId,
                'remote_jid' => $remoteJid,
                'channel_id' => $channel->id,
            ]);

            return ['action' => 'skipped', 'message' => null, 'reaction' => null];
        }

        $userJid = $fromMe ? 'admin-self' : $remoteJid;
        $action = 'added';

        if ($emoji === null || $emoji === '' || $emoji === false) {
            $deleted = $this->removeReaction($message, $userJid, $reactionId);
            if ($deleted > 0) {
                $this->broadcastReactionRemoved($message, $userJid, (string) ($reaction['previousText'] ?? ''));
            }

            return ['action' => $deleted > 0 ? 'removed' : 'skipped', 'message' => $message, 'reaction' => null];
        }

        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_jid', $userJid)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            return ['action' => 'exists', 'message' => $message, 'reaction' => $existing];
        }

        $model = MessageReaction::create([
            'message_id' => $message->id,
            'user_jid' => $userJid,
            'emoji' => $emoji,
            'external_id' => $reactionId,
        ]);

        ChatBotMessageReactionEvent::dispatch($message, $model, $action);

        return ['action' => $action, 'message' => $message, 'reaction' => $model];
    }

    /**
     * Busca el Message local por `external_id` (id de WhatsApp). Como el `external_id`
     * es único por instancia, se valida que pertenezca a una Conversation del mismo canal.
     */
    private function findMessageByExternalId(string $externalId, string $remoteJid, int $channelId): ?Message
    {
        $message = Message::withTrashed()
            ->where('external_id', $externalId)
            ->whereHas('conversation', function ($q) use ($channelId, $remoteJid) {
                $q->where('channel_id', $channelId)
                    ->where('external_id', $remoteJid);
            })
            ->first();

        if ($message) {
            return $message;
        }

        return Message::withTrashed()
            ->whereHas('conversation', function ($q) use ($channelId, $remoteJid) {
                $q->where('channel_id', $channelId)
                    ->where('external_id', $remoteJid);
            })
            ->where(function ($q) use ($externalId) {
                $q->where('metadata->wa_message_id', $externalId)
                    ->orWhere('metadata->reaction->id', $externalId);
            })
            ->first();
    }

    /**
     * Elimina reacciones de un mensaje según los criterios dados.
     *
     * @return int número de filas eliminadas
     */
    private function removeReaction(Message $message, string $userJid, ?string $reactionId): int
    {
        $query = MessageReaction::where('message_id', $message->id)->where('user_jid', $userJid);

        if ($reactionId) {
            $query->where(function ($q) use ($reactionId) {
                $q->where('external_id', $reactionId)->orWhereNull('external_id');
            });
        }

        return $query->delete();
    }

    private function broadcastReactionRemoved(Message $message, string $userJid, string $emoji): void
    {
        $placeholder = new MessageReaction([
            'message_id' => $message->id,
            'user_jid' => $userJid,
            'emoji' => $emoji,
        ]);
        $placeholder->id = 0;

        ChatBotMessageReactionEvent::dispatch($message, $placeholder, 'removed');
    }

    public function stats(Channel $channel): array
    {
        $config = $channel->config ?? [];

        if (empty($config['server_url']) || empty($config['api_key']) || empty($config['instance_name'])) {
            return [
                'connected' => false,
                'error' => 'Configuración incompleta',
                'instance' => $config['instance_name'] ?? '',
            ];
        }

        try {
            $client = $this->buildClient($config);
            $stateResponse = $client->connectionState();

            if ($stateResponse->successful()) {
                $state = $stateResponse->json();

                return [
                    'connected' => ($state['state'] ?? '') === 'open',
                    'state' => $state['state'] ?? 'unknown',
                    'instance' => $config['instance_name'] ?? '',
                    'qr_code' => $state['qrcode'] ?? null,
                ];
            }

            return [
                'connected' => false,
                'error' => 'Error al conectar: HTTP '.$stateResponse->status(),
                'instance' => $config['instance_name'] ?? '',
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'instance' => $config['instance_name'] ?? '',
            ];
        }
    }

    private function buildClient(array $config): EvolutionApiClient
    {
        return new EvolutionApiClient(
            serverUrl: rtrim($config['server_url'] ?? '', '/'),
            apiKey: $config['api_key'] ?? '',
            instanceName: $config['instance_name'] ?? '',
        );
    }

    private function mapMediaType(MessageType $type): string
    {
        return match ($type) {
            MessageType::Image => 'image',
            MessageType::Video => 'video',
            MessageType::Audio => 'audio',
            MessageType::File => 'document',
            MessageType::Sticker => 'sticker',
            default => 'document',
        };
    }

    private function guessMime(MessageType $type): string
    {
        return match ($type) {
            MessageType::Image => 'image/jpeg',
            MessageType::Video => 'video/mp4',
            MessageType::Audio => 'audio/mpeg',
            MessageType::File => 'application/octet-stream',
            MessageType::Sticker => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    /**
     * Desenvuelve los wrappers de mensaje de WhatsApp (ephemeralMessage, viewOnceMessage*, etc.)
     * y devuelve el bloque de mensaje real. Si el data ya es el bloque final, lo devuelve igual.
     *
     * @param  array<string, mixed>  $messageData
     * @return array<string, mixed>
     */
    private function unwrapMessageData(array $messageData): array
    {
        $wrappers = [
            'ephemeralMessage',
            'viewOnceMessage',
            'viewOnceMessageV2',
            'viewOnceMessageV2Extension',
            'documentWithCaptionMessage',
        ];

        for ($i = 0; $i < 5; $i++) {
            $unwrapped = false;
            foreach ($wrappers as $wrapper) {
                if (! empty($messageData[$wrapper]['message']) && is_array($messageData[$wrapper]['message'])) {
                    $messageData = $messageData[$wrapper]['message'];
                    $unwrapped = true;
                    break;
                }
            }
            if (! $unwrapped) {
                break;
            }
        }

        return $messageData;
    }

    /**
     * Extrae la metadata de media desde un bloque de mensaje de Evolution.
     * Devuelve un array con los campos relevantes que se persisten en `messages.metadata`.
     *
     * @param  string  $kind  image|video|audio|document|sticker
     * @param  array<string, mixed>  $mediaData
     * @return array<string, mixed>
     */
    private function extractMediaMeta(string $kind, array $mediaData): array
    {
        $meta = [
            'media_kind' => $kind,
        ];

        $url = $mediaData['url'] ?? $mediaData['mediaUrl'] ?? null;
        if (is_string($url) && $url !== '') {
            $meta['media_url'] = $url;
        }

        $mimetype = $mediaData['mimetype'] ?? $mediaData['mimeType'] ?? null;
        if (is_string($mimetype) && $mimetype !== '') {
            $meta['media_mimetype'] = $mimetype;
        }

        $fileName = $mediaData['fileName'] ?? $mediaData['filename'] ?? null;
        if (is_string($fileName) && $fileName !== '') {
            $meta['media_filename'] = $fileName;
        }

        $fileLength = $mediaData['fileLength']
            ?? $mediaData['fileSize']
            ?? $mediaData['size']
            ?? null;
        if (is_int($fileLength) || (is_string($fileLength) && ctype_digit($fileLength))) {
            $meta['media_size'] = (int) $fileLength;
        }

        $caption = $mediaData['caption'] ?? null;
        if (is_string($caption) && $caption !== '') {
            $meta['media_caption'] = $caption;
        }

        $base64 = $mediaData['base64'] ?? null;
        if (is_string($base64) && $base64 !== '') {
            $meta['media_base64'] = $base64;
        }

        $ptt = $mediaData['ptt'] ?? null;
        if (is_bool($ptt)) {
            $meta['media_ptt'] = $ptt;
        }

        $seconds = $mediaData['seconds'] ?? $mediaData['duration'] ?? null;
        if (is_int($seconds) || (is_string($seconds) && ctype_digit($seconds))) {
            $meta['media_duration'] = (int) $seconds;
        }

        return $meta;
    }
}
