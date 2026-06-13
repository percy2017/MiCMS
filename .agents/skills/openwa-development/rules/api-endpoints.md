# OpenWA API v0.1.6 — Complete Endpoint Reference

Base URL: `https://openwa.hostbol.lat/api` (production) or `http://localhost:2785/api` (dev).
All endpoints require header `X-API-Key: <key>` unless marked **public**.

The complete machine-readable spec is always at `/api/docs-json` and `/api/docs-yaml`. This document is a curated, opinionated reference with role requirements, response shapes, and gotchas.

---

## Authentication

All requests MUST include:
```http
X-API-Key: your-key
Content-Type: application/json
Accept: application/json
X-Request-ID: req_<epoch_ms>      # optional, returned in meta.requestId
```

**Roles** (from `ApiKeyRole` enum):
- `admin` — full access, can manage other API keys
- `operator` — read+write, no API key management (default)
- `viewer` — read-only

The `viewer` role is rejected by all `POST`/`PUT`/`DELETE` endpoints.

---

## 1. Health (public)

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/health` | Basic health check (public) | — |
| GET | `/health/live` | Kubernetes liveness probe (public) | — |
| GET | `/health/ready` | Kubernetes readiness probe (public) | — |

Response:
```json
{ "status": "ok", "timestamp": "2026-06-12T10:00:00Z" }
```

---

## 2. Sessions

Session = a single WhatsApp account. One OpenWA instance can host multiple sessions.

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| POST | `/sessions` | Create a new session record | `operator` |
| GET | `/sessions` | List all sessions | `viewer` |
| GET | `/sessions/{id}` | Get one session | `viewer` |
| DELETE | `/sessions/{id}` | Delete a session + engine | `operator` |
| POST | `/sessions/{id}/start` | Boot the engine, get QR-ready | `operator` |
| POST | `/sessions/{id}/stop` | Disconnect WhatsApp | `operator` |
| GET | `/sessions/{id}/qr` | Get current QR code (if not yet connected) | `viewer` |
| GET | `/sessions/{id}/groups` | List groups for this session | `viewer` |
| GET | `/sessions/stats/overview` | Aggregate session stats | `viewer` |

### 2.1 Create Session

```http
POST /api/sessions
{
  "name": "tigo1",
  "webhook": {                       // optional: auto-register a webhook
    "url": "https://your-server.com/webhook",
    "events": ["message.received", "message.ack", "session.status"],
    "secret": "your-hmac-secret"
  }
}
```

Response `201`:
```json
{
  "id": "sess_abc123",
  "name": "tigo1",
  "status": "INITIALIZING",
  "phone": null,
  "pushName": null,
  "createdAt": "2026-06-12T10:00:00.000Z"
}
```

Errors: `409 SESSION_ALREADY_EXISTS` if `name` is taken.

### 2.2 Session Status Values

| API Status     | Internal Status | Description                                |
|----------------|-----------------|--------------------------------------------|
| `INITIALIZING` | `initializing`  | Session record created, engine not yet booted |
| `SCAN_QR`      | `qr_ready`      | QR code generated, waiting for scan        |
| `CONNECTING`   | `connecting`    | QR scanned, authenticating                 |
| `CONNECTED`    | `ready`         | Connected and ready to send/receive        |
| `DISCONNECTED` | `disconnected`  | Lost connection, may need re-auth          |
| `FAILED`       | `error`         | Fatal error, manual intervention required |

**Internal statuses appear in DB and engine logs. API responses and webhooks always use the API status.**

### 2.3 Get QR Code

```http
GET /api/sessions/{id}/qr
```

Response `200`:
```json
{
  "code": "2@ABC123XYZ,5Wq6V...",
  "image": "data:image/png;base64,iVBORw0KGgo..."
}
```

Errors: `400 SESSION_QR_EXPIRED` (re-generate), `400` if already authenticated.

### 2.4 Start Session

```http
POST /api/sessions/{id}/start
```

Boots the `whatsapp-web.js` client. Status transitions: `INITIALIZING` → `SCAN_QR` → `CONNECTED`.

---

## 3. Messages

All message endpoints are under `/sessions/{sessionId}/messages`.

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/messages` | Get message history (paginated) | `viewer` |
| GET | `/messages/batch/{batchId}` | Get bulk batch status | `viewer` |
| POST | `/messages/batch/{batchId}/cancel` | Cancel running batch | `operator` |
| POST | `/messages/send-text` | Send text | `operator` |
| POST | `/messages/send-image` | Send image | `operator` |
| POST | `/messages/send-video` | Send video | `operator` |
| POST | `/messages/send-audio` | Send audio / voice note | `operator` |
| POST | `/messages/send-document` | Send document | `operator` |
| POST | `/messages/send-sticker` | Send sticker | `operator` |
| POST | `/messages/send-location` | Send location | `operator` |
| POST | `/messages/send-contact` | Send contact card | `operator` |
| POST | `/messages/send-product` | Send catalog product | `operator` |
| POST | `/messages/send-catalog` | Send catalog link | `operator` |
| POST | `/messages/reply` | Reply (quote) to a message | `operator` |
| POST | `/messages/forward` | Forward a message | `operator` |
| POST | `/messages/react` | Add/remove reaction | `operator` |
| GET | `/messages/{chatId}/{messageId}/reactions` | List reactions | `viewer` |
| POST | `/messages/delete` | Delete a message | `operator` |
| POST | `/messages/send-bulk` | Send batch (async) | `operator` |

