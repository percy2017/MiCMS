# Evolution API v2 — Message Types Reference

All `messageType` values from Evolution API webhooks, their content extraction, and processing in this project.

---

## Supported Types (Processed by Controller)

| messageType | Enum | Extracted Content | Status |
|-------------|------|-------------------|--------|
| `conversation` | `MessageType::Text` | `$messageData['conversation']` | ✅ |
| `extendedTextMessage` | `MessageType::Text` | `$messageData['extendedTextMessage']['text']` | ✅ |
| `imageMessage` | `MessageType::Image` | `$messageData['imageMessage']['caption']` or `[Imagen]` | ✅ |
| `videoMessage` | `MessageType::Video` | `$messageData['videoMessage']['caption']` or `[Video]` | ✅ |
| `audioMessage` | `MessageType::Audio` | `[Audio]` | ✅ |
| `documentMessage` | `MessageType::File` | `[Documento]` | ✅ |
| `stickerMessage` | `MessageType::Sticker` | `[Sticker]` | ✅ |
| `locationMessage` | `MessageType::Text` | lat/lng + Google Maps URL | ✅ |
| `contactMessage` | `MessageType::Text` | Contact name + vcard | ✅ |
| `productMessage` | `MessageType::Text` | Product title + price | ✅ |
| `reactionMessage` | N/A | Reaction to another message | ✅ (stored as metadata) |
| `callLog` | N/A | Call type + status + duration | ✅ (created internally) |

## Skipped Types (Explicitly Filtered)

| messageType | Reason |
|-------------|--------|
| `albumMessage` | Contains no usable content; individual messages arrive separately |
| `protocolMessage` | System protocol message, not user content |
| `groupStatusMentionMessage` | Status mention in group, no useful content |
| `associatedChildMessage` | Reference to another message |
| `encReactionMessage` | Encrypted reaction, unreadable |
| `unknown` | Unrecognized type |

---

## Content Extraction Logic

Current implementation in `EvolutionChannel::processIncoming()`:

```php
$content = match (true) {
    // Text messages
    ! empty($messageData['conversation']) => $messageData['conversation'],
    ! empty($messageData['extendedTextMessage']['text']) => $messageData['extendedTextMessage']['text'],

    // Media with caption
    ! empty($messageData['imageMessage']['caption']) => $messageData['imageMessage']['caption'],
    ! empty($messageData['videoMessage']['caption']) => $messageData['videoMessage']['caption'],

    // Media without caption
    ! empty($messageData['imageMessage']) => '[Imagen]',
    ! empty($messageData['videoMessage']) => '[Video]',
    ! empty($messageData['audioMessage']) => '[Audio]',
    ! empty($messageData['documentMessage']) => '[Documento]',
    ! empty($messageData['stickerMessage']) => '[Sticker]',

    // Default
    default => '[Mensaje no soportado]',
};
```

### Type Mapping

```php
$type = MessageType::Text;
if (! empty($messageData['imageMessage'])) {
    $type = MessageType::Image;
} elseif (! empty($messageData['videoMessage'])) {
    $type = MessageType::Video;
} elseif (! empty($messageData['audioMessage'])) {
    $type = MessageType::Audio;
} elseif (! empty($messageData['documentMessage'])) {
    $type = MessageType::File;
}
```

---

## Detailed Type Examples

### conversation (Plain Text)

```json
{
  "message": {
    "conversation": "Hello, how are you?"
  },
  "messageType": "conversation"
}
```

**Extraction:** `$messageData['conversation']`

---

### extendedTextMessage (Formatted Text)

```json
{
  "message": {
    "extendedTextMessage": {
      "text": "Hello *bold* and _italic_",
      "matchedText": "Hello *bold* and _italic_",
      "canonicalUrl": null,
      "description": null,
      "title": null,
      "previewType": 0
    }
  },
  "messageType": "extendedTextMessage"
}
```

**Extraction:** `$messageData['extendedTextMessage']['text']`

**Note:** WhatsApp formatting (`*bold*`, `_italic_`, `~strikethrough~`, `` `code` ``) is preserved in the text.

---

### imageMessage

```json
{
  "message": {
    "imageMessage": {
      "caption": "Check out this photo",
      "mimetype": "image/jpeg",
      "url": "https://mmg.whatsapp.net/v/t62.7161-24/...",
      "fileSha256": ["..."],
      "fileLength": { "low": 123456, "high": 0, "unsigned": true },
      "mediaKey": ["..."],
      "jpegThumbnail": ["..."]
    }
  },
  "messageType": "imageMessage"
}
```

**Extraction:** Caption if present, otherwise `[Imagen]`

**Note:** The `url` field is a temporary WhatsApp CDN URL. Download media promptly.

---

### videoMessage

```json
{
  "message": {
    "videoMessage": {
      "caption": "Look at this video",
      "mimetype": "video/mp4",
      "seconds": 15,
      "fileLength": { "low": 1048576, "high": 0, "unsigned": true }
    }
  },
  "messageType": "videoMessage"
}
```

**Extraction:** Caption if present, otherwise `[Video]`

---

### audioMessage

```json
{
  "message": {
    "audioMessage": {
      "mimetype": "audio/ogg; codecs=opus",
      "seconds": 5,
      "ptt": true
    }
  },
  "messageType": "audioMessage"
}
```

**Extraction:** Always `[Audio]`

