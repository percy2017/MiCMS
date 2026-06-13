# OpenWA Webhook Events — Complete Reference

OpenWA webhooks are HTTP POST requests sent to a user-configured URL. They are dispatched in real-time as events happen in the underlying `whatsapp-web.js` engine. The dispatcher (`src/modules/webhook/webhook.service.ts`) supports BullMQ-based retry (when Redis is enabled) or direct delivery with exponential backoff.

---

## Delivery Mechanism

OpenWA has **two delivery modes** (configurable per-webhook via `retryCount`):

1. **Queue mode** (when `REDIS_ENABLED=true` and `queue.enabled=true`): event is enqueued to BullMQ. Retries use exponential backoff, up to `retryCount` attempts. Delivery runs in a separate worker process.
2. **Direct mode** (default, no Redis): synchronous HTTP POST with exponential backoff (`WEBHOOK_RETRY_DELAY` ms × attempt number).

In both modes, a successful delivery is one where the consumer responds `2xx` within `WEBHOOK_TIMEOUT` (default 10000ms).

---

## Request Headers

Every webhook delivery includes these headers (case-insensitive):

| Header                       | Description                                            |
|------------------------------|--------------------------------------------------------|
| `Content-Type`               | `application/json`                                     |
| `User-Agent`                 | `OpenWA-Webhook/1.0.0`                                 |
| `X-OpenWA-Event`             | The event type (e.g., `message.received`)              |
| `X-OpenWA-Idempotency-Key`   | Deterministic content-based key (see below)            |
| `X-OpenWA-Delivery-Id`       | Unique per attempt (changes on retry)                  |
| `X-OpenWA-Retry-Count`       | Attempt number, `0` = first attempt                    |
| `X-OpenWA-Signature`         | `sha256=<hex>` HMAC of raw body (only if `secret` set) |
| Custom headers               | Any extra headers configured on the webhook            |

---

## Payload Structure

```json
{
  "event": "message.received",
  "timestamp": "2026-06-12T10:00:00.000Z",
  "sessionId": "sess_abc123",
  "deliveryId": "dlv_550e8400e29b41d4a716446655440000",
  "idempotencyKey": "msg_false_59169387181@c.us_3EB0ABC_1718188800",
  "data": { /* event-specific */ }
}
```

| Field            | Description                                                       |
|------------------|-------------------------------------------------------------------|
| `event`          | Event type, e.g., `message.received`                              |
| `timestamp`      | ISO 8601 UTC, when the event was generated                        |
| `sessionId`      | OpenWA session ID that produced the event                         |
| `deliveryId`     | Unique per attempt; safe to log but NOT for idempotency          |
| `idempotencyKey` | Deterministic; same across retries (USE FOR DEDUP)                |
| `data`           | Event-specific payload (see each event below)                     |

> The `idempotencyKey` does **not** include `deliveryId` or `timestamp`, only content fields. This means retrying an event produces the **same** `idempotencyKey`. Use that to dedupe on the consumer side.

---

## Signature Verification (HMAC SHA-256)

If the webhook is created with a `secret`, OpenWA computes:

```
signature = "sha256=" + HMAC_SHA256(secret, rawRequestBody).hex()
```

Sent in `X-OpenWA-Signature` header.

**Verification (Node.js):**
```js
const crypto = require('crypto');

function verify(req, secret) {
  const sig = req.headers['x-openwa-signature'];
  const body = req.rawBody;   // MUST be the raw, unparsed body
  const expected = 'sha256=' + crypto.createHmac('sha256', secret).update(body).digest('hex');
  return crypto.timingSafeEqual(Buffer.from(sig), Buffer.from(expected));
}
```

**Verification (Laravel):**
```php
$signature = $request->header('X-OpenWA-Signature');
$rawBody = $request->getContent();
$expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
return hash_equals($expected, $signature);
```

**Common bug:** verify on `$request->all()` (parsed array → re-serialized) instead of the raw body. This fails because the JSON serialization is not bit-identical. **Always use the raw body.**

---

## Idempotency Implementation

The `idempotencyKey` is **content-based** (deterministic), introduced in `v0.1.1`. Before that, it was random and could not dedupe.

**Format:**
```
{eventType}_{uniqueIdentifier}_{timestamp}
```

**Examples:**
- `message.received`: `msg_{messageId}_{sessionId}` or `msg_{messageId}_{timestamp}` (depends on `generateIdempotencyKey` impl in `webhook/utils/idempotency.util.ts`)
- `message.sent`: `msg_{messageId}_{sessionId}`
- `message.ack`: `ack_{messageId}_{ackLevel}_{sessionId}`
- `session.status`: `sess_{sessionId}_{status}_{sessionId}`
- `group.join`: `grp_{groupId}_{participantId}_{sessionId}`

