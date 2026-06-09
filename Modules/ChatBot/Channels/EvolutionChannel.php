<?php

namespace Modules\ChatBot\Channels;

use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Enums\ConversationStatus;
use Modules\ChatBot\Enums\MessageType;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

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

        $content = match (true) {
            ! empty($messageData['conversation']) => $messageData['conversation'],
            ! empty($messageData['extendedTextMessage']['text']) => $messageData['extendedTextMessage']['text'],
            ! empty($messageData['imageMessage']['caption']) => $messageData['imageMessage']['caption'],
            ! empty($messageData['imageMessage']) => '[Imagen]',
            ! empty($messageData['videoMessage']) => '[Video]',
            ! empty($messageData['audioMessage']) => '[Audio]',
            ! empty($messageData['documentMessage']) => '[Documento]',
            ! empty($messageData['stickerMessage']) => '[Sticker]',
            default => '[Mensaje no soportado]',
        };

        $type = MessageType::Text;
        if (! empty($messageData['imageMessage'])) {
            $type = MessageType::Image;
        } elseif (! empty($messageData['videoMessage'])) {
            $type = MessageType::Video;
        } elseif (! empty($messageData['audioMessage'])) {
            $type = MessageType::Audio;
        } elseif (! empty($messageData['documentMessage'])) {
            $type = MessageType::File;
        }

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

        return Message::create([
            'conversation_id' => $conversation->id,
            'role' => Message::ROLE_USER,
            'type' => $type,
            'content' => $content,
            'external_id' => $key['id'] ?? null,
            'metadata' => ['pushName' => $pushName, 'remoteJid' => $remoteJid],
        ]);
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
}
