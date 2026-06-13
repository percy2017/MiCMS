<?php

namespace Modules\ChatBot\Channels\OpenWa;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Message;

/**
 * Descarga el media desde la URL temporal de OpenWA/WhatsApp y lo guarda localmente.
 * Si falla (URL expirada, red caída), deja el media_url existente en metadata.
 */
class OpenWaMediaEnricher
{
    private const TIMEOUT_SECONDS = 30;

    public function enrich(Message $message, Channel $channel): void
    {
        $meta = $message->metadata ?? [];
        $mediaUrl = $meta['media_url'] ?? null;

        if (! $mediaUrl || ! is_string($mediaUrl) || $mediaUrl === '') {
            return;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => 'HostBol-ChatBot/1.0'])
                ->get($mediaUrl);

            if (! $response->successful()) {
                Log::warning('OpenWaMediaEnricher: download failed', [
                    'message_id' => $message->id,
                    'media_url' => $this->sanitizeUrl($mediaUrl),
                    'status' => $response->status(),
                ]);
                $this->markFailed($message);

                return;
            }

            $body = $response->body();
            if ($body === '' || $body === false) {
                Log::warning('OpenWaMediaEnricher: empty body', [
                    'message_id' => $message->id,
                ]);
                $this->markFailed($message);

                return;
            }

            $mimetype = $meta['media_mimetype'] ?? $response->header('Content-Type') ?? 'application/octet-stream';
            $extension = $this->extensionFor($mimetype);
            $filename = $meta['media_filename'] ?? "openwa-{$message->external_id}.{$extension}";

            $relativePath = "chatbot/openwa/{$channel->id}/".now()->format('Y/m/d')."/{$message->id}-{$filename}";

            $disk = config('chatbot.media_disk', 'public');
            Storage::disk($disk)->put($relativePath, $body);

            $meta['media_path'] = $relativePath;
            $meta['media_disk'] = $disk;
            $meta['media_mimetype'] = $mimetype;
            $meta['media_size'] = strlen($body);
            unset($meta['media_enrichment_failed_at']);

            $message->forceFill(['metadata' => $meta])->save();
        } catch (\Throwable $e) {
            Log::warning('OpenWaMediaEnricher: exception', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
            $this->markFailed($message);
        }
    }

    private function markFailed(Message $message): void
    {
        $meta = $message->metadata ?? [];
        $meta['media_enrichment_failed_at'] = now()->toIso8601String();
        $message->forceFill(['metadata' => $meta])->save();
    }

    private function extensionFor(string $mimetype): string
    {
        return match (strtolower(trim(explode(';', $mimetype)[0]))) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/ogg', 'audio/ogg; codecs=opus' => 'ogg',
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/mp4', 'audio/m4a' => 'm4a',
            'audio/amr' => 'amr',
            'audio/wav' => 'wav',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    private function sanitizeUrl(string $url): string
    {
        return strlen($url) > 200 ? substr($url, 0, 200).'...' : $url;
    }
}
