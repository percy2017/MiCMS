# OpenWA Message Types — Send & Receive

OpenWA supports all major WhatsApp message types. This document covers both sending (outbound) and receiving (inbound) for each, with payload examples, MIME types, size limits, and gotchas.

---

## Common Payload Patterns

### Outbound — Save-Before-Send

OpenWA uses a **save-before-send** pattern (see `MessageService.sendText`): the message is inserted with `status: PENDING` BEFORE calling the WhatsApp engine. On success, `status` updates to `SENT` and `waMessageId` is set. On failure, `status` updates to `FAILED`.

This means:
- If you have a local `Message` table, listen for `message.sent` to update from `PENDING` → `SENT`.
- Don't poll OpenWA for delivery status — wait for the `message.ack` event.
- The `messageId` in the send response is the `waMessageId`, not your local DB id.

### Inbound — `data.type` Values

| `data.type` | Description                                    |
|-------------|------------------------------------------------|
| `chat`      | Text message (also applies to extended text)   |
| `image`     | Image with optional caption                    |
| `video`     | Video with optional caption                    |
| `ptt`       | Voice note (push-to-talk)                      |
| `audio`     | Audio file                                     |
| `document`  | Any document (PDF, DOCX, etc.)                 |
| `sticker`   | WebP sticker                                   |
| `vcard`     | Contact card                                   |
| `location`  | Geo location                                   |
| `revoked`   | Message deleted                                |
| `ciphertext`| Undecryptable (rare, log and skip)             |

---

## 1. Text Messages

### Send

```http
POST /api/sessions/{id}/messages/send-text
{
  "chatId": "59169387181@c.us",
  "text": "Hello!",
  "options": {
    "mentionedIds": ["591...@c.us"],
    "quotedMessageId": "false_..._3EB0ABC"   // legacy field
  }
}
```

- Max length: **65,536 characters** (per spec; whatsapp-web.js may be lower in practice)
- Use `\n` for line breaks
- For better reply support, prefer `POST /messages/reply` (sets `quotedMsg`)

### Receive

```json
{
  "data": {
    "id": "false_59169387181@c.us_3EB0ABC",
    "from": "59169387181@c.us",
    "body": "Hello!",
    "type": "chat",
    "waTimestamp": 1718188800,
    "timestamp": "2026-06-12T10:00:00.000Z"
  }
}
```

`type` is `chat` for plain text, or could be a more specific sub-type for system messages (which are usually skipped).

---

## 2. Images

### Send

```http
POST /api/sessions/{id}/messages/send-image
{
  "chatId": "59169387181@c.us",
  "image": {
    "url": "https://example.com/photo.jpg"
  },
  "caption": "Check this out!"
}
```

Or with base64 (REQUIRED `mimetype`):
```json
{
  "chatId": "59169387181@c.us",
  "image": { "base64": "data:image/jpeg;base64,/9j/4AAQ..." },
  "mimetype": "image/jpeg",
  "caption": "..."
}
```

| Limit          | Value                           |
|----------------|---------------------------------|
| Max size       | **16 MB**                       |
| Formats        | JPEG, PNG, WebP, GIF            |
| Max resolution | 4096 × 4096 pixels              |
| Caption max    | 1,024 characters                |
| Recommended    | JPEG for photos, PNG for graphics |

### Receive

```json
{
  "data": {
    "id": "false_..._3EB0ABC",
    "from": "59169387181@c.us",
    "body": "Check this out!",
    "type": "image",
    "hasMedia": true,
    "mimetype": "image/jpeg",
    "mediaUrl": "https://mmg.whatsapp.net/v/t62.7117-24/...",
    "mediaKey": "...",
    "fileLength": 123456,
    "width": 1024,
    "height": 768
  }
}
```

> The `mediaUrl` is **temporary** (WhatsApp CDN, expires in minutes). Download immediately and persist. For long-lived storage, use OpenWA's `STORAGE_TYPE=s3` and copy the file via the engine's `downloadMediaMessage` API.

---

## 3. Videos

### Send

```http
POST /api/sessions/{id}/messages/send-video
{
  "chatId": "59169387181@c.us",
  "video": { "url": "https://example.com/clip.mp4" },
  "caption": "Watch this!",
  "gifPlayback": false
}
```

| Limit          | Value                                       |
|----------------|---------------------------------------------|
| Max size       | **64 MB** (WhatsApp limit: 16 MB in many regions) |
| Formats        | MP4, 3GP, AVI, MKV                          |
| Codec          | H.264 video + AAC audio                     |
| Caption max    | 1,024 characters                            |
| `gifPlayback`  | `true` to send as GIF (auto-converts MP4)   |

### Receive

Same as image, but `type: "video"`, `mimetype: "video/mp4"`, and additional fields:
- `duration` (seconds)
- `gifPlayback` (boolean)

