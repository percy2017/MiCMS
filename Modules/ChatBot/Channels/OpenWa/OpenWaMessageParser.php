<?php

namespace Modules\ChatBot\Channels\OpenWa;

use Modules\ChatBot\Enums\MessageType;

/**
 * Funciones puras para parsear payloads de OpenWA webhooks.
 * Sin estado, sin dependencias de modelos — fáciles de testear.
 *
 * OpenWA payload shape (`data` field):
 *   {
 *     "id": "true|false_{chatId}_{waMessageId}",
 *     "from": "59169387181@c.us",
 *     "to": "59169387555@c.us",
 *     "body": "Hello!",
 *     "type": "chat" | "image" | "video" | "ptt" | "audio" | "document" | "sticker" | "vcard" | "location" | "revoked",
 *     "waTimestamp": 1718188800,
 *     "timestamp": "2026-06-12T10:00:00.000Z",
 *     "isGroup": false,
 *     "hasMedia": true,
 *     "mimetype": "image/jpeg",
 *     "mediaUrl": "https://...",
 *     "contact": { "name": "John", "pushName": "John" },
 *     "quotedMsg": { "id": "...", "body": "..." },
 *     "forwarded": false
 *   }
 */
class OpenWaMessageParser
{
    /**
     * Extrae el `chatId` (from) de un payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function extractChatId(array $data): ?string
    {
        return $data['from'] ?? $data['chatId'] ?? null;
    }

    /**
     * Extrae la parte numérica del chatId (sin el @c.us, @g.us, etc.)
     */
    public static function extractPhone(?string $chatId): ?string
    {
        if (! $chatId) {
            return null;
        }

        $phone = explode('@', $chatId)[0] ?? null;

        return $phone !== '' ? $phone : null;
    }

    /**
     * Devuelve el `waMessageId` puro (sin prefijo `true_/false_` ni `chatId`).
     *
     * Input: "false_59169387181@c.us_3EB0ABC123"
     * Output: "3EB0ABC123"
     */
    public static function extractWaMessageId(?string $fullId): ?string
    {
        if (! $fullId) {
            return null;
        }
        $parts = explode('_', $fullId, 3);

        return $parts[2] ?? $parts[1] ?? $fullId;
    }

    /**
     * Detecta si el mensaje es outgoing (fromMe) basado en el prefijo del id.
     */
    public static function isFromMe(?string $fullId): bool
    {
        if (! $fullId) {
            return false;
        }

        return str_starts_with($fullId, 'true_');
    }

    /**
     * Extrae el pushName del contacto (de `data.contact.pushName` o `data.contact.name`).
     *
     * @param  array<string, mixed>  $data
     */
    public static function extractPushName(array $data): ?string
    {
        $contact = $data['contact'] ?? null;
        if (is_array($contact)) {
            return $contact['pushName'] ?? $contact['name'] ?? null;
        }

        return null;
    }

    /**
     * Extrae el contenido textual del mensaje.
     * Para media, devuelve el caption (o el body si no hay caption).
     *
     * @param  array<string, mixed>  $data
     */
    public static function extractContent(array $data): string
    {
        $body = $data['body'] ?? null;

        if (is_string($body) && $body !== '') {
            return $body;
        }

        return match ($data['type'] ?? 'chat') {
            'chat' => '',
            'image' => '[Imagen]',
            'video' => '[Video]',
            'ptt', 'audio' => '[Audio]',
            'document' => '[Documento]',
            'sticker' => '[Sticker]',
            'vcard' => '[Contacto]',
            'location' => '[Ubicación]',
            'revoked' => '[Mensaje eliminado]',
            default => '[Mensaje no soportado]',
        };
    }

    /**
     * Detecta el MessageType a partir de `data.type`.
     *
     * @param  array<string, mixed>  $data
     */
    public static function detectType(array $data): MessageType
    {
        return match ($data['type'] ?? 'chat') {
            'image' => MessageType::Image,
            'video' => MessageType::Video,
            'ptt', 'audio' => MessageType::Audio,
            'document' => MessageType::File,
            'sticker' => MessageType::Sticker,
            'location' => MessageType::Location,
            'vcard' => MessageType::Contact,
            default => MessageType::Text,
        };
    }

    /**
     * Extrae la metadata de media de un payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function extractMediaMeta(array $data): array
    {
        $meta = [];

        if (isset($data['mimetype']) && is_string($data['mimetype'])) {
            $meta['media_mimetype'] = $data['mimetype'];
        }

        if (isset($data['mediaUrl']) && is_string($data['mediaUrl'])) {
            $meta['media_url'] = $data['mediaUrl'];
        }

        if (isset($data['fileLength'])) {
            $size = is_numeric($data['fileLength']) ? (int) $data['fileLength'] : null;
            if ($size !== null) {
                $meta['media_size'] = $size;
            }
        }

        if (isset($data['width']) && is_numeric($data['width'])) {
            $meta['media_width'] = (int) $data['width'];
        }

        if (isset($data['height']) && is_numeric($data['height'])) {
            $meta['media_height'] = (int) $data['height'];
        }

        if (isset($data['duration']) && is_numeric($data['duration'])) {
            $meta['media_duration'] = (int) $data['duration'];
        }

        if (isset($data['gifPlayback'])) {
            $meta['media_gif_playback'] = (bool) $data['gifPlayback'];
        }

        if (isset($data['ptt']) && is_bool($data['ptt'])) {
            $meta['media_ptt'] = $data['ptt'];
        }

        if (! empty($data['filename']) && is_string($data['filename'])) {
            $meta['media_filename'] = $data['filename'];
        }

        $type = $data['type'] ?? null;
        if (is_string($type)) {
            $meta['media_kind'] = $type;
        }

        return $meta;
    }

    /**
     * Extrae metadata de un quoted message (si existe).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public static function extractQuotedMsg(array $data): ?array
    {
        $quoted = $data['quotedMsg'] ?? null;
        if (! is_array($quoted) || $quoted === []) {
            return null;
        }

        return [
            'id' => $quoted['id'] ?? null,
            'body' => $quoted['body'] ?? null,
            'type' => $quoted['type'] ?? null,
            'from' => $quoted['from'] ?? null,
        ];
    }

    /**
     * Verifica si el payload representa un evento de mensaje procesable.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function isMessageEvent(array $payload): bool
    {
        $event = $payload['event'] ?? null;

        return in_array($event, [
            'message.received',
            'message.sent',
        ], true);
    }
}
