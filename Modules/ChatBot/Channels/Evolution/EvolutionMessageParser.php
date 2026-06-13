<?php

namespace Modules\ChatBot\Channels\Evolution;

use Modules\ChatBot\Enums\MessageType;

/**
 * Funciones puras para parsear y normalizar payloads de Evolution API.
 * Sin estado, sin dependencias de modelos — fáciles de testear.
 */
class EvolutionMessageParser
{
    /**
     * Desenvuelve los wrappers de mensaje de WhatsApp (ephemeralMessage, viewOnceMessage*, etc.)
     * y devuelve el bloque de mensaje real.
     *
     * @param  array<string, mixed>  $messageData
     * @return array<string, mixed>
     */
    public static function unwrapMessageData(array $messageData): array
    {
        $wrappers = [
            'ephemeralMessage',
            'viewOnceMessage',
            'viewOnceMessageV2',
            'viewOnceMessageV2Extension',
            'documentWithCaptionMessage',
        ];

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

        return $messageData;
    }

    /**
     * Extrae el contenido textual del mensaje según el tipo de bloque.
     *
     * @param  array<string, mixed>  $unwrapped
     */
    public static function extractContent(array $unwrapped): string
    {
        return match (true) {
            ! empty($unwrapped['conversation']) => (string) $unwrapped['conversation'],
            ! empty($unwrapped['extendedTextMessage']['text']) => (string) $unwrapped['extendedTextMessage']['text'],
            ! empty($unwrapped['imageMessage']['caption']) => (string) $unwrapped['imageMessage']['caption'],
            ! empty($unwrapped['videoMessage']['caption']) => (string) $unwrapped['videoMessage']['caption'],
            ! empty($unwrapped['ptvMessage']['caption']) => (string) $unwrapped['ptvMessage']['caption'],
            ! empty($unwrapped['documentWithCaptionMessage']['caption']) => (string) $unwrapped['documentWithCaptionMessage']['caption'],
            ! empty($unwrapped['documentMessage']['caption']) => (string) $unwrapped['documentMessage']['caption'],
            ! empty($unwrapped['contactMessage']['displayName']) => '[Contacto] '.$unwrapped['contactMessage']['displayName'],
            ! empty($unwrapped['locationMessage']) => '[Ubicación]',
            ! empty($unwrapped['liveLocationMessage']) => '[Ubicación en vivo]',
            ! empty($unwrapped['imageMessage']) => '[Imagen]',
            ! empty($unwrapped['videoMessage']) => '[Video]',
            ! empty($unwrapped['ptvMessage']) => '[Video]',
            ! empty($unwrapped['audioMessage']) => '[Audio]',
            ! empty($unwrapped['documentMessage']) => '[Documento]',
            ! empty($unwrapped['documentWithCaptionMessage']) => '[Documento]',
            ! empty($unwrapped['stickerMessage']) => '[Sticker]',
            ! empty($unwrapped['lottieStickerMessage']) => '[Sticker]',
            ! empty($unwrapped['reactionMessage']['text']) => '[Reacción: '.$unwrapped['reactionMessage']['text'].']',
            ! empty($unwrapped['reactionMessage']) => '[Reacción removida]',
            ! empty($unwrapped['pollCreationMessage']['name']) => '[Encuesta: '.$unwrapped['pollCreationMessage']['name'].']',
            ! empty($unwrapped['pollCreationMessage']) => '[Encuesta]',
            ! empty($unwrapped['eventMessage']['name']) => '[Evento: '.$unwrapped['eventMessage']['name'].']',
            ! empty($unwrapped['eventMessage']) => '[Evento]',
            ! empty($unwrapped['orderMessage']['orderTitle']) => '[Pedido: '.$unwrapped['orderMessage']['orderTitle'].']',
            ! empty($unwrapped['orderMessage']) => '[Pedido]',
            ! empty($unwrapped['productMessage']['product']['title']) => '[Producto: '.$unwrapped['productMessage']['product']['title'].']',
            ! empty($unwrapped['productMessage']) => '[Producto]',
            default => '[Mensaje no soportado]',
        };
    }

    /**
     * Detecta el tipo de mensaje y devuelve [MessageType, mediaData, mediaKind].
     *
     * @param  array<string, mixed>  $unwrapped
     * @return array{0: MessageType, 1: array<string, mixed>, 2: ?string}
     */
    public static function detectType(array $unwrapped): array
    {
        if (! empty($unwrapped['imageMessage'])) {
            return [MessageType::Image, (array) $unwrapped['imageMessage'], 'image'];
        }
        if (! empty($unwrapped['videoMessage']) || ! empty($unwrapped['ptvMessage'])) {
            $videoData = $unwrapped['videoMessage'] ?? $unwrapped['ptvMessage'];

            return [MessageType::Video, (array) $videoData, 'video'];
        }
        if (! empty($unwrapped['audioMessage'])) {
            return [MessageType::Audio, (array) $unwrapped['audioMessage'], 'audio'];
        }
        if (! empty($unwrapped['documentMessage']) || ! empty($unwrapped['documentWithCaptionMessage'])) {
            $docData = $unwrapped['documentMessage'] ?? $unwrapped['documentWithCaptionMessage'];

            return [MessageType::File, (array) $docData, 'document'];
        }
        if (! empty($unwrapped['stickerMessage']) || ! empty($unwrapped['lottieStickerMessage'])) {
            $stickerData = $unwrapped['stickerMessage'] ?? $unwrapped['lottieStickerMessage'];

            return [MessageType::Sticker, (array) $stickerData, 'sticker'];
        }
        if (! empty($unwrapped['locationMessage']) || ! empty($unwrapped['liveLocationMessage'])) {
            $locData = $unwrapped['locationMessage'] ?? $unwrapped['liveLocationMessage'];

            return [MessageType::Location, (array) $locData, 'location'];
        }
        if (! empty($unwrapped['contactMessage'])) {
            return [MessageType::Contact, (array) $unwrapped['contactMessage'], 'contact'];
        }

        return [MessageType::Text, [], null];
    }

