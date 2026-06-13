# OpenWA Message Formats — Internal Representation

This document covers the **internal** representation of messages in OpenWA, useful when reading source code, building reports, or designing integrations that need to round-trip messages.

---

## Database Schema

The `messages` table (TypeORM entity `src/modules/message/entities/message.entity.ts`):

```typescript
@Entity('messages')
@Index(['sessionId', 'createdAt'])
@Index(['chatId'])
export class Message {
  @PrimaryGeneratedColumn('uuid')
  id: string;                          // OpenWA internal UUID

  @Column()
  sessionId: string;                   // FK to sessions.id

  @Column({ nullable: true })
  waMessageId: string;                 // WhatsApp-native ID (data.id in webhook)

  @Column()
  chatId: string;                      // recipient or originating chat

  @Column()
  from: string;                        // sender (session phone for outgoing)

  @Column()
  to: string;                          // recipient

  @Column({ type: 'text', nullable: true })
  body: string;                        // text content OR caption OR filename

  @Column({ default: 'text' })
  type: string;                        // 'text' | 'image' | 'video' | 'audio' | 'document' | 'sticker' | 'location' | 'contact' | 'forward'

  @Column({ type: 'varchar', default: MessageDirection.OUTGOING })
  direction: MessageDirection;         // 'incoming' | 'outgoing'

  @Column({ type: 'bigint', nullable: true })
  timestamp: number;                   // Unix epoch seconds (waTimestamp)

  @Column({ type: 'json', nullable: true })
  metadata: Record<string, unknown>;   // engine-specific extras

  @Column({ type: 'varchar', default: MessageStatus.SENT })
  status: MessageStatus;               // 'pending' | 'sent' | 'delivered' | 'read' | 'failed'

  @CreateDateColumn()
  createdAt: Date;                     // DB row creation
}
```

> **Note:** OpenWA does NOT have an `updatedAt` on messages. Updates to `status` happen in-place (no DB trigger), so `createdAt` is the only timestamp for sort/audit.

---

## Enums

### `MessageDirection`

```typescript
enum MessageDirection {
  INCOMING = 'incoming',
  OUTGOING = 'outgoing',
}
```

**Rule of thumb:**
- `INCOMING` = received from WhatsApp (user sent from phone or another user in a group)
- `OUTGOING` = sent by this session (admin sent from dashboard, or `send-*` API call)

The `from` and `to` fields are mirrored for OUTGOING messages:
- `from = session.phone` (or 'me' if not yet known)
- `to = chatId`

For INCOMING:
- `from = sender chatId`
- `to = session phone chatId`

### `MessageStatus`

```typescript
enum MessageStatus {
  PENDING  = 'pending',    // saved before send, not yet confirmed
  SENT     = 'sent',       // WhatsApp accepted
  DELIVERED = 'delivered', // recipient's device received
  READ     = 'read',       // recipient opened chat
  FAILED   = 'failed',     // send failed
}
```

**Transition diagram:**
```
PENDING ──[send OK]──> SENT ──[ack 3]──> DELIVERED ──[ack 4]──> READ
   │
   └──[send fails]──> FAILED
```

**Mapping from `message.ack` events:**
| `ack` | `ackName`   | → `MessageStatus` |
|-------|-------------|-------------------|
| 0     | error       | `FAILED` (only if PENDING) |
| 1     | pending     | `PENDING`         |
| 2     | sent        | `SENT`            |
| 3     | delivered   | `DELIVERED`       |
| 4     | read        | `READ`            |
| 5     | played      | `READ` (audio only) |

> OpenWA does NOT have a separate `PLAYED` status. `ack=5` maps to `READ` for audio messages.

---

## Message ID Format

The `waMessageId` (and `data.id` in webhooks) follows the pattern:

```
{fromMe}_{chatId}_{whatsappNativeId}
```

| Part           | Value                                              |
|----------------|----------------------------------------------------|
| `fromMe`       | `true` for outgoing, `false` for incoming          |
| `chatId`       | The chat (e.g., `59169387181@c.us`)                |
| `whatsappNativeId` | WhatsApp's internal message ID (e.g., `3EB0ABC123`) |

**Examples:**
- `true_59169387181@c.us_3EB0ABC123` — outgoing message to user
- `false_59169387181@c.us_3EB0XYZ` — incoming message from user
- `true_120363012345678@g.us_3EB0...` — outgoing to group

