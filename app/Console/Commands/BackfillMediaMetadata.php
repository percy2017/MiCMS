<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Channels\Evolution\EvolutionApiClient;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Models\Message;

#[Signature('chats:backfill-media {--conversation= : Backfill only a specific conversation id} {--message= : Backfill only a specific message id} {--limit=100 : Max messages to process}')]
#[Description('Re-descarga los media URLs de mensajes entrantes (image/video/audio/file) que no tienen attachment_media_id ni media_url en metadata.')]
class BackfillMediaMetadata extends Command
{
    public function handle(): int
    {
        $messageId = $this->option('message');
        $conversationId = $this->option('conversation');
        $limit = (int) $this->option('limit');

        $query = Message::query()
            ->with(['conversation.channel'])
            ->whereNull('attachment_media_id')
            ->whereIn('type', ['image', 'video', 'audio', 'file', 'sticker'])
            ->whereNotNull('external_id')
            ->whereNull('deleted_at')
            ->orderByDesc('id');

        if ($messageId) {
            $query->where('id', (int) $messageId);
        }
        if ($conversationId) {
            $query->where('conversation_id', (int) $conversationId);
        }

        $messages = $query->limit($limit)->get();

        if ($messages->isEmpty()) {
            $this->info('No hay mensajes que procesar.');

            return self::SUCCESS;
        }

        $this->info("Procesando {$messages->count()} mensajes...");

        $channelClients = [];
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($messages as $message) {
            $meta = $message->metadata ?? [];
            if (! empty($meta['media_base64'])) {
                $skipped++;

                continue;
            }

            $conversation = $message->conversation;
            $channel = $conversation?->channel;
            if (! $channel || $channel->type !== ChannelType::Evolution) {
                $skipped++;

                continue;
            }

            $client = $channelClients[$channel->id] ??= new EvolutionApiClient(
                serverUrl: rtrim($channel->config['server_url'] ?? '', '/'),
                apiKey: $channel->config['api_key'] ?? '',
                instanceName: $channel->config['instance_name'] ?? '',
            );

            $response = $client->getBase64FromMediaMessage($message->external_id);
            if (! $response->successful()) {
                $errors++;
                Log::warning('BackfillMedia: evolution no devolvió media', [
                    'message_id' => $message->id,
                    'status' => $response->status(),
                ]);
                $this->warn("Mensaje {$message->id}: HTTP {$response->status()}");

                continue;
            }

            $body = $response->json();
            $base64 = $body['base64'] ?? null;
            $mimetype = $body['mimetype'] ?? null;
            $fileName = $body['fileName'] ?? null;
            $size = $body['size']['fileLength']['low'] ?? null;
            $mediaType = $body['mediaType'] ?? null;

            if (! $base64) {
                $skipped++;

                continue;
            }

            $newMeta = array_merge($meta, [
                'media_kind' => $mediaType,
                'media_base64' => $base64,
                'media_mimetype' => $mimetype,
                'media_filename' => $fileName,
                'media_size' => is_int($size) ? $size : (is_numeric($size) ? (int) $size : null),
            ]);
            $message->forceFill(['metadata' => $newMeta])->save();
            $updated++;
            $this->line("Mensaje {$message->id}: OK ({$mimetype}, {$fileName})");
        }

        $this->info("Listo. updated={$updated}, skipped={$skipped}, errors={$errors}");

        return self::SUCCESS;
    }
}