---

## 4. Audio

### Send

```http
POST /api/sessions/{id}/messages/send-audio
{
  "chatId": "59169387181@c.us",
  "audio": { "url": "https://example.com/clip.mp3" },
  "ptt": true
}
```

| Field  | Type    | Description                              |
|--------|---------|------------------------------------------|
| `ptt`  | boolean | Send as voice note (push-to-talk, OGG Opus) |
| `mimetype` | string | Required if using base64. For PTT, use `audio/ogg; codecs=opus` |

| Limit          | Value                                  |
|----------------|----------------------------------------|
| Max size       | **16 MB**                              |
| Formats        | MP3, OGG, M4A, AMR, WAV                |
| PTT            | OGG Opus codec (auto-converted)        |
| Max duration   | ~15 minutes                            |

### Receive

- `type: "ptt"` for voice notes
- `type: "audio"` for regular audio
- `ptt: true` in `data` indicates voice note

---

## 5. Documents

### Send

```http
POST /api/sessions/{id}/messages/send-document
{
  "chatId": "59169387181@c.us",
  "document": { "url": "https://example.com/file.pdf" },
  "filename": "report.pdf",
  "caption": "Here is the report"
}
```

| Limit        | Value                                  |
|--------------|----------------------------------------|
| Max size     | **100 MB**                             |
| Formats      | PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, etc. |
| Filename max | 100 characters                         |
| Caption max  | 1,024 characters                       |

`filename` is **required** for documents.

### Receive

```json
{
  "data": {
    "type": "document",
    "body": "report.pdf",
    "mimetype": "application/pdf",
    "hasMedia": true,
    "fileLength": 1234567,
    "mediaUrl": "...",
    "filename": "report.pdf"
  }
}
```

`body` is the filename, not the file content.

---

## 6. Stickers

### Send

```http
POST /api/sessions/{id}/messages/send-sticker
{
  "chatId": "59169387181@c.us",
  "sticker": { "url": "https://example.com/sticker.webp" },
  "mimetype": "image/webp"
}
```

| Limit       | Value                                |
|-------------|--------------------------------------|
| Max size    | **500 KB**                           |
| Format      | WebP (animated WebP supported)       |
| Dimensions  | 512 × 512 pixels                     |

### Receive

```json
{
  "data": {
    "type": "sticker",
    "mimetype": "image/webp",
    "hasMedia": true,
    "mediaUrl": "..."
  }
}
```

---

## 7. Location

### Send

```http
POST /api/sessions/{id}/messages/send-location
{
  "chatId": "59169387181@c.us",
  "latitude": -17.7833,
  "longitude": -63.1821,
  "description": "Santa Cruz, Bolivia",
  "address": "Plaza Principal"
}
```

| Field         | Type   | Required | Description                |
|---------------|--------|----------|----------------------------|
| `latitude`    | number | Yes      | Decimal degrees            |
| `longitude`   | number | Yes      | Decimal degrees            |
| `description` | string | No       | Short label (e.g., name)   |
| `address`     | string | No       | Full address               |

### Receive

```json
{
  "data": {
    "type": "location",
    "latitude": -17.7833,
    "longitude": -63.1821,
    "description": "Santa Cruz, Bolivia"
  }
}
```

---

## 8. Contact Card (vCard)

### Send

```http
POST /api/sessions/{id}/messages/send-contact
{
  "chatId": "59169387181@c.us",
  "contact": {
    "name": "John Doe",
    "phone": "59169387555"
  }
}
```

| Field         | Type   | Required | Description                  |
|---------------|--------|----------|------------------------------|
| `contactName` | string | Yes      | Display name                 |
| `contactNumber` | string | Yes      | Phone with country code, no `+` |

### Receive

```json
{
  "data": {
    "type": "vcard",
    "body": "📇 John Doe",
    "vcardList": [
      { "displayName": "John Doe", "vcard": "BEGIN:VCARD\nVERSION:3.0\n..." }
    ]
  }
}
```

Use `vcardList[0].vcard` (full vCard string) for structured parsing.

---

## 9. Reply (Quote)

```http
POST /api/sessions/{id}/messages/reply
{
  "chatId": "59169387181@c.us",
  "quotedMessageId": "false_59169387181@c.us_3EB0ABC",
  "text": "Yes, that works for me!"
}
```

The `quotedMessageId` is the WhatsApp-native ID (e.g., `false_..._3EB0ABC`). The result includes the quote preview in WhatsApp.

---

## 10. Forward

```http
POST /api/sessions/{id}/messages/forward
{
  "fromChatId": "59169387181@c.us",
  "toChatId": "59169387555@c.us",
  "messageId": "false_..._3EB0ABC"
}
```