### 3.1 Send Text

```http
POST /api/sessions/{id}/messages/send-text
{
  "chatId": "59169387181@c.us",
  "text": "Hello!",
  "options": {                         // optional
    "quotedMessageId": "false_59169387181@c.us_3EB0ABC",   // legacy
    "mentionedIds": ["591...@c.us"]
  }
}
```

Response `201`:
```json
{
  "messageId": "true_59169387181@c.us_3EB0ABC123",
  "timestamp": 1718188800
}
```

### 3.2 Send Image (and other media)

```http
POST /api/sessions/{id}/messages/send-image
{
  "chatId": "59169387181@c.us",
  "image": {
    "url": "https://example.com/photo.jpg",        // OR
    "base64": "data:image/jpeg;base64,/9j/4AAQ..." // OR
    "path": "/uploads/photo.jpg"                    // server-local
  },
  "caption": "Optional caption (max 1024 chars)",
  "mimetype": "image/jpeg"            // REQUIRED when using base64
}
```

Limits: 16 MB, JPEG/PNG/WebP/GIF, max 4096x4096.

**Same pattern for** `send-video` (64 MB), `send-audio` (16 MB, `ptt:true` for voice note), `send-document` (100 MB, `filename` required), `send-sticker` (500 KB WebP).

### 3.3 Send Bulk (Async)

```http
POST /api/sessions/{id}/messages/send-bulk
{
  "batchId": "optional-custom-id",
  "messages": [
    { "chatId": "591...@c.us", "type": "text", "content": { "text": "Hi {name}!" }, "variables": { "name": "Pedro" } },
    { "chatId": "592...@c.us", "type": "image", "content": { "image": { "url": "..." }, "caption": "..." } }
  ],
  "options": {
    "delayBetweenMessages": 5000,    // default 3000, min 1000
    "randomizeDelay": true,
    "stopOnError": false
  }
}
```

Response `202`:
```json
{
  "batchId": "batch_xyz",
  "status": "processing",
  "totalMessages": 2,
  "estimatedCompletionTime": "2026-06-12T10:05:00.000Z",
  "statusUrl": "/api/sessions/{id}/messages/batch/batch_xyz"
}
```

---

## 4. Webhooks

