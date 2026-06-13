<?php

namespace Modules\ChatBot\Channels\Evolution;

use Illuminate\Support\Facades\Storage;
use Modules\ChatBot\Enums\MessageType;
use Modules\ChatBot\Models\Message;

/**
 * Construye los parámetros para cada endpoint de envío de Evolution API.
 */
class EvolutionMessageBuilder
{
    public function buildParams(Message $message, string $number): array
    {
        return match ($message->type) {
            MessageType::Text => $this->buildTextParams($message, $number),
            MessageType::Sticker => $this->buildStickerParams($message, $number),
            MessageType::Location => $this->buildLocationParams($message, $number),
            MessageType::Contact => $this->buildContactParams($message, $number),
            default => $this->buildMediaParams($message, $number),
        };
    }

    /**
     * @return array{number: string, text: string}
     */
    private function buildTextParams(Message $message, string $number): array
    {
        return [
            'number' => $number,
            'text' => $message->content,
        ];
    }

    /**
     * @return array{number: string, mediatype: string, mimetype: string, caption?: string, media: string, fileName?: string}
     */
    private function buildMediaParams(Message $message, string $number): array
    {
        $message->load('attachment');
        $media = $message->attachment;
        $path = Storage::disk($media->disk)->path($media->path);

        $params = [
            'number' => $number,
            'mediatype' => $this->mapMediaType($message->type),
            'mimetype' => $this->resolveMimeType($media->mime_type, $message->type),
            'media' => base64_encode(file_get_contents($path)),
        ];

        if (trim((string) $message->content) !== '') {
            $params['caption'] = $message->content;
        }

        if ($message->type === MessageType::File && ! empty($media->name)) {
            $params['fileName'] = $media->name;
        }

        return $params;
    }

    /**
     * @return array{number: string, sticker: string}
     */
    private function buildStickerParams(Message $message, string $number): array
    {
        $message->load('attachment');
        $path = Storage::disk($message->attachment->disk)->path($message->attachment->path);

        return [
            'number' => $number,
            'sticker' => base64_encode(file_get_contents($path)),
        ];
    }

    /**
     * @return array{number: string, name: string, address?: string, latitude: float, longitude: float}
     */
    private function buildLocationParams(Message $message, string $number): array
    {
        $meta = $message->metadata ?? [];

        return [
            'number' => $number,
            'name' => (string) ($meta['name'] ?? 'Ubicación'),
            'address' => (string) ($meta['address'] ?? ''),
            'latitude' => (float) $meta['latitude'],
            'longitude' => (float) $meta['longitude'],
        ];
    }

    /**
     * @return array{number: string, fullName: string, phoneNumber: string, organization?: string}
     */
    private function buildContactParams(Message $message, string $number): array
    {
        $meta = $message->metadata ?? [];

        return [
            'number' => $number,
            'fullName' => (string) $meta['contact_name'],
            'phoneNumber' => (string) $meta['contact_phone'],
            'organization' => (string) ($meta['organization'] ?? ''),
        ];
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

    private function resolveMimeType(?string $storedMime, MessageType $type): string
    {
        if ($storedMime && $storedMime !== '' && $storedMime !== 'application/octet-stream') {
            return $storedMime;
        }

        return $this->guessMime($type);
    }
}