Forwarded messages arrive at the destination with `forwarded: true` and a chain of origin info.

---

## 11. Reactions

```http
POST /api/sessions/{id}/messages/react
{
  "chatId": "59169387181@c.us",
  "messageId": "false_..._3EB0ABC",
  "emoji": "❤️"
}
```

To **remove** a reaction, send the same call with `emoji: ""` (empty string).

**List reactions:**
```http
GET /api/sessions/{id}/messages/{chatId}/{messageId}/reactions
```

Returns:
```json
[
  { "emoji": "❤️", "sender": "59169387555@c.us", "timestamp": 1718188800 }
]
```

---

## 12. Delete Message

```http
POST /api/sessions/{id}/messages/delete
{
  "chatId": "59169387181@c.us",
  "messageId": "true_..._3EB0ABC",
  "forEveryone": true
}
```

- `forEveryone: true` → revoke (delete for everyone). Triggers `message.revoked` event.
- `forEveryone: false` → delete locally only.

**Permission:** You can only delete messages you sent (`forEveryone: true`). For received messages, only `forEveryone: false` works.

---

## 13. Bulk Messages (Async Batch)

See `api-endpoints.md §3.3` for the full request/response. Key points:

- Up to 100 messages per request
- Default 3s delay between messages (min 1s)
- Use `variables` for template substitution in text
- Track via `batchId` at `GET /messages/batch/{batchId}`
- Cancel via `POST /messages/batch/{batchId}/cancel`

```json
{
  "messages": [
    {
      "chatId": "591...@c.us",
      "type": "text",
      "content": { "text": "Hi {name}, welcome!" },
      "variables": { "name": "Pedro" }
    }
  ]
}
```

---

## 14. Catalog Products (Business)

```http
POST /api/sessions/{id}/messages/send-product
{
  "chatId": "59169387181@c.us",
  "productId": "1234567890"
}
```

Or send the full catalog:
```http
POST /api/sessions/{id}/messages/send-catalog
{
  "chatId": "59169387181@c.us"
}
```

**Requires** a WhatsApp Business account with a configured catalog.

---

## 15. Status (Stories)

```http
POST /api/sessions/{id}/status/send-text    { "text": "Hello!" }
POST /api/sessions/{id}/status/send-image   { "image": { "url": "..." }, "caption": "..." }
POST /api/sessions/{id}/status/send-video   { "video": { "url": "..." }, "caption": "..." }
```

Posts to your **own** status (stories). Visible to all contacts per your privacy settings.

---

## Quoted Message Detection in Webhooks

When a user **replies** to a message, the webhook `data` includes a `quotedMsg` object:

```json
{
  "data": {
    "id": "false_..._3EB0XYZ",
    "body": "Yes!",
    "type": "chat",
    "quotedMsg": {
      "id": "true_..._3EB0ABC",
      "body": "Are you coming?",
      "type": "chat",
      "from": "59169387555@c.us"
    }
  }
}
```

Use `quotedMsg.id` to link replies to original messages in your DB.

## Forwarded Message Flag

```json
{
  "data": {
    "id": "false_..._3EB0ABC",
    "body": "Interesting article",
    "forwarded": true,
    "forwardingScore": 2    // number of times forwarded
  }
}
```

## Mentions

```json
{
  "data": {
    "body": "Hey @Pedro check this!",
    "mentionedIds": ["59169387181@c.us"]
  }
}
```

---

## Media Size Validation

OpenWA returns these error codes for media issues:

| Code                          | HTTP | Cause                              |
|-------------------------------|------|------------------------------------|
| `MESSAGE_MEDIA_TOO_LARGE`     | 413  | Exceeds type-specific size limit   |
| `MESSAGE_MEDIA_DOWNLOAD_FAILED` | 400 | URL unreachable or timed out       |
| `MESSAGE_MEDIA_INVALID_FORMAT`| 400  | MIME type not in allowed list      |

For local files via `path:`, ensure the file is in `STORAGE_LOCAL_PATH` (default `./data/media`) or the engine won't have access.

---

## Best Practices

1. **Use URLs over base64** for media > 1MB. URL is downloaded once by OpenWA; base64 bloats the API request.
2. **Set `mimetype` even for URLs** to skip content-type detection and improve reliability.
3. **Reuse the same `mimetype` you receive** when replying with the same media type.
4. **Voice notes MUST be Opus** for `ptt: true`. MP3 voice notes will be auto-converted by whatsapp-web.js (slow, may fail).
5. **Document `filename` is required** — without it, WhatsApp shows "file" with no name.
6. **Stickers must be WebP**. PNG/JPG will be rejected with `MESSAGE_MEDIA_INVALID_FORMAT`.
7. **Image dimensions matter**: above 4096×4096 the send may fail silently. Resize client-side first.
