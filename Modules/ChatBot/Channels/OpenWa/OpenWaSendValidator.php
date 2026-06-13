<?php

namespace Modules\ChatBot\Channels\OpenWa;

use Illuminate\Support\Facades\Storage;
use Modules\ChatBot\Enums\MessageType;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

/**
 * Validaciones centralizadas para el envío de mensajes a OpenWA.
 * Credenciales globales se validan contra config('chatbot.openwa.*').
 */
class OpenWaSendValidator
{
    private const MAX_TEXT_LENGTH = 65536;

    private const MAX_BYTES_IMAGE = 16 * 1024 * 1024;

    private const MAX_BYTES_VIDEO = 64 * 1024 * 1024;

    private const MAX_BYTES_AUDIO = 16 * 1024 * 1024;

    private const MAX_BYTES_FILE = 100 * 1024 * 1024;

    private const MAX_BYTES_STICKER = 1 * 1024 * 1024;

    public function validate(Conversation $conversation, Message $message): ?array
    {
        $config = $conversation->channel->config ?? [];
        $sessionName = (string) ($config['session_name'] ?? '');

        if ($sessionName === '') {
            return ['ok' => false, 'error' => 'El canal no tiene configurado "session_name".'];
        }

        $client = new OpenWaApiClient;
        if (! $client->isConfigured()) {
            return ['ok' => false, 'error' => 'OpenWA no está configurado en .env (OPENWA_BASE_URL / OPENWA_API_KEY).'];
        }

        $chatId = $conversation->external_id;
        if (! $chatId || trim($chatId) === '') {
            return ['ok' => false, 'error' => 'La conversación no tiene un chatId de destino (external_id).'];
        }
        if (! str_contains($chatId, '@')) {
            return ['ok' => false, 'error' => "El chatId '{$chatId}' no tiene formato OpenWA válido."];
        }

        if ($message->type === MessageType::Text) {
            $text = trim((string) $message->content);
            if ($text === '') {
                return ['ok' => false, 'error' => 'El mensaje de texto está vacío.'];
            }
            if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
                return ['ok' => false, 'error' => 'El mensaje de texto excede el límite de 65.536 caracteres.'];
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
            return ['ok' => false, 'error' => "El mensaje de tipo {$message->type->value} no tiene un archivo adjunto."];
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
            $maxMb = round($maxSize / 1024 / 1024, 1);

            return ['ok' => false, 'error' => "El archivo adjunto excede el tamaño máximo ({$maxMb} MB) para {$message->type->value}."];
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
                return ['ok' => false, 'error' => 'La ubicación requiere latitude y longitude numéricos.'];
            }
        }

        if ($message->type === MessageType::Contact) {
            $name = $meta['contact_name'] ?? null;
            $phone = $meta['contact_phone'] ?? null;
            if (! $name || ! $phone) {
                return ['ok' => false, 'error' => 'El contacto requiere contact_name y contact_phone.'];
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
