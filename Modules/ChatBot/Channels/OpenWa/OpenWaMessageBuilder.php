<?php

namespace Modules\ChatBot\Channels\OpenWa;

use Illuminate\Support\Facades\Storage;
use Modules\ChatBot\Enums\MessageType;
use Modules\ChatBot\Models\Message;

/**
 * Construye los parámetros para cada endpoint de envío de OpenWA.
 * OpenWA espera payloads distintos por tipo (send-text, send-image, send-video, etc.)
 */
class OpenWaMessageBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function buildParams(Message $message, string $chatId): array
    {
        return match ($message->type) {
            MessageType::Text => $this->buildTextParams($message, $chatId),
            MessageType::Sticker => $this->buildStickerParams($message, $chatId),
            MessageType::Location => $this->buildLocationParams($message, $chatId),
            MessageType::Contact => $this->buildContactParams($message, $chatId),
            MessageType::Image => $this->buildImageParams($message, $chatId),
            MessageType::Video => $this->buildVideoParams($message, $chatId),
            MessageType::Audio => $this->buildAudioParams($message, $chatId),
            MessageType::File => $this->buildDocumentParams($message, $chatId),
            default => throw new \InvalidArgumentException("OpenWaMessageBuilder: tipo {$message->type->value} no soportado"),
        };
    }

    /**
     * @return array{chatId: string, text: string}
     */
    private function buildTextParams(Message $message, string $chatId): array
    {
        return [
            'chatId' => $chatId,
            'text' => (string) $message->content,
        ];
    }

    /**
     * @return array{chatId: string, image: array{base64: string}, caption?: string, mimetype: string}
     */
    private function buildImageParams(Message $message, string $chatId): array
    {
        $media = $this->loadAttachment($message);
        $payload = [
            'chatId' => $chatId,
            'image' => ['base64' => $this->toBase64($media['path'])],
            'mimetype' => $media['mimetype'],
        ];

        if (trim((string) $message->content) !== '') {
            $payload['caption'] = $message->content;
        }

        return $payload;
    }

    /**
     * @return array{chatId: string, video: array{base64: string}, caption?: string, mimetype: string}
     */
    private function buildVideoParams(Message $message, string $chatId): array
    {
        $media = $this->loadAttachment($message);
        $payload = [
            'chatId' => $chatId,
            'video' => ['base64' => $this->toBase64($media['path'])],
            'mimetype' => $media['mimetype'],
        ];

        if (trim((string) $message->content) !== '') {
            $payload['caption'] = $message->content;
        }

        return $payload;
    }

    /**
     * @return array{chatId: string, audio: array{base64: string}, ptt: bool, mimetype: string}
     */
    private function buildAudioParams(Message $message, string $chatId): array
    {
        $media = $this->loadAttachment($message);
        $meta = $message->metadata ?? [];

        return [
            'chatId' => $chatId,
            'audio' => ['base64' => $this->toBase64($media['path'])],
            'ptt' => (bool) ($meta['ptt'] ?? false),
            'mimetype' => $media['mimetype'],
        ];
    }

    /**
     * @return array{chatId: string, document: array{base64: string}, filename: string, caption?: string, mimetype: string}
     */
    private function buildDocumentParams(Message $message, string $chatId): array
    {
        $media = $this->loadAttachment($message);
        $payload = [
            'chatId' => $chatId,
            'document' => ['base64' => $this->toBase64($media['path'])],
            'filename' => $media['name'] !== '' ? $media['name'] : 'documento.bin',
            'mimetype' => $media['mimetype'],
        ];

        if (trim((string) $message->content) !== '') {
            $payload['caption'] = $message->content;
        }

        return $payload;
    }

    /**
     * @return array{chatId: string, sticker: array{base64: string}, mimetype: string}
     */
    private function buildStickerParams(Message $message, string $chatId): array
    {
        $media = $this->loadAttachment($message);

        return [
            'chatId' => $chatId,
            'sticker' => ['base64' => $this->toBase64($media['path'])],
            'mimetype' => $media['mimetype'] !== 'application/octet-stream' ? $media['mimetype'] : 'image/webp',
        ];
    }

    /**
     * @return array{chatId: string, latitude: float, longitude: float, description?: string, address?: string}
     */
    private function buildLocationParams(Message $message, string $chatId): array
    {
        $meta = $message->metadata ?? [];

        return [
            'chatId' => $chatId,
            'latitude' => (float) $meta['latitude'],
            'longitude' => (float) $meta['longitude'],
            'description' => (string) ($meta['description'] ?? ''),
            'address' => (string) ($meta['address'] ?? ''),
        ];
    }

    /**
     * @return array{chatId: string, contact: array{name: string, phone: string}}
     */
    private function buildContactParams(Message $message, string $chatId): array
    {
        $meta = $message->metadata ?? [];

        return [
            'chatId' => $chatId,
            'contact' => [
                'name' => (string) ($meta['contact_name'] ?? 'Contacto'),
                'phone' => (string) ($meta['contact_phone'] ?? ''),
            ],
        ];
    }

    /**
     * @return array{path: string, mimetype: string, name: string}
     */
    private function loadAttachment(Message $message): array
    {
        if (! $message->relationLoaded('attachment')) {
            $message->load('attachment');
        }

        $media = $message->attachment;
        if (! $media) {
            throw new \RuntimeException("OpenWaMessageBuilder: mensaje {$message->id} no tiene attachment.");
        }

        return [
            'path' => Storage::disk($media->disk)->path($media->path),
            'mimetype' => $media->mime_type ?: 'application/octet-stream',
            'name' => $media->name ?? '',
        ];
    }

    private function toBase64(string $path): string
    {
        if (! file_exists($path)) {
            throw new \RuntimeException("OpenWaMessageBuilder: archivo no encontrado en {$path}.");
        }

        return base64_encode(file_get_contents($path));
    }
}
