---
name: openwa-development
description: TRIGGER when working with OpenWA API (https://github.com/rmyndharis/OpenWA) — a self-hosted Node.js WhatsApp Web gateway built on whatsapp-web.js. Activate when building, debugging, or extending OpenWA integrations in Laravel or any client; sending messages via OpenWA REST API; receiving webhooks from OpenWA; parsing message payloads; troubleshooting session lifecycle (QR / connect / disconnect); integrating multi-session WhatsApp bots. Do NOT use for Evolution API (use evolution-api-development), Twilio, WhatsApp Business Cloud API, or other WhatsApp providers.
---

# OpenWA Development

OpenWA is a free, open-source WhatsApp API Gateway (8.3k+ stars, MIT license) built on **whatsapp-web.js** with NestJS 11, TypeORM, TypeScript 5, and Node 22 LTS. It exposes a REST API + WebSocket on port `2785`, optional dashboard on `2886`, and supports multi-session WhatsApp instances in a single deployment.

**Version this skill targets:** `v0.1.6` (May 17, 2026). API surface stable since `v0.1.0`. Pin client expectations to `/api/docs-json` for ground truth.

## Project Architecture

```
HTTP Request    → NestJS Controllers → DTO validation (class-validator) → Services → whatsapp-web.js engine
                                                                                            ↓
Webhook delivery: Service event → BullMQ (Redis, optional) → HTTP POST to consumer URL    ↓
WebSocket: Engine event → Socket.IO → subscribers                                         ↓
Database:   SQLite (default) or PostgreSQL via TypeORM; S3-compatible storage for media   ↓
```

### Core Stack
| Layer       | Technology                          |
|-------------|--------------------------------------|
| Runtime     | Node.js 22 LTS                       |
| Framework   | NestJS 11.x                          |
| Language    | TypeScript 5.x                       |
| WA Engine   | whatsapp-web.js                      |
| Database    | SQLite (default) / PostgreSQL        |
| ORM         | TypeORM                              |
| Cache/Queue | Redis (optional, BullMQ)             |
| Storage     | Local filesystem / S3 / MinIO        |
| Auth        | API Key (X-API-Key header)           |
| Real-time   | WebSocket (Socket.IO)                |
| Container   | Docker + Compose                     |

### Key Source Paths

| File                                                          | Purpose                                                |
|---------------------------------------------------------------|--------------------------------------------------------|
| `src/modules/session/session.controller.ts`                   | Session CRUD + start/stop + QR code                    |
| `src/modules/session/session.service.ts`                      | Engine lifecycle (whatsapp-web.js client)              |
| `src/modules/message/message.controller.ts`                   | All `send-*` and message history endpoints             |
| `src/modules/message/message.service.ts`                      | Message orchestration, save-before-send pattern        |
| `src/modules/webhook/webhook.controller.ts`                   | Per-session webhook CRUD                               |
| `src/modules/webhook/webhook.service.ts`                      | Dispatch + HMAC signature + BullMQ retry               |
| `src/modules/webhook/dto/webhook.dto.ts`                      | `CreateWebhookDto`, `WEBHOOK_EVENTS` enum              |
| `src/modules/webhook/entities/webhook.entity.ts`              | Webhook DB schema                                      |
| `src/modules/message/entities/message.entity.ts`             | Message DB schema (direction, status, metadata)        |
| `src/modules/session/entities/session.entity.ts`              | Session DB schema (status, proxy, phone)               |
| `src/modules/auth/entities/api-key.entity.ts`                | API key roles: `admin`, `operator`, `viewer`           |
| `src/engine/interfaces/whatsapp-engine.interface.ts`          | Engine abstraction (`sendTextMessage`, etc.)           |

## Quick Reference — Most Used Endpoints

Base URL: `http://localhost:2785/api` (or `https://openwa.hostbol.lat/api` for production).
Auth header: `X-API-Key: <key>`.

| Action                              | Method | Endpoint                                              |
|-------------------------------------|--------|-------------------------------------------------------|
| Health check                        | GET    | `/health`                                             |
| List sessions                       | GET    | `/sessions`                                           |
| Create session                      | POST   | `/sessions`                                           |
| Get session                         | GET    | `/sessions/{id}`                                      |
| Delete session                      | DELETE | `/sessions/{id}`                                      |
| Start session                       | POST   | `/sessions/{id}/start`                                |
| Stop session                        | POST   | `/sessions/{id}/stop`                                 |
| Get QR code                         | GET    | `/sessions/{id}/qr`                                   |
| Session stats                       | GET    | `/sessions/stats/overview`                            |
| Send text                           | POST   | `/sessions/{id}/messages/send-text`                   |
| Send image                          | POST   | `/sessions/{id}/messages/send-image`                  |
| Send video                          | POST   | `/sessions/{id}/messages/send-video`                  |
| Send audio                          | POST   | `/sessions/{id}/messages/send-audio`                  |
| Send document                       | POST   | `/sessions/{id}/messages/send-document`               |
| Send sticker                        | POST   | `/sessions/{id}/messages/send-sticker`                |
| Send location                       | POST   | `/sessions/{id}/messages/send-location`               |
| Send contact                        | POST   | `/sessions/{id}/messages/send-contact`                |
| Reply to message                    | POST   | `/sessions/{id}/messages/reply`                       |
| Forward message                     | POST   | `/sessions/{id}/messages/forward`                     |
| React to message                    | POST   | `/sessions/{id}/messages/react`                       |
| Delete message                      | POST   | `/sessions/{id}/messages/delete`                      |
| Get message reactions               | GET    | `/sessions/{id}/messages/{chatId}/{messageId}/reactions` |
| Send bulk (async batch)             | POST   | `/sessions/{id}/messages/send-bulk`                   |
| Get batch status                    | GET    | `/sessions/{id}/messages/batch/{batchId}`             |
| Cancel batch                        | POST   | `/sessions/{id}/messages/batch/{batchId}/cancel`      |
| Get message history                 | GET    | `/sessions/{id}/messages`                             |
| Create webhook                      | POST   | `/sessions/{id}/webhooks`                             |
| List webhooks (session)             | GET    | `/sessions/{id}/webhooks`                             |
| Get webhook                         | GET    | `/sessions/{id}/webhooks/{webhookId}`                 |
| Update webhook                      | PUT    | `/sessions/{id}/webhooks/{webhookId}`                 |
| Delete webhook                      | DELETE | `/sessions/{id}/webhooks/{webhookId}`                 |
| Test webhook delivery               | POST   | `/sessions/{id}/webhooks/{webhookId}/test`            |
| List all webhooks (cross-session)   | GET    | `/webhooks`                                           |
| Get contacts                        | GET    | `/sessions/{id}/contacts`                             |
| Check number on WhatsApp            | GET    | `/sessions/{id}/contacts/check/{number}`              |
| Get contact                         | GET    | `/sessions/{id}/contacts/{contactId}`                 |
| Block contact                       | POST   | `/sessions/{id}/contacts/{contactId}/block`           |
| Unblock contact                     | DELETE | `/sessions/{id}/contacts/{contactId}/block`           |
| Profile picture                     | GET    | `/sessions/{id}/contacts/{contactId}/profile-picture` |
| List groups                         | GET    | `/sessions/{id}/groups`                               |
| Create group                        | POST   | `/sessions/{id}/groups`                               |
| Get group                           | GET    | `/sessions/{id}/groups/{groupId}`                     |
| Add participants                    | POST   | `/sessions/{id}/groups/{groupId}/participants`        |
| Remove participants                 | DELETE | `/sessions/{id}/groups/{groupId}/participants`        |
| Promote participants                | POST   | `/sessions/{id}/groups/{groupId}/participants/promote` |
| Demote participants                 | POST   | `/sessions/{id}/groups/{groupId}/participants/demote` |
| Set group subject                   | PUT    | `/sessions/{id}/groups/{groupId}/subject`             |
| Set group description               | PUT    | `/sessions/{id}/groups/{groupId}/description`         |
| Leave group                         | POST   | `/sessions/{id}/groups/{groupId}/leave`               |
| Get invite code                     | GET    | `/sessions/{id}/groups/{groupId}/invite-code`         |
| Revoke invite code                  | POST   | `/sessions/{id}/groups/{groupId}/invite-code/revoke`  |
| List channels                       | GET    | `/sessions/{id}/channels`                             |
| Subscribe to channel                | POST   | `/sessions/{id}/channels/subscribe`                   |
| Get channel                         | GET    | `/sessions/{id}/channels/{channelId}`                 |
| Unsubscribe from channel            | DELETE | `/sessions/{id}/channels/{channelId}`                 |
| Get channel messages                | GET    | `/sessions/{id}/channels/{channelId}/messages`        |
| Get labels                          | GET    | `/sessions/{id}/labels`                               |
| Add label to chat                   | POST   | `/sessions/{id}/labels/chat/{chatId}`                 |
| Remove label from chat              | DELETE | `/sessions/{id}/labels/chat/{chatId}/{labelId}`       |
| Get status updates (stories)        | GET    | `/sessions/{id}/status`                               |
| Post text status                    | POST   | `/sessions/{id}/status/send-text`                     |
| Post image status                   | POST   | `/sessions/{id}/status/send-image`                    |
| Post video status                   | POST   | `/sessions/{id}/status/send-video`                    |
| Send catalog product                | POST   | `/sessions/{id}/messages/send-product`                |
| Send catalog link                   | POST   | `/sessions/{id}/messages/send-catalog`                |
| List catalog products               | GET    | `/sessions/{id}/catalog/products`                     |
| Create API key (admin)              | POST   | `/auth/api-keys`                                      |
| Validate API key                    | POST   | `/auth/validate`                                      |
| List audit logs                     | GET    | `/audit`                                              |
| Get statistics overview             | GET    | `/stats/overview`                                     |
| Get session statistics              | GET    | `/stats/sessions/{sessionId}`                         |
| Get settings                        | GET    | `/settings`                                           |
| Update settings                     | PUT    | `/settings`                                           |

> **Total: 92 endpoints** (some shared by both `v0.1.6` Swagger spec and the canonical `/api/docs-json`).
> For the complete always-up-to-date list, fetch `https://openwa.hostbol.lat/api/docs-json`.

## Common Pitfalls

1. **API key is via `X-API-Key` header**, NOT `Authorization: Bearer`. Although Swagger's `security: [bearer: []]` looks like JWT, it actually validates the `X-API-Key` header. Using `Authorization: Bearer <key>` will result in `401 UNAUTHORIZED`.

2. **`sessionId` is a UUID, not a name.** Always pass `session.id` (returned from `POST /sessions`) to subsequent endpoints. The human-readable `session.name` is for display only and is unique but cannot be used in URL paths.

3. **Sessions must be `start`-ed before sending.** A `POST /sessions` only creates the record. You must call `POST /sessions/{id}/start` to boot the underlying `whatsapp-web.js` client. Until status is `CONNECTED`, all `send-*` calls return `400 SESSION_NOT_READY`.

4. **Message ID format is `true|false_{chatId}_{whatsappId}`.** The `messageId` returned by send/reply endpoints and the `id` in webhook `data` follow the pattern `true_628123456789@c.us_3EB0ABC123`. The `true_` prefix means "from me" (outgoing); `false_` means incoming. Always split on `_` if you need the raw WhatsApp ID.

5. **Webhooks are session-scoped.** A webhook belongs to ONE session. To receive events from multiple sessions, register one webhook per session (or use `/api/webhooks` to list all and re-register as needed). The `events: ['*']` wildcard is supported for "all events from that session".

6. **Webhook idempotency: the `idempotencyKey` is content-based** (deterministic), not random. Same event re-fired = same key. The format is `{eventType}_{identifier}_{sessionId}`. Always de-dupe on this key (recommended: 24h TTL) before persisting, otherwise you will create duplicate Message rows.

7. **Webhook signature header is `X-OpenWA-Signature`** (NOT `X-OpenWA-Signature` SHA-256). Value format: `sha256=<hexdigest>` where `<hexdigest>` is `HMAC-SHA256(secret, rawBody)`. Compute over the RAW request body BEFORE JSON parsing.

8. **Headers on every webhook**: `X-OpenWA-Event`, `X-OpenWA-Delivery-Id`, `X-OpenWA-Idempotency-Key`, `X-OpenWA-Retry-Count` (starts at `0` for first attempt). Use these for routing, dedup, and observability.

9. **Media can be sent 3 ways**: `{url, mimetype, filename, caption}`, `{base64, mimetype, filename, caption}` (REQUIRED mimetype for base64), or `{path}` (server-local file, not accessible to remote clients). URLs must be `http` or `https`.

10. **`chatId` format is critical**: `628123456789@c.us` for individuals, `120363012345678@g.us` for groups, `123@newsletter` for channels. OpenWA uses the `whatsapp-web.js` canonical form — NOT Evolution's `591...@s.whatsapp.net` format.

11. **Bulk messages are async** with `202 Accepted` response and a `batchId`. Status via `GET /messages/batch/{batchId}`. Use `delayBetweenMessages` (ms) to avoid rate limits; default 3000ms, min 1000ms.

12. **No `forward` event in webhooks** — forwarded messages come through as normal `message.received` with a `forwarded` metadata flag inside `data`.

13. **Sessions can be started, stopped, and restarted** without losing the WhatsApp credentials. The credentials are persisted to the engine's `SESSION_DATA_PATH` (default `./data/sessions`) and re-used on `start`.

14. **No built-in outbound "message status" change event** beyond the standard `message.ack` (delivered, read). There is no separate "sent" event — successful `send-*` response is your "sent" signal.

15. **API key role enforcement**: `admin` (full access), `operator` (read+write, no API key management), `viewer` (read-only). Set role when creating the key; downgrade a leaked operator key to `viewer` rather than deleting (preserves audit trail).

16. **Rate limits are category-based** (configurable via env): session management 10 req/min, send message 60 req/min, read operations 120 req/min. Watch for `429` + `Retry-After` header.

17. **Webhook delivery has automatic retry** with exponential backoff (default: 3 retries, 5s delay). Configurable per-webhook via `retryCount` (0-5) at creation. After max retries the job is marked failed but no further event is sent.

18. **`messageId` in `data` is NOT the OpenWA internal UUID** — it's the WhatsApp-native ID from `whatsapp-web.js`. Don't confuse with the OpenWA `Message` table's `id` (UUID) — that one is internal and not exposed in webhooks.

19. **The `metadata` field on Message entity is `jsonb`/`json`** and is the only place to store engine-specific extras (quoted message info, forwarded flag, mentions, etc.). Don't try to read these from the top-level fields.

20. **Plugin system extends hooks** at well-defined points: `message:sending`, `message:sent`, `message:failed`, `webhook:before`, `webhook:queued`, `webhook:delivered`, `webhook:error`, `webhook:after`. Plugins can mutate payloads or cancel the action. See `src/core/hooks/HookManager`.

## Quick Authentication

```bash
# Header (recommended)
curl -H "X-API-Key: your-key" https://openwa.hostbol.lat/api/sessions

# Or via query param (less secure, only for browser fallback)
curl "https://openwa.hostbol.lat/api/sessions?apiKey=your-key"
```

## Quick Send-Text Example

```bash
curl -X POST https://openwa.hostbol.lat/api/sessions/sess_abc123/messages/send-text \
  -H "X-API-Key: your-key" \
  -H "Content-Type: application/json" \
  -d '{
    "chatId": "59169387181@c.us",
    "text": "Hello from OpenWA!"
  }'
```

## Quick Webhook Register

```bash
curl -X POST https://openwa.hostbol.lat/api/sessions/sess_abc123/webhooks \
  -H "X-API-Key: your-key" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://your-server.com/webhook",
    "events": ["message.received", "message.ack", "session.status"],
    "secret": "your-hmac-secret"
  }'
```

## Cross-Reference

For deeper details, see:
- `rules/api-endpoints.md` — All 92 endpoints with method, path, params, response, role required
- `rules/webhook-events.md` — Event types, payload structure, signature verification, idempotency
- `rules/message-types.md` — Send image, video, audio, document, sticker, location, contact, reactions, bulk
- `rules/authentication.md` — API key management, role hierarchy, CIDR whitelisting, expiry
- `rules/message-formats.md` — Internal Message entity, chatId formats, metadata, status mapping
- `rules/installation.md` — Docker, dev/local, profiles, env vars, QR flow
- `rules/client-extension.md` — Writing a Laravel client (or any HTTP client) with HMAC verify
- `rules/common-pitfalls.md` — 20+ documented pitfalls with workarounds
