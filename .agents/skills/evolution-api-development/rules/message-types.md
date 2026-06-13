# Evolution API v2 — Message Types Reference

All `messageType` values from Evolution API webhooks, their content extraction, and processing in this project.

---

## Supported Types (Processed by Controller)

| messageType | Enum | Content Placeholder | Metadata Keys | Downloadable |
|-------------|------|---------------------|---------------|--------------|
| `conversation` | `MessageType::Text` | `$messageData['conversation']` | (none) | ❌ |
| `extendedTextMessage` | `MessageType::Text` | `$messageData['extendedTextMessage']['text']` | `media_kind=link` + `media_preview` if URL | ❌ |
| `imageMessage` | `MessageType::Image` | caption or `[Imagen]` | `media_url`, `media_mimetype`, `media_caption`, `media_size`, `media_thumbnail` | ✅ |
| `videoMessage` | `MessageType::Video` | caption or `[Video]` | `media_url`, `media_mimetype`, `media_caption`, `media_size`, `media_duration` | ✅ |
| `ptvMessage` | `MessageType::Video` | caption or `[Video]` | (same as videoMessage) | ✅ |
| `audioMessage` | `MessageType::Audio` | `[Audio]` | `media_url`, `media_mimetype`, `media_duration`, `media_ptt` | ✅ |
| `documentMessage` | `MessageType::File` | caption or `[Documento]` | `media_url`, `media_mimetype`, `media_filename`, `media_size` | ✅ |
| `documentWithCaptionMessage` | `MessageType::File` | caption or `[Documento]` | (same as documentMessage) | ✅ |
| `stickerMessage` | `MessageType::Sticker` | `[Sticker]` | `media_url` (may be empty), `media_mimetype`, `media_filename` | ✅ |
| `lottieStickerMessage` | `MessageType::Sticker` | `[Sticker]` | (same as stickerMessage) | ✅ |
| `locationMessage` | `MessageType::Location` | `[Ubicación]` | `media_latitude`, `media_longitude`, `media_name`, `media_address`, `media_url`, `media_thumbnail` | ❌ |
| `liveLocationMessage` | `MessageType::Location` | `[Ubicación en vivo]` | (same as locationMessage) | ❌ |
| `contactMessage` | `MessageType::Contact` | `[Contacto] {displayName}` | `media_name`, `media_phone`, `media_vcard` | ❌ |
| `productMessage` | `MessageType::Text` | `[Producto: {title}]` | (none yet) | ❌ |
| `orderMessage` | `MessageType::Text` | `[Pedido: {orderTitle}]` | (none yet) | ❌ |
| `eventMessage` | `MessageType::Text` | `[Evento: {name}]` | (none yet) | ❌ |
| `pollCreationMessage` | `MessageType::Text` | `[Encuesta: {name}]` | (none yet) | ❌ |
| `reactionMessage` | N/A | `[Reacción: {text}]` or `[Reacción removida]` | (handled separately) | ❌ |

## Skipped Types (Explicitly Filtered)

| messageType | Reason |
|-------------|--------|
| `albumMessage` | Contains no usable content; individual messages arrive separately |
| `protocolMessage` | System protocol message, not user content |
| `groupStatusMentionMessage` | Status mention in group, no useful content |

---

## Content Extraction Patterns

### Text content
```php
// EvolutionMessageParser::extractContent()
return match (true) {
    ! empty($unwrapped['conversation']) => (string) $unwrapped['conversation'],
    ! empty($unwrapped['extendedTextMessage']['text']) => (string) $unwrapped['extendedTextMessage']['text'],
    // ...
    ! empty($unwrapped['contactMessage']['displayName']) => '[Contacto] '.$unwrapped['contactMessage']['displayName'],
    ! empty($unwrapped['locationMessage']) => '[Ubicación]',
    default => '[Mensaje no soportado]',
};
```

### Type detection
```php
// EvolutionMessageParser::detectType() returns [MessageType, mediaData, mediaKind]
if (! empty($unwrapped['imageMessage'])) {
    return [MessageType::Image, (array) $unwrapped['imageMessage'], 'image'];
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
```

