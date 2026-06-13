# Evolution API v2 — Webhook Events Reference

Complete reference for all webhook events sent by Evolution API v2.

---

## ⚠️ CRITICAL: fromMe Processing (Project-Specific)

**In our project (`Modules\ChatBot/Channels/Evolution/EvolutionChannel.php`), `fromMe=true` is NOT filtered out.** We process BOTH directions:

- `fromMe=false` (incoming) → save as `role=user`
- `fromMe=true` (outgoing echo) → save as `role=admin` (the admin's own message)

**Why?** Because the admin can send messages from the WhatsApp app (not from our CRM panel), and we need to track those too. The `fromMe=true` echo is the ONLY way the CRM knows about messages sent directly from the phone.

When processing `fromMe=true`:
- `remoteJid` = the **recipient** (the client being messaged) — NOT the admin
- `pushName` = the **admin's own name** (the sender) — NOT the client's
- The user should be looked up/created using `remoteJid`, not `pushName`
- The `linkOrCreate()` should be called with `name=null` (the admin's pushName is irrelevant for the client's user record)

See `rules/common-pitfalls.md` #11 for the full breakdown.

---

## Common Payload Structure

All webhooks share this base structure:

```json
{
  "event": "string",
  "instance": "string",
  "data": { ... },
  "destination": "string",
  "date_time": "ISO 8601",
  "sender": "string",
  "server_url": "string",
  "apikey": "string"
}
```

---

## 1. messages.upsert

**Description:** New message received or sent. Most frequent event (~95% of traffic). This event fires for BOTH incoming and outgoing messages — distinguish them with `key.fromMe`.

**CRITICAL: `fromMe` MUST be processed in both directions.** Filtering out `fromMe: true` will cause:
- Outgoing admin messages sent from the panel to be MISSING from the database entirely (only the webhook echo is what tells you the message went out).
- The `external_id` returned by Evolution (e.g. `3EB0FA07A6B525A7894F56`) will never be linked to the local admin message.
- Later `messages.update` status events (DELIVERY_ACK, READ) cannot update the local message because its `external_id` is empty.

### Processing Logic

```php
// In EvolutionChannel::processIncoming():
$fromMe = (bool) ($key['fromMe'] ?? false);
$messageId = $key['id'] ?? null;

if ($fromMe) {
    // Outgoing: admin sent from the panel, Evolution confirms.
    // Check if local admin message already exists (may have been saved before webhook arrived).
    if ($messageId) {
        $existing = Message::withTrashed()->where('external_id', $messageId)->first();
        if ($existing) {
            return $existing;
        }
    }
    // Create new admin message from the webhook payload
    $message = Message::create([
        'conversation_id' => $conversation->id,
        'role' => Message::ROLE_ADMIN,
        'type' => $type,
        'content' => $content,
        'external_id' => $messageId,
        // ...
    ]);
    return $message;
}

// Incoming: user sent from phone.
// (existing logic, unchanged)
```

**Idempotency:** A `messages.upsert` webhook may fire twice for the same `messageId` (Evolution retries). Always check `external_id` first.

### Data Structure

```json
{
  "key": {
    "remoteJid": "string",
    "remoteJidAlt": "string",
    "fromMe": false,
    "id": "string",
    "participant": "string|null",
    "participantAlt": "string|null",
    "addressingMode": "lid"
  },
  "pushName": "string",
  "status": "string",
  "message": { ... },
  "messageType": "string",
  "messageTimestamp": 1234567890,
  "instanceId": "uuid",
  "source": "string"
}
```

### Status Values

| Status | Description |
|--------|-------------|
| `DELIVERY_ACK` | Message delivered to device |
| `SERVER_ACK` | Message received by WhatsApp server |
| `READ` | Message read by recipient |
| `PLAYED` | Audio/video played |

### Source Values

`android`, `ios`, `web`

### Message Types & Examples

See `rules/message-types.md` for the full type reference with extraction logic.

---

## 2. messages.update

**Description:** Status update for outgoing messages (delivery/read receipts).

**Total in DB:** ~3.6% of traffic.

### Data Structure

```json
{
  "keyId": "string",
  "remoteJid": "string",
  "fromMe": true,
  "status": "string",
  "instanceId": "uuid",
  "messageId": "string"
}
```

### Status Mapping to DB

| API Status | DB Status | Description |
|------------|-----------|-------------|
| `SERVER_ACK` | `sent` | Message received by WhatsApp server |
| `DELIVERY_ACK` | `delivered` | Message delivered to recipient device |
| `READ` | `read` | Message read by recipient |
| `ERROR` | `failed` | Error sending message |

### Processing

```php
// In EvolutionChannel or controller:
$statusMap = [
    'SERVER_ACK' => 'sent',
    'DELIVERY_ACK' => 'delivered',
    'READ' => 'read',
    'ERROR' => 'failed',
];

$newStatus = $statusMap[$data['status']] ?? null;
if ($newStatus && isset($data['messageId'])) {
    Message::where('external_id', $data['keyId'])
        ->update(['status' => $newStatus]);
}
```

---

## 3. send.message

**Description:** Confirmation that an outgoing message was sent from the instance. Fires when the CRM sends a message via API.

**Total in DB:** ~0.6% of traffic.

### Data Structure

```json
{
  "key": {
    "remoteJid": "string",
    "fromMe": true,
    "id": "string"
  },
  "pushName": "string",
  "status": "PENDING",
  "message": {
    "conversation": "string"
  },
  "contextInfo": { ... },
  "messageType": "string",
  "messageTimestamp": 1234567890,
  "instanceId": "uuid",
  "source": "string"
}
```

### Notes

- Only fires for outgoing messages (`fromMe: true`)
- Status is always `PENDING` (subsequent statuses come via `messages.update`)
- Use this to confirm message was accepted by the instance
- The `messageId` field contains the CRM's internal message ID

---

## 4. connection.update

**Description:** Change in instance connection state.

**Total in DB:** ~0.4% of traffic.

### Data Structure

```json
{
  "instance": "string",
  "wuid": "string",
  "profileName": "string",
  "profilePictureUrl": "string",
  "state": "string",
  "statusReason": 200
}
```

### State Values & Actions

| State | Description | Action in CRM |
|-------|-------------|---------------|
| `open` | Connected and operational | `status = 'active'`, `connectionStatus = 'open'` |
| `connecting` | Establishing connection | `connectionStatus = 'connecting'` |
| `close` | Disconnected | `status = 'inactive'`, `connectionStatus = 'inactive'` |

### Processing

```php
if ($event === 'connection.update') {
    $state = $data['state'] ?? 'close';

    $statusMap = [
        'open' => ['status' => 'active', 'connectionStatus' => 'open'],
        'connecting' => ['connectionStatus' => 'connecting'],
        'close' => ['status' => 'inactive', 'connectionStatus' => 'inactive'],
    ];

    $updates = $statusMap[$state] ?? $statusMap['close'];
    Inbox::where('name', $instance)->update($updates);
    broadcast(new InboxStatusUpdated($instance, $state, $updates['status'] ?? 'inactive'));
}
```

---

## 5. QRCODE_UPDATED

**Description:** New QR code generated for instance linking. Indicates instance disconnected and needs re-linking.

### Data

Not processed — the event itself triggers the action.

### Action in CRM

```php
Inbox::where('name', $instance)->update([
    'config->connectionStatus' => 'disconnected',
    'status' => 'inactive',
]);
broadcast(new InboxStatusUpdated($instance, 'disconnected', 'inactive'));
```

---

## 6. LOGOUT_INSTANCE

**Description:** Instance was logged out (disconnected intentionally).

### Action in CRM

```php
Inbox::where('name', $instance)->update([
    'config->connectionStatus' => 'removed',
    'status' => 'inactive',
]);
broadcast(new InboxStatusUpdated($instance, 'removed', 'inactive'));
```

---

## 7. REMOVE_INSTANCE

**Description:** Instance was deleted entirely.

### Action in CRM

```php
Inbox::where('name', $instance)->update([
    'config->connectionStatus' => 'removed',
    'status' => 'inactive',
]);
broadcast(new InboxStatusUpdated($instance, 'removed', 'inactive'));
```

---

## 8. APPLICATION_STARTUP

**Description:** Evolution API application is starting/restarting.

### Action in CRM

```php
Inbox::where('name', $instance)->update([
    'config->connectionStatus' => 'connecting',
]);
broadcast(new InboxStatusUpdated($instance, 'connecting', 'active'));
```

---

## 9. call

**Description:** WhatsApp call event (voice or video).

**Total in DB:** ~0.1% of traffic.

### Data Structure

```json
{
  "chatId": "string",
  "from": "string",
  "id": "string",
  "date": "ISO 8601",
  "offline": false,
  "status": "string",
  "isVideo": false,
  "isGroup": false
}
```

### Status Values & Labels

| Status | Label | Description |
|--------|-------|-------------|
| `offer` | `entrante` | Incoming call (offer) |
| `ringing` | `sonando` | Ringing |
| `accept` | `respondida` | Answered |
| `reject` | `rechazada` | Rejected |
| `timeout` | `no respondida` | Not answered |
| `terminate` / `ended` | `finalizada` | Call ended |

### Processing

```php
if ($event === 'call') {
    $from = $data['from'] ?? null;
    if (! $from) {
        return null; // Skip calls without from
    }

    $callType = $data['isVideo'] ? 'video' : 'voice';
    $status = $data['status'] ?? 'offer';
    $duration = $data['duration'] ?? 0;

    $labelMap = [
        'offer' => 'entrante',
        'ringing' => 'sonando',
        'accept' => 'respondida',
        'reject' => 'rechazada',
        'timeout' => 'no respondida',
        'ended' => 'finalizada',
    ];

    $label = $labelMap[$status] ?? $status;
    $content = "📞 Llamada de {$callType} {$label}";
    if ($duration > 0) {
        $minutes = floor($duration / 60);
        $seconds = $duration % 60;
        $content .= " — {$minutes}:" . str_pad($seconds, 2, '0', STR_PAD_LEFT) . " min";
    }

    // Create contact, conversation, and message (callLog type)
    // Similar to messages.upsert flow
}
```

---

## Filtering Rules in Controller

The `EvolutionWebhookController` and `EvolutionChannel` apply these filters:

1. **No instance** → Skip if `payload.instance` is missing
2. **No Inbox** → Skip if no matching Inbox found for the instance name
3. **Inactive instance** → Skip all events except `connection.update`
4. **Broadcast/Newsletter** → Skip messages to `@broadcast` or `@newsletter`
5. **Duplicate** → Skip if message already exists by `external_id`
6. **Empty content** → Skip if no extractable content
7. **fromMe** → Skip in `messages.upsert` (handled separately)
8. **Album/Protocol** → Skip `albumMessage` and `protocolMessage` types
9. **Call without from** → Skip calls missing `from` field

---

## Event Frequency Summary

| Event | % of Traffic | Stored in DB | Processes Logic | Broadcasts |
|-------|-------------|--------------|-----------------|------------|
| `messages.upsert` | ~95.3% | Yes | Yes (Contact, Conversation, Message) | `MessageCreated` |
| `messages.update` | ~3.6% | Yes | Yes (Message status update) | `MessageStatusUpdated` |
| `send.message` | ~0.6% | Yes | Yes (Message status update) | `MessageStatusUpdated` |
| `connection.update` | ~0.4% | Yes | Yes (Inbox status) | `InboxStatusUpdated` |
| `call` | ~0.1% | Yes | Yes (Contact, Conversation, Message) | `MessageCreated` |
| `QRCODE_UPDATED` | ~0% | No | Yes (Inbox status) | `InboxStatusUpdated` |
| `LOGOUT_INSTANCE` | ~0% | No | Yes (Inbox status) | `InboxStatusUpdated` |
| `REMOVE_INSTANCE` | ~0% | No | Yes (Inbox status) | `InboxStatusUpdated` |
| `APPLICATION_STARTUP` | ~0% | No | Yes (Inbox status) | `InboxStatusUpdated` |