Per-session webhook configuration. See `rules/webhook-events.md` for the full event catalog and payload format.

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| POST | `/sessions/{id}/webhooks` | Create a webhook | `operator` |
| GET | `/sessions/{id}/webhooks` | List webhooks for a session | `viewer` |
| GET | `/sessions/{id}/webhooks/{webhookId}` | Get one webhook | `viewer` |
| PUT | `/sessions/{id}/webhooks/{webhookId}` | Update webhook | `operator` |
| DELETE | `/sessions/{id}/webhooks/{webhookId}` | Delete webhook | `operator` |
| POST | `/sessions/{id}/webhooks/{webhookId}/test` | Send a test payload | `operator` |
| GET | `/webhooks` | List ALL webhooks across all sessions | `viewer` |

### 4.1 Create Webhook

```http
POST /api/sessions/{id}/webhooks
{
  "url": "https://your-server.com/webhook",
  "events": ["message.received", "message.ack", "session.status"],
  "secret": "your-hmac-secret",      // optional but recommended
  "headers": {                        // optional: extra headers
    "X-Custom-Header": "value"
  },
  "retryCount": 3                     // 0-5, default 3
}
```

Supported event types (see `rules/webhook-events.md` for full list):
- `message.received`, `message.sent`, `message.ack`, `message.revoked`
- `session.status`, `session.qr`, `session.authenticated`, `session.disconnected`
- `group.join`, `group.leave`, `group.update`
- `*` (wildcard: all events from that session)

Response `201`:
```json
{
  "id": "wh_xyz",
  "sessionId": "sess_abc",
  "url": "https://your-server.com/webhook",
  "events": ["message.received"],
  "active": true,
  "retryCount": 3,
  "lastTriggeredAt": null,
  "createdAt": "2026-06-12T10:00:00.000Z",
  "updatedAt": "2026-06-12T10:00:00.000Z"
}
```

### 4.2 Test Webhook

```http
POST /api/sessions/{id}/webhooks/{webhookId}/test
```

Sends a `test` event with the current webhook's URL, secret, and headers. Useful for verifying HMAC signature and reachability.

```json
{
  "event": "test",
  "timestamp": "...",
  "sessionId": "sess_abc",
  "idempotencyKey": "test_...",
  "deliveryId": "dlv_...",
  "data": { "message": "This is a test webhook from OpenWA" }
}
```

---

## 5. Contacts

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/sessions/{id}/contacts` | List all contacts | `viewer` |
| GET | `/sessions/{id}/contacts/{contactId}` | Get one contact | `viewer` |
| GET | `/sessions/{id}/contacts/check/{number}` | Check if a number is on WhatsApp | `viewer` |
| GET | `/sessions/{id}/contacts/{contactId}/profile-picture` | Get profile picture URL | `viewer` |
| POST | `/sessions/{id}/contacts/{contactId}/block` | Block contact | `operator` |
| DELETE | `/sessions/{id}/contacts/{contactId}/block` | Unblock contact | `operator` |

Contact `id` format: `628123456789@c.us`. `check/{number}` accepts raw phone without `@c.us` suffix.

---

## 6. Groups

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/sessions/{id}/groups` | List groups | `viewer` |
| POST | `/sessions/{id}/groups` | Create group | `operator` |
| GET | `/sessions/{id}/groups/{groupId}` | Get group info | `viewer` |
| POST | `/sessions/{id}/groups/{groupId}/participants` | Add participants | `operator` |
| DELETE | `/sessions/{id}/groups/{groupId}/participants` | Remove participants | `operator` |
| POST | `/sessions/{id}/groups/{groupId}/participants/promote` | Promote to admin | `operator` |
| POST | `/sessions/{id}/groups/{groupId}/participants/demote` | Demote from admin | `operator` |
| PUT | `/sessions/{id}/groups/{groupId}/subject` | Change name | `operator` |
| PUT | `/sessions/{id}/groups/{groupId}/description` | Change description | `operator` |
| POST | `/sessions/{id}/groups/{groupId}/leave` | Leave group | `operator` |
| GET | `/sessions/{id}/groups/{groupId}/invite-code` | Get invite link | `viewer` |
| POST | `/sessions/{id}/groups/{groupId}/invite-code/revoke` | Generate new invite code | `operator` |

