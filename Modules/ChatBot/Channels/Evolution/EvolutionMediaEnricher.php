<?php

namespace Modules\ChatBot\Channels\Evolution;

use App\Models\Media;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Log;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Message;

class EvolutionMediaEnricher
{
    public function __construct(
        private readonly MediaStorage $mediaStorage = new MediaStorage,
    ) {}

    /**
     * Descarga el media desde Evolution (base64), lo guarda en disco +
     * tabla `media` y setea `message.attachment_media_id`.
     *
     * Si falla (media expirado, instance offline, etc.), deja la media_url existente
     * en metadata como fallback.
     */
    public function enrich(Message $message, Channel $channel, string $messageId): void
    {
        try {
            $config = $channel->config ?? [];
            if (empty($config['server_url']) || empty($config['api_key']) || empty($config['instance_name'])) {
                Log::info('EvolutionMediaEnricher: skipped — channel config incomplete', [
                    'message_id' => $message->id,
                ]);

                return;
            }

            $client = new EvolutionApiClient(
                serverUrl: rtrim($config['server_url'], '/'),
                apiKey: $config['api_key'],
                instanceName: $config['instance_name'],
            );

            $response = $client->getBase64FromMediaMessage($messageId);
            if (! $response->successful()) {
                Log::info('EvolutionMediaEnricher: failed — non-2xx', [
                    'message_id' => $message->id,
                    'evolution_message_id' => $messageId,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                $message->forceFill([
                    'metadata' => array_merge($message->metadata ?? [], ['media_enrichment_failed_at' => now()->toIso8601String()]),
                ])->save();

                return;
            }

            $body = $response->json();
            $base64 = $body['base64'] ?? null;
            if (! $base64) {
                Log::info('EvolutionMediaEnricher: returned no base64', [
                    'message_id' => $message->id,
                    'evolution_message_id' => $messageId,
                    'body_keys' => is_array($body) ? array_keys($body) : null,
                ]);
                $message->forceFill([
                    'metadata' => array_merge($message->metadata ?? [], ['media_enrichment_failed_at' => now()->toIso8601String()]),
                ])->save();

                return;
            }

            $mimetype = $body['mimetype'] ?? ($message->metadata['media_mimetype'] ?? 'application/octet-stream');
            $originalName = $body['fileName'] ?? ($message->metadata['media_filename'] ?? "media-{$messageId}");

            $binary = base64_decode($base64, strict: true);
            if ($binary === false || $binary === '') {
                Log::warning('EvolutionMediaEnricher: base64 decode failed', [
                    'message_id' => $message->id,
                    'evolution_message_id' => $messageId,
                ]);

                return;
            }

            $stored = $this->mediaStorage->storeBytes($binary, $mimetype, $originalName);

            $media = Media::create([
                'disk' => $stored['path'] !== '' ? config('media.disk') : config('media.disk'),
                'path' => $stored['path'],
                'mime_type' => $stored['mime_type'],
                'size' => $stored['size'],
                'name' => $stored['name'],
                'user_id' => null,
            ]);

            $meta = $message->metadata ?? [];
            $meta['media_base64'] = $base64;
            $meta['media_mimetype'] = $mimetype;
            $meta['media_filename'] = $originalName;
            $meta['media_size'] = (int) $stored['size'];
            $meta['media_stored_at'] = now()->toIso8601String();
            unset($meta['media_enrichment_failed_at']);

            $message->forceFill([
                'attachment_media_id' => $media->id,
                'metadata' => $meta,
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('EvolutionMediaEnricher: exception', [
                'message_id' => $message->id,
                'evolution_message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
