<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Events\ChatBotMessageReaction as ChatBotMessageReactionEvent;
use Modules\ChatBot\Models\Message;
use Modules\ChatBot\Models\MessageReaction;

#[Signature('chats:refetch-unsupported {--conversation= : Only refetch for this conversation} {--message= : Only refetch this specific message} {--limit=50 : Max messages to process}')]
#[Description('Re-busca mensajes entrantes con contenido [Mensaje no soportado] desde Evolution API y los actualiza con el tipo real (reactionMessage, ephemeral, viewOnce, etc.).')]
class RefetchUnsupportedMessages extends Command
{
    public function handle(): int
    {
        $messageId = $this->option('message');
        $conversationId = $this->option('conversation');
        $limit = (int) $this->option('limit');

        $query = Message::query()
            ->with(['conversation.channel'])
            ->whereNull('deleted_at')
            ->whereNotNull('external_id')
            ->where(function ($q): void {
                $q->where('content', '[Mensaje no soportado]')
                    ->orWhere('content', 'LIKE', '[Mensaje no soportado]%');
            })
            ->orderByDesc('id');

        if ($messageId) {
            $query->where('id', (int) $messageId);
        }
        if ($conversationId) {
            $query->where('conversation_id', (int) $conversationId);
        }

        $messages = $query->limit($limit)->get();
        if ($messages->isEmpty()) {
            $this->info('No hay mensajes [Mensaje no soportado] para refetch.');

            return self::SUCCESS;
        }

        $this->info("Procesando {$messages->count()} mensajes...");
        $channelClients = [];
        $updated = 0;
        $errors = 0;

        foreach ($messages as $message) {
            $conversation = $message->conversation;
            $channel = $conversation?->channel;
            if (! $channel || $channel->type !== ChannelType::Evolution) {
                continue;
            }

            $client = $channelClients[$channel->id] ??= function () use ($channel) {
                $serverUrl = rtrim($channel->config['server_url'] ?? '', '/');
                $apiKey = $channel->config['api_key'] ?? '';

                return Http::withHeaders(['apikey' => $apiKey])->baseUrl($serverUrl);
            };

            $response = $client->post("/chat/findMessages/{$channel->config['instance_name']}", [
                'where' => [
                    'key' => ['id' => $message->external_id],
                ],
            ]);

            if (! $response->successful()) {
                $errors++;
                $this->warn("Mensaje {$message->id}: HTTP {$response->status()}");

                continue;
            }

            $record = $response->json('messages.records.0');
            if (! $record) {
                $errors++;
                $this->warn("Mensaje {$message->id}: sin record en Evolution");

                continue;
            }

            $result = $this->updateFromEvolutionRecord($message, $record);
            $this->line("Mensaje {$message->id}: {$result}");
            $updated++;
        }

        $this->info("Listo. updated={$updated}, errors={$errors}");

        return self::SUCCESS;
    }

    private function updateFromEvolutionRecord(Message $message, array $record): string
    {
        $messageType = $record['messageType'] ?? 'unknown';
        $messageData = $record['message'] ?? [];

        $wrappers = ['ephemeralMessage', 'viewOnceMessage', 'viewOnceMessageV2', 'viewOnceMessageV2Extension', 'documentWithCaptionMessage'];
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

        if ($messageType === 'reactionMessage' || ! empty($messageData['reactionMessage'])) {
            $reaction = $messageData['reactionMessage'] ?? [];
            $emoji = $reaction['text'] ?? null;
            $reactedToId = $reaction['key']['id'] ?? null;

            $meta = $message->metadata ?? [];
            $meta['is_reaction'] = true;
            $meta['reaction_emoji'] = $emoji;
            $meta['reacted_to_external_id'] = $reactedToId;

            $message->forceFill([
                'type' => 'text',
                'content' => $emoji !== null && $emoji !== '' ? $emoji : '[Reacción removida]',
                'metadata' => $meta,
            ])->save();

            if ($emoji && $reactedToId) {
                $target = Message::where('external_id', $reactedToId)->first();
                if ($target) {
                    $userJid = $message->metadata['remoteJid'] ?? 'unknown';
                    $existing = MessageReaction::where('message_id', $target->id)
                        ->where('user_jid', $userJid)
                        ->where('emoji', $emoji)
                        ->first();
                    if (! $existing) {
                        $reactionModel = MessageReaction::create([
                            'message_id' => $target->id,
                            'user_jid' => $userJid,
                            'emoji' => $emoji,
                            'external_id' => $message->external_id,
                        ]);
                        ChatBotMessageReactionEvent::dispatch($target, $reactionModel, 'added');
                    }
                }
            }

            return "OK reaction ({$emoji})";
        }

        $contentMap = [
            'imageMessage' => '[Imagen]',
            'videoMessage' => '[Video]',
            'ptvMessage' => '[Video]',
            'audioMessage' => '[Audio]',
            'documentMessage' => '[Documento]',
            'documentWithCaptionMessage' => '[Documento]',
            'stickerMessage' => '[Sticker]',
            'lottieStickerMessage' => '[Sticker]',
            'conversation' => null,
            'extendedTextMessage' => null,
        ];

        $newContent = '[Mensaje no soportado]';
        $newType = $message->type->value;

        if (isset($messageData['conversation'])) {
            $newContent = $messageData['conversation'];
            $newType = 'text';
        } elseif (isset($messageData['extendedTextMessage']['text'])) {
            $newContent = $messageData['extendedTextMessage']['text'];
            $newType = 'text';
        } elseif (isset($messageData['imageMessage'])) {
            $newContent = '[Imagen]';
            $newType = 'image';
        } elseif (isset($messageData['videoMessage']) || isset($messageData['ptvMessage'])) {
            $newContent = '[Video]';
            $newType = 'video';
        } elseif (isset($messageData['audioMessage'])) {
            $newContent = '[Audio]';
            $newType = 'audio';
        } elseif (isset($messageData['documentMessage']) || isset($messageData['documentWithCaptionMessage'])) {
            $newContent = '[Documento]';
            $newType = 'file';
        } elseif (isset($messageData['stickerMessage']) || isset($messageData['lottieStickerMessage'])) {
            $newContent = '[Sticker]';
            $newType = 'sticker';
        } elseif (isset($messageData['contactMessage'])) {
            $name = $messageData['contactMessage']['displayName'] ?? '';
            $newContent = '[Contacto] '.$name;
            $newType = 'contact';
        } elseif (isset($messageData['locationMessage'])) {
            $newContent = '[Ubicación]';
            $newType = 'location';
        } elseif (isset($messageData['pollCreationMessage'])) {
            $name = $messageData['pollCreationMessage']['name'] ?? '';
            $newContent = '[Encuesta: '.$name.']';
            $newType = 'text';
        } elseif (isset($messageData['eventMessage'])) {
            $name = $messageData['eventMessage']['name'] ?? '';
            $newContent = '[Evento: '.$name.']';
            $newType = 'text';
        }

        $message->forceFill([
            'type' => $newType,
            'content' => $newContent,
        ])->save();

        return "OK {$newType} ({$newContent})";
    }
}