> The exact key generation logic is in `src/modules/webhook/utils/idempotency.util.ts`. Check the source for your version.

**Recommended consumer-side pattern:**
```php
// Laravel example
$key = $request->header('X-OpenWA-Idempotency-Key');
if (cache()->has("webhook:{$key}")) {
    return response()->json(['status' => 'duplicate_ignored']);
}

$lock = cache()->lock("webhook:{$key}", 300);
if (!$lock->get()) return response()->json(['status' => 'in_progress'], 423);

try {
    processEvent($request->all());
    cache()->put("webhook:{$key}", true, now()->addDay());
} finally {
    $lock->release();
}
```

For production, persist to DB:
```sql
CREATE TABLE webhook_idempotency (
  idempotency_key VARCHAR(255) PRIMARY KEY,
  processed_at TIMESTAMP DEFAULT NOW()
);
```

---

## Event Catalog

### 1. `message.received` — New incoming message

```json
{
  "event": "message.received",
  "timestamp": "2026-06-12T10:00:00.000Z",
  "sessionId": "sess_abc123",
  "idempotencyKey": "msg_...",
  "deliveryId": "dlv_...",
  "data": {
    "id": "false_59169387181@c.us_3EB0ABC123",
    "from": "59169387181@c.us",
    "to": "59169387555@c.us",
    "body": "Hola!",
    "type": "chat",
    "waTimestamp": 1718188800,
    "timestamp": "2026-06-12T10:00:00.000Z",
    "isGroup": false,
    "hasMedia": false,
    "contact": {
      "name": "Pedro",
      "pushName": "Pedro"
    }
  }
}
```

| `data` field   | Type     | Description                              |
|----------------|----------|------------------------------------------|
| `id`           | string   | `false_{chatId}_{whatsAppId}`            |
| `from`         | string   | Sender `chatId`                          |
| `to`           | string   | Recipient `chatId` (your session)        |
| `body`         | string   | Message text (or caption for media)      |
| `type`         | string   | `chat`, `image`, `video`, `ptt`, `document`, `sticker`, `vcard`, `location`, `revoked` |
| `waTimestamp`  | number   | Unix epoch seconds from WhatsApp         |
| `timestamp`    | string   | ISO 8601 UTC                             |
| `isGroup`      | boolean  | `true` if from a `@g.us` chat            |
| `hasMedia`     | boolean  | `true` if message has downloadable media |
| `contact`      | object   | Sender profile (name, pushName)          |
| `mediaUrl`     | string?  | Direct media URL (if `webhook_base64` is disabled) |
| `mediaBase64`  | string?  | Base64 data URI (if enabled)             |
| `mimetype`     | string?  | MIME type for media messages             |
| `quotedMsg`    | object?  | Quoted message if this is a reply        |
| `forwarded`    | boolean? | `true` if this is a forwarded message    |
| `mentionedIds` | string[]?| Mentioned `chatId`s                      |

### 2. `message.sent` — Outgoing message confirmed

Fired AFTER `whatsapp-web.js` confirms the send succeeded. Use this to update your DB's `Message.status` from `PENDING` to `SENT`.

```json
{
  "event": "message.sent",
  "timestamp": "...",
  "sessionId": "sess_abc",
  "idempotencyKey": "msg_...",
  "data": {
    "id": "true_59169387181@c.us_3EB0ABC123",
    "to": "59169387181@c.us",
    "body": "Hello!",
    "type": "chat",
    "waTimestamp": 1718188800,
    "timestamp": "..."
  }
}
```

### 3. `message.ack` — Delivery / read receipt

```json
{
  "event": "message.ack",
  "timestamp": "...",
  "sessionId": "sess_abc",
  "idempotencyKey": "ack_...",
  "data": {
    "messageId": "true_59169387181@c.us_3EB0ABC123",
    "ack": 3,
    "ackName": "read"
  }
}
```

| `ack` | `ackName` | Meaning                    |
|-------|-----------|----------------------------|
| 0     | error     | Send failed                |
| 1     | pending   | Pending (rare)             |
| 2     | sent      | Server received            |
| 3     | delivered | Delivered to recipient     |
| 4     | read      | Read by recipient          |
| 5     | played    | Played (audio only)        |

**Map to your DB**:
- `ack >= 2` → `status = 'sent'`
- `ack == 3` → `status = 'delivered'`
- `ack == 4` → `status = 'read'`
- `ack == 5` (audio) → `status = 'played'`

### 4. `message.revoked` — Message deleted

```json
{
  "event": "message.revoked",
  "data": {
    "messageId": "true_..._3EB0ABC",
    "from": "59169387181@c.us",
    "to": "59169387555@c.us"
  }
}
```