**Parse in PHP:**
```php
[$fromMe, $chatId, $whatsappId] = explode('_', $messageId, 3);
$isFromMe = $fromMe === 'true';
```

**Parse in JavaScript:**
```js
const [fromMe, chatId, whatsappId] = messageId.split('_');
const isFromMe = fromMe === 'true';
```

---

## `chatId` Formats

| Type        | Format                  | Example                          |
|-------------|-------------------------|----------------------------------|
| Individual  | `{phone}@c.us`          | `59169387181@c.us`               |
| Group       | `{id}@g.us`             | `120363012345678@g.us`           |
| Channel     | `{id}@newsletter`       | `1234567890@newsletter`          |
| Status/Bcast| `status@broadcast`      | `status@broadcast`               |

**Country code** is always required, no `+` prefix.

**Phone normalization for OpenWA** (Laravel helper):
```php
function normalizeChatId(string $phone): string
{
    $phone = preg_replace('/\D+/', '', $phone);
    if (str_starts_with($phone, '0')) {
        $phone = substr($phone, 1);
    }
    // Add your default country code if missing
    if (strlen($phone) <= 10) {
        $phone = '591' . $phone;  // Bolivia
    }
    return $phone . '@c.us';
}
```

---

## The `metadata` Field

This is a free-form JSON column used by `whatsapp-web.js` to store engine-specific data not mapped to top-level fields. Common keys:

### For `type: 'chat'`

```json
{
  "metadata": {
    "quotedMsg": {                    // if this is a reply
      "id": "true_..._3EB0ABC",
      "body": "Original message",
      "type": "chat",
      "from": "591...@c.us"
    },
    "mentionedIds": ["591...@c.us"],
    "forwarded": true,                // if forwarded
    "forwardingScore": 2
  }
}
```

### For `type: 'image'` / `video` / `document` / `audio` / `sticker`

```json
{
  "metadata": {
    "mimetype": "image/jpeg",
    "fileLength": 123456,
    "width": 1024,
    "height": 768,
    "duration": 30,                   // for video/audio
    "gifPlayback": false,             // for video
    "ptt": true,                      // for audio (voice note)
    "filename": "report.pdf",         // for document
    "mediaKey": "...",                // encryption key
    "mediaUrl": "https://...",        // temporary CDN URL
    "mediaPath": "/data/media/..."    // local path if downloaded
  }
}
```

### For `type: 'location'`

```json
{
  "metadata": {
    "latitude": -17.7833,
    "longitude": -63.1821,
    "description": "Santa Cruz",
    "address": "..."
  }
}
```

### For `type: 'contact'`

```json
{
  "metadata": {
    "vcardList": [
      { "displayName": "John", "vcard": "BEGIN:VCARD..." }
    ]
  }
}
```

> **Best practice:** When integrating with OpenWA, use top-level fields (`body`, `chatId`, `from`, `to`) for the most common data, and read `metadata` for engine-specific extensions only.

---

## Webhook `data` vs DB Row

| Webhook `data` field | DB column           | Notes                                  |
|----------------------|---------------------|----------------------------------------|
| `id`                 | `waMessageId`       | Same value                             |
| `from`               | `from`              | Same value                             |
| `to`                 | `to`                | Same value                             |
| `body`               | `body`              | Same value                             |
| `type`               | `type`              | Same value                             |
| `waTimestamp`        | `timestamp`         | Same value (epoch seconds)             |
| `timestamp` (ISO)    | `createdAt`         | Different: ISO string vs DB Date       |
| `contact`            | —                   | Webhook only; not stored on Message    |
| `quotedMsg`          | `metadata.quotedMsg`| Webhook → top-level; DB → nested       |
| `forwarded`          | `metadata.forwarded`| Same                                   |
| `hasMedia`           | `metadata.*`        | Implied by `type` ∈ media types        |
| `mimetype`           | `metadata.mimetype` | Same                                   |
| `mediaUrl`           | `metadata.mediaUrl` | Same (download before it expires)      |
| `sessionId`          | `sessionId`         | Top-level in webhook, column in DB     |
| `idempotencyKey`     | —                   | Webhook only                           |
| `deliveryId`         | —                   | Webhook only                           |
| `event`              | —                   | Webhook only                           |

