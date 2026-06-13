<?php

namespace Modules\ChatBot\Channels\Evolution;

use Illuminate\Support\Facades\Storage;
use Modules\ChatBot\Enums\MessageType;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

/**
 * Validaciones unificadas para el envío de mensajes a Evolution.
 * Centraliza TODAS las reglas de validación (config del canal, número destino,
 * tipo de mensaje, adjunto, límites de tamaño) en un solo lugar.
 */
class EvolutionSendValidator
{
    private const MAX_TEXT_LENGTH = 65000;

    private const MAX_BYTES_IMAGE = 16 * 1024 * 1024;

    private const MAX_BYTES_VIDEO = 64 * 1024 * 1024;

    private const MAX_BYTES_AUDIO = 16 * 1024 * 1024;

    private const MAX_BYTES_FILE = 100 * 1024 * 1024;

    private const MAX_BYTES_STICKER = 1 * 1024 * 1024;

    /**
     * Retorna `null` si todo OK, o un array `{ok: false, error: ...}` si falla.
     */
    public function validate(Conversation $conversation, Message $message): ?array
    {
        $config = $conversation->channel->config ?? [];

        $serverUrl = rtrim((string) ($config['server_url'] ?? ''), '/');
        $apiKey = (string) ($config['api_key'] ?? '');
        $instanceName = (string) ($config['instance_name'] ?? '');

        if ($serverUrl === '') {
            return ['ok' => false, 'error' => 'El canal no tiene configurado el "server_url" de Evolution.'];
        }
        if ($apiKey === '') {
            return ['ok' => false, 'error' => 'El canal no tiene configurado el "api_key" de Evolution.'];
        }
        if ($instanceName === '') {
            return ['ok' => false, 'error' => 'El canal no tiene configurado el "instance_name" de Evolution.'];
        }

        $number = $conversation->external_id;
        if (! $number || trim($number) === '') {
            return ['ok' => false, 'error' => 'La conversación no tiene un número de destino (external_id).'];
        }

        if ($message->type === MessageType::Text) {
            $text = trim((string) $message->content);
            if ($text === '') {
                return ['ok' => false, 'error' => 'El mensaje de texto está vacío.'];
            }
            if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
                return ['ok' => false, 'error' => 'El mensaje de texto excede el límite de 65.000 caracteres.'];
            }

            return null;
        }

        return $this->validateMedia($message);
    }

    private function validateMedia(Message $message): ?array
    {
        if (in_array($message->type, [MessageType::Location, MessageType::Contact], true)) {
            return $this->validateLocationOrContact($message);
        }

        if (! $message->attachment_media_id) {
            return ['ok' => false, 'error' => "El mensaje de tipo {$message->type->value} no tiene un archivo adjunto (attachment_media_id)."];
        }

        if (! $message->relationLoaded('attachment')) {
            $message->load('attachment');
        }

        $media = $message->attachment;
        if (! $media) {
            return ['ok' => false, 'error' => "El archivo adjunto (ID={$message->attachment_media_id}) no existe."];
        }

        $path = Storage::disk($media->disk)->path($media->path);
        if (! file_exists($path)) {
            return ['ok' => false, 'error' => "El archivo adjunto '{$media->path}' no existe en disco."];
        }

        $size = filesize($path) ?: 0;
        $maxSize = $this->maxSizeFor($message->type);
        if ($maxSize !== null && $size > $maxSize) {
            return ['ok' => false, 'error' => "El archivo adjunto excede el tamaño máximo permitido ({$maxSize} bytes) para el tipo {$message->type->value}."];
        }

        return null;
    }

    private function validateLocationOrContact(Message $message): ?array
    {
        $meta = $message->metadata ?? [];

        if ($message->type === MessageType::Location) {
            $lat = $meta['latitude'] ?? null;
            $lng = $meta['longitude'] ?? null;
            if (! is_numeric($lat) || ! is_numeric($lng)) {
                return ['ok' => false, 'error' => 'La ubicación requiere latitude y longitude numéricos en metadata.'];
            }
        }

        if ($message->type === MessageType::Contact) {
            $name = $meta['contact_name'] ?? null;
            $phone = $meta['contact_phone'] ?? null;
            if (! $name || ! $phone) {
                return ['ok' => false, 'error' => 'El contacto requiere contact_name y contact_phone en metadata.'];
            }
        }

        return null;
    }

    private function maxSizeFor(MessageType $type): ?int
    {
        return match ($type) {
            MessageType::Image => self::MAX_BYTES_IMAGE,
            MessageType::Video => self::MAX_BYTES_VIDEO,
            MessageType::Audio => self::MAX_BYTES_AUDIO,
            MessageType::File => self::MAX_BYTES_FILE,
            MessageType::Sticker => self::MAX_BYTES_STICKER,
            default => self::MAX_BYTES_IMAGE,
        };
    }
}