> A `revoke` is when the original sender deletes the message for everyone. It can come from either side.

### 5. `session.status` — Connection status changed

```json
{
  "event": "session.status",
  "timestamp": "...",
  "sessionId": "sess_abc",
  "data": {
    "status": "CONNECTED",
    "phoneNumber": "59169387555"
  }
}
```

| `status`     | Meaning                          |
|--------------|----------------------------------|
| `INITIALIZING` | Engine booting                 |
| `SCAN_QR`    | QR ready to scan                  |
| `CONNECTING` | QR scanned, authenticating        |
| `CONNECTED`  | Connected, ready                  |
| `DISCONNECTED` | Lost connection, may reconnect |
| `FAILED`     | Fatal error, manual intervention  |

### 6. `session.qr` — New QR code generated

```json
{
  "event": "session.qr",
  "data": {
    "code": "2@ABC123XYZ...",
    "image": "data:image/png;base64,iVBORw0KGgo..."
  }
}
```

Use the `image` (data URI) directly in an `<img>` tag, or the `code` string for QR generation in other libraries.

### 7. `session.authenticated` — Pairing successful

```json
{
  "event": "session.authenticated",
  "data": { "phoneNumber": "59169387555" }
}
```

Fires once when the session transitions to `CONNECTED`.

### 8. `session.disconnected` — Lost connection

```json
{
  "event": "session.disconnected",
  "data": { "reason": "LOGOUT", "phoneNumber": "59169387555" }
}
```

Possible reasons: `LOGOUT`, `NAVIGATION`, `CONFLICT`, `TIMEOUT`, `MULTI_DEVICE_CONFLICT`.

### 9. `group.join` — Bot added to group

```json
{
  "event": "group.join",
  "data": {
    "groupId": "120363012345678@g.us",
    "groupName": "Family",
    "addedBy": "59169387181@c.us"
  }
}
```

### 10. `group.leave` — Bot removed from group

```json
{
  "event": "group.leave",
  "data": {
    "groupId": "120363012345678@g.us",
    "removedBy": "59169387181@c.us"
  }
}
```

### 11. `group.update` — Group metadata changed

```json
{
  "event": "group.update",
  "data": {
    "groupId": "120363012345678@g.us",
    "change": "subject",
    "newValue": "New Family Name"
  }
}
```

`change` can be: `subject`, `description`, `icon`, `participants`.

### 12. `*` (wildcard)

Subscribe to all events for a session. Useful for development/debugging, not recommended in production.

---

## Wildcard & Multi-Event

```json
{
  "events": ["*"]
}
```

Subscribe to a subset:
```json
{
  "events": ["message.received", "message.ack", "session.status"]
}
```

The same URL receives ALL events you subscribed to. The `X-OpenWA-Event` header tells you which event.

---

## Webhook Best Practices

1. **Respond 2xx fast.** If processing takes >5s, ACK first then process async (e.g., queue it to a Laravel job).
2. **Verify HMAC on EVERY request.** Use the raw body, not the parsed array.
3. **Dedupe on `idempotencyKey`.** Don't dedupe on `deliveryId` — that changes on retry.
4. **Use `X-OpenWA-Retry-Count` for observability** — increment your log/alert severity as retries grow.
5. **Return 5xx on transient failures** to trigger retry. Return 4xx on permanent failures (e.g., invalid payload) to STOP retrying.
6. **Store `messageId`** (the `data.id` or `data.messageId` for acks) to update status later.
7. **Be tolerant of unknown events.** New event types may be added in future versions.
8. **Don't trust `data.from` blindly** for identity — use it only to route to a conversation, then verify via the contact API.

---

## Webhook Setup via Session Create

You can register a webhook at session creation time:

```http
POST /api/sessions
{
  "name": "tigo1",
  "webhook": {
    "url": "https://hostbol.lat/api/webhooks/openwa/1",
    "events": ["message.received", "message.ack", "session.status"],
    "secret": "your-hmac-secret"
  }
}
```

This saves a round-trip vs `POST /sessions` → `POST /sessions/{id}/webhooks`.

---

## Internal Hooks (Plugin Developers)

OpenWA has a plugin system that runs *server-side* before/after webhook dispatch:

| Hook                  | When                                     | Can Cancel? |
|-----------------------|------------------------------------------|-------------|
| `webhook:before`      | Before building payload                  | Yes         |
| `webhook:queued`      | After enqueuing to BullMQ                | No          |
| `webhook:delivered`   | After successful HTTP delivery           | No          |
| `webhook:error`       | On any error                             | No          |
| `webhook:after`       | After delivery (direct mode only)        | No          |

Plugins can mutate `payload` in `webhook:before` to add/remove fields. See `src/core/hooks/`.