---

## Save-Before-Send Pattern

OpenWA's `MessageService` saves the message **before** calling `engine.sendTextMessage`:

```typescript
// MessageService.sendText (simplified)
async sendText(sessionId, dto) {
  // 1. Save with PENDING
  const message = await this.saveOutgoingMessage(sessionId, {
    chatId: dto.chatId, body: dto.text, type: 'text'
  });

  try {
    // 2. Call engine
    const result = await engine.sendTextMessage(dto.chatId, dto.text);

    // 3. Update on success
    message.waMessageId = result.id;
    message.status = MessageStatus.SENT;
    message.timestamp = result.timestamp;
    await this.messageRepository.save(message);
  } catch (error) {
    // 4. Mark as failed
    message.status = MessageStatus.FAILED;
    await this.messageRepository.save(message);
    throw error;
  }
}
```

**What this means for consumers:**
- If you receive a `message.sent` webhook, the local DB row is `status: SENT` with `waMessageId` set.
- If you receive a `message.ack` (delivered/read), update the local row's `status`.
- If you see a row stuck in `PENDING` for >30s with no `message.ack` event, the send likely failed silently — check OpenWA logs.

---

## Building Reports / Exports

To export a conversation to CSV/JSON:

```sql
SELECT
  id,
  waMessageId,
  chatId,
  from,
  to,
  body,
  type,
  direction,
  timestamp,
  status,
  json_extract(metadata, '$.mimetype') as mimetype,
  json_extract(metadata, '$.fileLength') as file_size,
  createdAt
FROM messages
WHERE sessionId = ? AND chatId = ?
ORDER BY timestamp ASC
```

For media, you'll need to join with your own `media` table that maps `waMessageId` → local file path (downloaded from `metadata.mediaUrl` on receipt).

---

## Type Discrimination

To detect message type programmatically (in webhook handler or DB read):

```php
$type = $message['type'] ?? $message['data']['type'] ?? 'chat';

$isMedia = in_array($type, ['image', 'video', 'audio', 'document', 'sticker', 'ptt']);
$isInteractive = in_array($type, ['buttons', 'list', 'template']);
$isLocation = $type === 'location';
$isContact = $type === 'vcard' || $type === 'contact';
$isText = $type === 'chat' || $type === 'text';
```

---

## Multi-Device Considerations

WhatsApp Multi-Device (introduced ~2021) means a session can be connected to multiple devices. The `Message` table does not distinguish which device received or sent the message — all messages go through the primary phone.

**If you need device-level tracking**, store `waMessageId` AND a custom `deviceId` in `metadata` based on the originating device (extract from the websocket source if you can, but this is engine-internal and not recommended).

---

## Common Pitfalls

1. **`body` is NOT always text** — for documents, `body` is the filename. For location, `body` is the description. For contact, `body` is `📇 Name`. Always check `type` first.

2. **`timestamp` is epoch seconds, not milliseconds** — different from JavaScript's `Date.now()`. Multiply by 1000 to convert.

3. **`createdAt` is set by the DB** — not by OpenWA. Don't trust it for the actual message time. Use `timestamp` (epoch seconds) for message ordering.

4. **No `updatedAt`** — if you want to track status changes, add your own trigger or poll column.

5. **The DB row UUID (`id`) is NOT in webhooks** — webhooks give you `waMessageId` (e.g., `true_..._3EB0ABC`). If you want to update a local row, look it up by `waMessageId`, not by `id`.

6. **`metadata` shape is not stable** — it reflects what `whatsapp-web.js` returns, which can change between versions. Treat as opaque; extract what you need and don't depend on structure.

7. **Quoted messages can be deep** — `metadata.quotedMsg.quotedMsg.quotedMsg...` (in theory infinite, in practice 1-2 levels). Handle recursively with a depth limit.

8. **For outgoing messages, `from = 'me'` if the session phone isn't known yet** — the engine sets this on first connection. Don't assume 'me' means anything special.

9. **Forwarded messages don't preserve original `from`** — the `from` is whoever forwarded it. Original sender info is in `metadata` (as `forwardedFrom` or similar, version-dependent).

10. **`type` is a string, not an enum in the DB** — older OpenWA versions had different values. When querying, use string literals with version checks.