Group `id` format: `120363012345678@g.us`.

---

## 7. Channels (Newsletter)

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/sessions/{id}/channels` | List subscribed channels | `viewer` |
| POST | `/sessions/{id}/channels/subscribe` | Subscribe via invite code | `operator` |
| GET | `/sessions/{id}/channels/{channelId}` | Get channel info | `viewer` |
| DELETE | `/sessions/{id}/channels/{channelId}` | Unsubscribe | `operator` |
| GET | `/sessions/{id}/channels/{channelId}/messages` | Get channel messages | `viewer` |

Channel `id` format: `123@newsletter`.

---

## 8. Labels (Business)

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/sessions/{id}/labels` | List labels | `viewer` |
| GET | `/sessions/{id}/labels/{labelId}` | Get label | `viewer` |
| GET | `/sessions/{id}/labels/chat/{chatId}` | Labels for a chat | `viewer` |
| POST | `/sessions/{id}/labels/chat/{chatId}` | Add label | `operator` |
| DELETE | `/sessions/{id}/labels/chat/{chatId}/{labelId}` | Remove label | `operator` |

**Only available for WhatsApp Business accounts.**

---

## 9. Status (Stories)

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/sessions/{id}/status` | All status updates | `viewer` |
| GET | `/sessions/{id}/status/{contactId}` | Status from a contact | `viewer` |
| POST | `/sessions/{id}/status/send-text` | Post text status | `operator` |
| POST | `/sessions/{id}/status/send-image` | Post image status | `operator` |
| POST | `/sessions/{id}/status/send-video` | Post video status | `operator` |
| DELETE | `/sessions/{id}/status/{statusId}` | Delete own status | `operator` |

---

## 10. Catalog (Business)

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/sessions/{id}/catalog` | Get business catalog info | `viewer` |
| GET | `/sessions/{id}/catalog/products` | List products | `viewer` |
| GET | `/sessions/{id}/catalog/products/{productId}` | Get product | `viewer` |
| POST | `/sessions/{id}/messages/send-product` | Send single product | `operator` |
| POST | `/sessions/{id}/messages/send-catalog` | Send catalog link | `operator` |

---

## 11. Auth (API Keys)

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| POST | `/auth/api-keys` | Create new API key | `admin` |
| GET | `/auth/api-keys` | List keys | `admin` |
| GET | `/auth/api-keys/{id}` | Get key details | `admin` |
| PUT | `/auth/api-keys/{id}` | Update key (name, role, IPs, sessions) | `admin` |
| DELETE | `/auth/api-keys/{id}` | Delete key | `admin` |
| POST | `/auth/api-keys/{id}/revoke` | Revoke (deactivate) without delete | `admin` |
| POST | `/auth/validate` | Validate a key (returns 200 if valid) | `operator` |
| GET | `/dashboard/bootstrap` | Get default key (for bundled dashboard) | public |

Create response includes the **plaintext key** — store it immediately, it's never returned again.

---

## 12. Infrastructure / Admin

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/infra/status` | Infrastructure status (DB, queue, sessions) | `viewer` |
| GET | `/infra/engines` | Available WhatsApp engines | `viewer` |
| GET | `/infra/engines/current` | Current engine | `viewer` |
| PUT | `/infra/config` | Save infra config to `.env` | `admin` |
| POST | `/infra/restart` | Request server restart | `admin` |
| GET | `/infra/health` | Health check (engine+DB) | `viewer` |
| GET | `/infra/export-data` | Export all data as JSON | `admin` |
| POST | `/infra/import-data` | Import data (replaces) | `admin` |
| GET | `/infra/storage/files/count` | Storage stats | `viewer` |
| GET | `/infra/storage/export` | Export files as tar.gz | `admin` |
| POST | `/infra/storage/import` | Import tar.gz | `admin` |

---

## 13. Statistics

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/stats/overview` | Overall stats | `viewer` |
| GET | `/stats/messages` | Message stats with time series | `viewer` |
| GET | `/stats/sessions/{sessionId}` | Per-session stats | `viewer` |