**Note:** `ptt: true` means it's a voice note (push-to-talk).

---

### documentMessage

```json
{
  "message": {
    "documentMessage": {
      "fileName": "invoice.pdf",
      "mimetype": "application/pdf",
      "fileLength": { "low": 524288, "high": 0, "unsigned": true }
    }
  },
  "messageType": "documentMessage"
}
```

**Extraction:** Always `[Documento]`

---

### stickerMessage

```json
{
  "message": {
    "stickerMessage": {
      "mimetype": "image/webp",
      "isAnimated": false
    }
  },
  "messageType": "stickerMessage"
}
```

**Extraction:** Always `[Sticker]`

---

### locationMessage

```json
{
  "message": {
    "locationMessage": {
      "degreesLatitude": -16.5000,
      "degreesLongitude": -68.1500,
      "name": "Office",
      "address": "Av. 6 de Octubre, La Paz, Bolivia"
    }
  },
  "messageType": "locationMessage"
}
```

**Extraction (enhanced):**
```php
if (! empty($messageData['locationMessage'])) {
    $loc = $messageData['locationMessage'];
    $lat = $loc['degreesLatitude'] ?? 0;
    $lng = $loc['degreesLongitude'] ?? 0;
    $name = $loc['name'] ?? '';
    $address = $loc['address'] ?? '';

    $content = "📍 {$name}\n{$address}\nhttps://maps.google.com/?q={$lat},{$lng}";
    $type = MessageType::Text;
}
```

---

### contactMessage

```json
{
  "message": {
    "contactMessage": {
      "displayName": "John Doe",
      "vcard": "BEGIN:VCARD\nVERSION:3.0\nFN:John Doe\nTEL;type=CELL;waid=59170000000:+591 7000 0000\nEND:VCARD"
    }
  },
  "messageType": "contactMessage"
}
```

**Extraction (enhanced):**
```php
if (! empty($messageData['contactMessage'])) {
    $contact = $messageData['contactMessage'];
    $content = "👤 Contacto: {$contact['displayName']}";
    $type = MessageType::Contact;
}
```

---

### productMessage

```json
{
  "message": {
    "productMessage": {
      "product": {
        "title": "Product XYZ",
        "description": "Great product",
        "priceAmount1000": { "low": 50000, "high": 0, "unsigned": true },
        "currencyCode": "BOB"
      }
    }
  },
  "messageType": "productMessage"
}
```

**Extraction (enhanced):**
```php
if (! empty($messageData['productMessage'])) {
    $product = $messageData['productMessage']['product'] ?? [];
    $title = $product['title'] ?? 'Producto';
    $price = ($product['priceAmount1000']['low'] ?? 0) / 1000;
    $currency = $product['currencyCode'] ?? '';
    $content = "🛒 {$title} — {$price} {$currency}";
    $type = MessageType::Text;
}
```

---

### reactionMessage

```json
{
  "message": {
    "reactionMessage": {
      "text": "👍",
      "key": {
        "id": "ORIGINAL_MESSAGE_ID",
        "remoteJid": "59168964000@s.whatsapp.net",
        "fromMe": false
      }
    }
  },
  "messageType": "reactionMessage"
}
```

**Processing:** Store as metadata, not as a standalone message:

```php
if (! empty($messageData['reactionMessage'])) {
    $reaction = $messageData['reactionMessage'];
    // Find the original message by $reaction['key']['id']
    // Store reaction in metadata or as a separate reaction record
    return null; // Don't create a new message
}
```

---

### callLog (Internal)

Created internally by the controller when processing `call` webhook events:

```php
$content = "📞 Llamada de {$callType} {$label}";
// Example: "📞 Llamada de voice entrante — 05:30 min"
```

**Type:** Stored as `MessageType::Text` with a `callLog` marker in metadata.

---

## Adding New Message Types

### Step 1: Add extraction in processIncoming()

```php
// In the content match:
! empty($messageData['pollMessage']) => $messageData['pollMessage']['name'] ?? '[Encuesta]',

// In the type assignment:
} elseif (! empty($messageData['pollMessage'])) {
    $type = MessageType::Text;
}
```

### Step 2: Add enum value (if needed)

```php
// In Modules/ChatBot/Enums/MessageType.php
case Poll = 'poll';
case Story = 'story';
```

### Step 3: Add outbound support (if needed)

```php
// In EvolutionChannel::sendMessage():
MessageType::Poll => $client->sendPoll([
    'number' => $number,
    'name' => $message->content,
    'selectableCount' => 1,
    'values' => $message->metadata['options'] ?? [],
]),
```

### Step 4: Add factory state (for testing)

```php
// In Modules/ChatBot/database/factories/MessageFactory.php
public function poll(): static
{
    return $this->state(fn () => [
        'type' => MessageType::Poll,
        'content' => 'What is your favorite color?',
    ]);
}
```

---

## Media Handling Notes

1. **Temporary URLs** — WhatsApp media URLs (`mmg.whatsapp.net`) expire. Download and store media in your own storage.
2. **Base64 option** — Set `webhook_base64: true` in webhook config to receive media as base64 instead of URLs.
3. **MIME types** — Always use the `mimetype` field from the payload, don't guess.
4. **File size** — `fileLength` is an object `{low, high, unsigned}` — combine for the full size.
5. **Thumbnails** — `jpegThumbnail` in image messages is a base64-encoded thumbnail.