### Media metadata extraction
```php
// EvolutionMessageParser::extractMediaMeta($kind, $mediaData) returns media_* prefixed array
$meta = [
    'media_kind' => $kind,  // 'image' | 'video' | 'audio' | 'document' | 'sticker'
    'media_url' => $mediaData['url'] ?? null,
    'media_mimetype' => $mediaData['mimetype'] ?? null,
    'media_caption' => $mediaData['caption'] ?? null,
    'media_size' => (int) $mediaData['fileLength'] ?? null,
];
```

### Location metadata extraction
```php
// EvolutionMessageParser::extractLocationMeta($locData)
$meta = [
    'media_kind' => 'location',
    'media_latitude' => (float) $locData['degreesLatitude'],
    'media_longitude' => (float) $locData['degreesLongitude'],
    'media_name' => $locData['name'] ?? null,
    'media_address' => $locData['address'] ?? null,
    'media_url' => $locData['url'] ?? null,  // Google Maps URL
    'media_thumbnail' => $locData['jpegThumbnail'] ?? null,  // base64
];
```

### Contact metadata extraction
```php
// EvolutionMessageParser::extractContactMeta($contactData)
$meta = [
    'media_kind' => 'contact',
    'media_name' => $contactData['displayName'] ?? null,
    'media_vcard' => $contactData['vcard'] ?? null,
];

// Extract phone from vcard (regex: TEL:...)
if (preg_match_all('/TEL[^:]*:([^\r\n]+)/i', $vcard, $matches)) {
    foreach ($matches[1] as $raw) {
        $phone = preg_replace('/[^+\d]/', '', $raw);
        if ($phone !== '' && strlen($phone) >= 8) {
            $meta['media_phone'] = $phone;
            break;
        }
    }
}
```

---

## Media Download Pattern

For `isMediaDownloadable()` types (image/video/audio/sticker/file):

1. **Parser** extracts metadata with `media_url`, `media_mimetype`, etc. from the webhook payload
2. **`EvolutionMediaEnricher::enrich()`** is called with the `messageId` (external_id):
   - Calls `Evolution API: /chat/getBase64FromMediaMessage/{instance}` to get base64
   - Decodes the base64
   - Saves to disk via `App\Support\MediaStorage::storeBytes()`
   - Creates a `Media` record
   - Sets `message.attachment_media_id = $media->id`
   - **ALSO persists `media_base64`, `media_mimetype`, `media_filename`, `media_size`, `media_stored_at` in metadata as fallback**

3. **Failure case**: if `getBase64FromMediaMessage` returns 400/500 or empty base64:
   - Sets `media_enrichment_failed_at` in metadata
   - Does NOT set `attachment_media_id`
   - Does NOT clear existing `media_url` (frontend can still use it temporarily)

## Important Notes on Sticker Messages

**Stickers often DON'T include `media_url` in the webhook payload.** The only way to get the actual sticker data is via `getBase64FromMediaMessage`. This is why `mediaEnricher->enrich()` is critical for stickers.

For expired sticker URLs (sent >5 min ago), `getBase64FromMediaMessage` returns HTTP 400. The sticker is lost. The user must resend it.

## Important Notes on Location Messages

The `locationMessage` payload includes:
- `degreesLatitude` (float) — required
- `degreesLongitude` (float) — required
- `name` (string, optional) — place name
- `address` (string, optional) — full address
- `url` (string, optional) — Google Maps link
- `jpegThumbnail` (string, optional) — base64 thumbnail of the map

If `name` and `address` are empty, the frontend shows "Ubicación compartida" + the coordinates + Google Maps link.

## Important Notes on Contact Messages

The `contactMessage` payload includes:
- `displayName` (string) — required
- `vcard` (string) — full vCard with phone, email, etc.
- The phone is **extracted from the vcard**, not from a top-level field.

The phone is stored in `media_phone` with E.164 format (e.g. `+59172811368`).