---

## 14. Settings

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/settings` | Get current application settings | `viewer` |
| PUT | `/settings` | Update settings | `admin` |

---

## 15. Audit

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/audit` | List audit logs (filterable) | `admin` |

Query params: `action`, `severity`, `sessionId`, `apiKeyId`, `limit`, `offset`.

---

## 16. Plugins

| Method | Endpoint | Description | Role |
|--------|----------|-------------|------|
| GET | `/plugins` | List all plugins | `viewer` |
| GET | `/plugins/{id}` | Get plugin | `viewer` |
| PUT | `/plugins/{id}/config` | Update plugin config | `admin` |
| POST | `/plugins/{id}/enable` | Enable plugin | `admin` |
| POST | `/plugins/{id}/disable` | Disable plugin | `admin` |
| GET | `/plugins/{id}/health` | Plugin health | `viewer` |

---

## Error Response Format

```json
{
  "success": false,
  "error": {
    "code": "MESSAGE_NUMBER_NOT_ON_WHATSAPP",
    "message": "Number 12345 is not registered on WhatsApp",
    "details": { "number": "12345" }
  },
  "meta": {
    "timestamp": "2026-06-12T10:00:00.000Z",
    "requestId": "req_1706868000000"
  }
}
```

### Error Code Categories

**General**: `VALIDATION_ERROR` (400), `UNAUTHORIZED` (401), `FORBIDDEN` (403), `NOT_FOUND` (404), `RATE_LIMITED` (429), `INTERNAL_ERROR` (500).

**Session**: `SESSION_NOT_FOUND`, `SESSION_NOT_READY`, `SESSION_ALREADY_EXISTS`, `SESSION_INITIALIZING`, `SESSION_QR_EXPIRED`, `SESSION_AUTH_FAILED`, `SESSION_DISCONNECTED`, `SESSION_LOGGED_OUT`, `SESSION_LIMIT_REACHED`, `SESSION_BANNED`.

**Message**: `MESSAGE_SEND_FAILED`, `MESSAGE_NOT_FOUND`, `MESSAGE_INVALID_CHAT_ID`, `MESSAGE_NUMBER_NOT_ON_WHATSAPP`, `MESSAGE_MEDIA_TOO_LARGE`, `MESSAGE_MEDIA_DOWNLOAD_FAILED`, `MESSAGE_MEDIA_INVALID_FORMAT`, `MESSAGE_TEXT_TOO_LONG`, `MESSAGE_BLOCKED_CONTACT`, `MESSAGE_RATE_LIMITED`, `MESSAGE_QUOTED_NOT_FOUND`.

**Webhook**: `WEBHOOK_NOT_FOUND`, `WEBHOOK_URL_INVALID`, `WEBHOOK_URL_UNREACHABLE`, `WEBHOOK_DUPLICATE`, `WEBHOOK_LIMIT_REACHED`.

**Group**: `GROUP_NOT_FOUND`, `GROUP_NOT_ADMIN`, `GROUP_PARTICIPANT_EXISTS`, `GROUP_PARTICIPANT_NOT_FOUND`, `GROUP_INVITE_INVALID`, `GROUP_NAME_TOO_LONG`.

**Contact**: `CONTACT_NOT_FOUND`, `CONTACT_BLOCKED`, `CONTACT_NOT_BLOCKED`.

---

## Pagination

Paginated list endpoints (e.g., `/audit`, `/messages`) return:

```json
{
  "success": true,
  "data": [...],
  "pagination": {
    "page": 1,
    "limit": 50,
    "total": 1234,
    "totalPages": 25
  }
}
```

---

## Rate Limiting

| Category             | Default Limit |
|----------------------|---------------|
| Session management   | 10 req/min    |
| Send message         | 60 req/min    |
| Read operations      | 120 req/min   |
| Webhook management   | 10 req/min    |

Response headers:
```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1706868060
```

On `429`: include `Retry-After` header (seconds).