    /**
     * Extrae la metadata de media desde un bloque de mensaje de Evolution.
     *
     * @param  string  $kind  image|video|audio|document|sticker
     * @param  array<string, mixed>  $mediaData
     * @return array<string, mixed>
     */
    public static function extractMediaMeta(string $kind, array $mediaData): array
    {
        $meta = [
            'media_kind' => $kind,
        ];

        $url = $mediaData['url'] ?? $mediaData['mediaUrl'] ?? null;
        if (is_string($url) && $url !== '') {
            $meta['media_url'] = $url;
        }

        $mimetype = $mediaData['mimetype'] ?? $mediaData['mimeType'] ?? null;
        if (is_string($mimetype) && $mimetype !== '') {
            $meta['media_mimetype'] = $mimetype;
        }

        $fileName = $mediaData['fileName'] ?? $mediaData['filename'] ?? null;
        if (is_string($fileName) && $fileName !== '') {
            $meta['media_filename'] = $fileName;
        }

        $fileLength = $mediaData['fileLength']
            ?? $mediaData['fileSize']
            ?? $mediaData['size']
            ?? null;
        if (is_int($fileLength) || (is_string($fileLength) && ctype_digit($fileLength))) {
            $meta['media_size'] = (int) $fileLength;
        }

        $caption = $mediaData['caption'] ?? null;
        if (is_string($caption) && $caption !== '') {
            $meta['media_caption'] = $caption;
        }

        $base64 = $mediaData['base64'] ?? null;
        if (is_string($base64) && $base64 !== '') {
            $meta['media_base64'] = $base64;
        }

        $ptt = $mediaData['ptt'] ?? null;
        if (is_bool($ptt)) {
            $meta['media_ptt'] = $ptt;
        }

        $seconds = $mediaData['seconds'] ?? $mediaData['duration'] ?? null;
        if (is_int($seconds) || (is_string($seconds) && ctype_digit($seconds))) {
            $meta['media_duration'] = (int) $seconds;
        }

        return $meta;
    }

    /**
     * Extrae metadata de ubicación desde un bloque locationMessage o liveLocationMessage.
     *
     * @param  array<string, mixed>  $locData
     * @return array<string, mixed>
     */
    public static function extractLocationMeta(array $locData): array
    {
        $meta = [
            'media_kind' => 'location',
        ];

        $lat = $locData['degreesLatitude'] ?? null;
        $lng = $locData['degreesLongitude'] ?? null;

        if (is_numeric($lat) && is_numeric($lng)) {
            $meta['media_latitude'] = (float) $lat;
            $meta['media_longitude'] = (float) $lng;
        }

        $name = $locData['name'] ?? null;
        if (is_string($name) && $name !== '') {
            $meta['media_name'] = $name;
        }

        $address = $locData['address'] ?? null;
        if (is_string($address) && $address !== '') {
            $meta['media_address'] = $address;
        }

        $url = $locData['url'] ?? null;
        if (is_string($url) && $url !== '') {
            $meta['media_url'] = $url;
        }

        $thumbnail = $locData['jpegThumbnail'] ?? null;
        if (is_string($thumbnail) && $thumbnail !== '') {
            $meta['media_thumbnail'] = $thumbnail;
        }

        return $meta;
    }

    /**
     * Extrae metadata de contacto desde un bloque contactMessage.
     *
     * @param  array<string, mixed>  $contactData
     * @return array<string, mixed>
     */
    public static function extractContactMeta(array $contactData): array
    {
        $meta = [
            'media_kind' => 'contact',
        ];

        $displayName = $contactData['displayName'] ?? null;
        if (is_string($displayName) && $displayName !== '') {
            $meta['media_name'] = $displayName;
        }

        $vcard = $contactData['vcard'] ?? null;
        if (is_string($vcard) && $vcard !== '') {
            $meta['media_vcard'] = $vcard;
            if (preg_match_all('/TEL[^:]*:([^\r\n]+)/i', $vcard, $matches)) {
                foreach ($matches[1] as $raw) {
                    $phone = preg_replace('/[^+\d]/', '', $raw);
                    if ($phone !== '' && strlen($phone) >= 8) {
                        $meta['media_phone'] = $phone;
                        break;
                    }
                }
            }
        }

        return $meta;
    }
}
