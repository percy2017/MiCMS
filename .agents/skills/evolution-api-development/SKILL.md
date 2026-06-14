---
name: evolution-api-development
description: Expert guide for working with Evolution API v2 in this Laravel ChatBot module. Always fetch the official OpenAPI spec for exact schemas before writing client code.
---

## Documentation (fetch first)

- **OpenAPI v2 spec (JSON)**: `https://doc.evolution-api.com/openapi/openapi-v2.json`
- **Docs index (llms.txt)**: `https://doc.evolution-api.com/llms.txt`
- **Webhooks config (en)**: `https://doc.evolution-api.com/v2/en/configuration/webhooks`
- **Webhook Set reference**: `https://doc.evolution-api.com/v2/api-reference/webhook/set`
- **Webhook Get reference**: `https://doc.evolution-api.com/v2/api-reference/webhook/get`
- **Get Information (root)**: `https://doc.evolution-api.com/v2/api-reference/get-information`

## Project Architecture

This project uses a **Channel Driver pattern** (Strategy + Registry):

```
Webhook POST → EvolutionWebhookController → MessageIngestor → EvolutionChannel → DB + Broadcast
Admin reply  → ChannelManager → EvolutionChannel → EvolutionApiClient → Evolution API server
```

### Key Files

| File | Purpose |
|------|---------|
| `Modules/ChatBot/Channels/ChannelInterface.php` | Contract all channel drivers implement |
| `Modules/ChatBot/Channels/ChannelRegistry.php` | Maps `ChannelType` → driver instance |
| `Modules/ChatBot/Channels/EvolutionChannel.php` | WhatsApp channel driver (webhook processing + sending) |
| `Modules/ChatBot/Channels/Evolution/EvolutionMessageParser.php` | Pure parsing (detectType, extractMediaMeta, extractLocationMeta, extractContactMeta) |
| `Modules/ChatBot/Channels/Evolution/EvolutionMediaEnricher.php` | Downloads base64 from Evolution, saves to `media` table, persists `media_base64` in metadata as fallback |
| `Modules/ChatBot/Channels/Evolution/EvolutionUserLinker.php` | Auto-creates users from phone/jid |
| `Modules/ChatBot/Channels/Evolution/EvolutionApiClient.php` | HTTP client wrapper for Evolution REST API |
| `Modules/ChatBot/Http/Controllers/Api/Evolution/EvolutionWebhookController.php` | Receives webhook POSTs |
| `Modules/ChatBot/Services/MessageIngestor.php` | Dispatches inbound payloads to correct driver |
| `Modules/ChatBot/Services/ChannelManager.php` | Dispatches outbound messages via correct driver |
| `Modules/ChatBot/Models/Channel.php` | Stores per-instance config (encrypted `config` column) |

### Authentication

Each Evolution API instance uses its own API key. The header is:
```
apikey: {your-api-key}
```

In this project, credentials are stored encrypted in the `channels.config` JSON column:
```php
// Reading config
$config = $channel->config; // ['server_url' => '...', 'api_key' => '...', 'instance_name' => '...']

// Building client
$client = new EvolutionApiClient(
    serverUrl: $config['server_url'],
    apiKey: $config['api_key'],
    instanceName: $config['instance_name'],
);
```

## Inbound Webhook Flow (9 steps, super simple)

1. **Webhook arrives** at `POST /api/webhooks/evolution/{channel}` → `EvolutionWebhookController::handle()`
2. **Detect direction** → `$fromMe = $key['fromMe']` (true=outgoing admin echo, false=incoming user)
3. **Detect type** → `EvolutionMessageParser::detectType()` (text/image/video/audio/sticker/file/location/contact)
4. **Create conversation if not exists** → `Conversation::firstOrCreate([channel_id, external_id])`
5. **Create user if not exists** → `userLinker->linkOrCreate()` (by phone or whatsapp_jid)
6. **Save message** with type, content, role, metadata (uses `media_*` prefix keys)
7. **If media type (image/video/audio/sticker/file)** → `mediaEnricher->enrich()` downloads base64 from Evolution, saves to `media` table, sets `attachment_media_id`, ALSO persists `media_base64` in metadata as fallback
8. **If text with URLs** → `maybeDispatchLinkPreview()` dispatches `FetchLinkPreviewsJob` to fetch OpenGraph metadata
9. **Broadcast** → `ChatBotMessageReceived` event → Reverb → frontend updates sidebar + active chat in real time

## Webhook Configuration (Evolution v2 — CORRECT)

### Set Webhook — `POST /webhook/set/{instance}`

**Body** (per official spec):
```json
{
  "webhook": {
    "enabled": true,
    "url": "https://example.com/webhook",
    "webhookByEvents": false,
    "webhookBase64": false,
    "events": ["MESSAGES_UPSERT"]
  }
}
```

**Required fields**: `enabled`, `url`, `webhookByEvents`, `webhookBase64`, `events` (per the OpenAPI spec).

**Important reality check** (verified with curl against live server):
- The `webhookByEvents` and `webhookBase64` fields are accepted in the request body
- BUT on the find response they often come back as `false` regardless of what you sent
- Different Evolution v2 builds handle these inconsistently — treat the official find response as source of truth
- The ONLY fields that are reliably persisted in practice are: `enabled`, `url`, `events`
- **DO NOT display `webhookBase64` or `webhookByEvents` as user-configurable flags** — they are not really under our control via this API

**Events enum** (all valid values from the spec):
- `APPLICATION_STARTUP`
- `QRCODE_UPDATED`
- `MESSAGES_SET`
- `MESSAGES_UPSERT`
- `MESSAGES_UPDATE`
- `MESSAGES_DELETE`
- `SEND_MESSAGE`
- `CONTACTS_SET`
- `CONTACTS_UPSERT`
- `CONTACTS_UPDATE`
- `PRESENCE_UPDATE`
- `CHATS_SET`
- `CHATS_UPSERT`
- `CHATS_UPDATE`
- `CHATS_DELETE`
- `GROUPS_UPSERT`
- `GROUP_UPDATE`
- `GROUP_PARTICIPANTS_UPDATE`
- `CONNECTION_UPDATE`
- `CALL`
- `NEW_JWT_TOKEN`
- `TYPEBOT_START`
- `TYPEBOT_CHANGE_STATUS`

### Find Webhook — `GET /webhook/find/{instance}`

**Response** (per official spec — only these 3 fields are guaranteed):
```json
{
  "enabled": true,
  "url": "https://example.com/webhook",
  "events": ["MESSAGES_UPSERT"]
}
```

The live API may return extra fields (`webhookByEvents`, `webhookBase64`, `webhookBase64`, `headers`, `id`, `createdAt`, `updatedAt`, `instanceId`) but those are **NOT** part of the official contract — don't rely on them for UI state.

### Fetch Instances — `GET /instance/fetchInstances`

Returns the instance with the name informed in the parameter, or all the instances if empty. Used to list available WhatsApp numbers when creating a new inbox.

### Connection State — `GET /instance/connectionState/{instance}`

Returns the connection state of an instance (`open`, `close`, `connecting`).

## Messages Table Schema (UNIFIED — single column for all media)

The `messages` table has **ONE JSON column** for all media metadata: `metadata`.

### Columns
- `id`, `conversation_id`, `role`, `type`, `content`, `external_id`
- **`metadata` (JSON)** — single source of truth for all media data (uses `media_*` prefix)
- `attachment_media_id` (FK to `media` table) — for downloadable media
- `delivered_at`, `read_at`, timestamps, soft delete

### Metadata JSON Convention (media_* prefix)

```json
// Text (no media, no URL)
{ "media_kind": "text" }

// Text with link (preview scraped by Chromium)
{
  "media_kind": "link",
  "media_external_url": "https://...",
  "media_preview": { "title": "...", "description": "...", "image": "...", "site_name": "...", "favicon": "...", "final_url": "..." }
}

// Image / Video / Audio / Sticker / File
{
  "media_kind": "image",
  "media_url": "https://...",          // temporary WhatsApp URL (mmg.whatsapp.net)
  "media_mimetype": "image/jpeg",
  "media_caption": "...",
  "media_size": 12345,
  "media_base64": "UklGR...",          // ONLY after mediaEnricher runs successfully
  "media_filename": "photo.jpg",
  "media_stored_at": "2026-..."
}

// Location (map)
{
  "media_kind": "location",
  "media_latitude": -17.78,
  "media_longitude": -63.18,
  "media_name": "Santa Cruz",
  "media_address": "Av. Las Américas, Bolivia",
  "media_url": "https://maps.google.com/...",
  "media_thumbnail": "base64..."
}

// Contact
{
  "media_kind": "contact",
  "media_name": "Juan Pérez",
  "media_phone": "+59172811368",
  "media_vcard": "BEGIN:VCARD..."
}
```

### Naming convention (do NOT break)
- `media_kind` — discriminator
- `media_url` — public/temporary URL
- `media_mimetype` — MIME type
- `media_caption` — text caption (for image/video)
- `media_size` — bytes
- `media_duration` — seconds (for video/audio)
- `media_thumbnail` — base64 thumbnail
- `media_filename` — original filename
- `media_base64` — base64 (LARGE, only persisted after enricher success)
- `media_external_url` — external URL (for links)
- `media_preview` — OpenGraph metadata (for links)
- `media_latitude` / `media_longitude` / `media_name` / `media_address` — location
- `media_phone` / `media_vcard` — contact
- `media_enrichment_failed_at` — ISO timestamp of last failed enrichment
- `media_stored_at` — ISO timestamp of last successful enrichment

## MessageType Enum (Modules/ChatBot/Enums/MessageType.php)

```php
enum MessageType: string {
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case File = 'file';
    case Sticker = 'sticker';
    case Location = 'location';
    case Contact = 'contact';

    public function isMediaDownloadable(): bool {
        return match ($this) {
            self::Image, self::Video, self::Audio, self::File, self::Sticker => true,
            default => false,
        };
    }
}
```

**Always check `isMediaDownloadable()`** before calling `mediaEnricher->enrich()`.

## Quick Reference — Most Used Endpoints

| Action | Method | Endpoint |
|--------|--------|----------|
| Send text | POST | `/message/sendText/{instance}` |
| Send media | POST | `/message/sendMedia/{instance}` |
| Send audio | POST | `/message/sendAudio/{instance}` |
| Send sticker | POST | `/message/sendSticker/{instance}` |
| Send location | POST | `/message/sendLocation/{instance}` |
| Send contact | POST | `/message/sendContact/{instance}` |
| Send button | POST | `/message/sendButton/{instance}` |
| Send list | POST | `/message/sendList/{instance}` |
| Send poll | POST | `/message/sendPoll/{instance}` |
| Send reaction | POST | `/message/sendReaction/{instance}` |
| Send status | POST | `/message/sendStatus/{instance}` |
| **Fetch instances** | GET | `/instance/fetchInstances` |
| **Connection state** | GET | `/instance/connectionState/{instance}` |
| Create instance | POST | `/instance/create` |
| Delete instance | DELETE | `/instance/delete/{instance}` |
| Logout | PUT | `/instance/logout/{instance}` |
| Restart | PUT | `/instance/restart/{instance}` |
| Set presence | PUT | `/instance/setPresence/{instance}` |
| Find contacts | GET | `/chat/findContacts/{instance}` |
| Find chats | GET | `/chat/findChats/{instance}` |
| Find messages | GET | `/chat/findMessages/{instance}` |
| Mark as read | PUT | `/chat/markAsRead/{instance}` |
| Check WhatsApp | POST | `/chat/checkIsWhatsApp/{instance}` |
| **Get base64 media** | POST | `/chat/getBase64FromMediaMessage/{instance}` |
| Profile picture | GET | `/chat/fetchProfilePictureUrl/{instance}` |
| **Fetch profile (pushName)** | POST | `/chat/fetchProfile/{instance}` |
| **Set webhook** | POST | `/webhook/set/{instance}` |
| **Find webhook** | GET | `/webhook/find/{instance}` |
| Settings | GET | `/settings/find/{instance}` |

For the complete list of all 50+ endpoints, see `rules/api-endpoints.md`.

## Sub-Rules (Detailed Reference)

| File | Covers |
|------|--------|
| `rules/api-endpoints.md` | Full endpoint reference with request/response shapes |
| `rules/webhook-events.md` | All webhook events, payloads, filtering logic, and DB mapping |
| `rules/client-extension.md` | How to add new methods to `EvolutionApiClient` |
| `rules/channel-architecture.md` | `ChannelInterface` contract, registry, extending with new features |
| `rules/message-types.md` | All `messageType` values, extraction logic, supported media types |
| `rules/common-pitfalls.md` | 20+ known pitfalls from production (data corruption, payload size, etc.) |

## How to Extend

### Add a new API method to the client

```php
// In EvolutionApiClient.php, add:
public function sendReaction(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->post("{$this->serverUrl}/message/sendReaction/{$this->instanceName}", $params);
}
```

See `rules/client-extension.md` for the full pattern.

### Handle a new webhook event

```php
// In EvolutionChannel::processIncoming(), add to the event check:
if (! in_array($event, ['messages.upsert', 'messages.update', 'connection.update', 'send.message', 'call'])) {
    return null;
}
```

See `rules/webhook-events.md` for all events and their payloads.

### Add a new message type

```php
// In the content match inside processIncoming():
! empty($messageData['pollMessage']) => $messageData['pollMessage']['name'] ?? '[Encuesta]',

// In the type assignment:
} elseif (! empty($messageData['pollMessage'])) {
    $type = MessageType::Text; // or add a Poll enum value
}
```

See `rules/message-types.md` for the full type reference.

## Common Pitfalls (20+ from production)

See `rules/common-pitfalls.md` for the complete list. Top pitfalls:

1. **Wrong endpoint paths** — The official endpoints are:
   - `POST /webhook/set/{instance}` (NOT `/webhook/setWebhook/`)
   - `GET /webhook/find/{instance}` (NOT `/webhook/findWebhook/`)
   - `GET /instance/connectionState/{instance}` (NOT `/instance/connect/`)
   - Always verify with curl before trusting third-party code or chat exports

2. **Missing `apikey` header** — Every request MUST include `apikey` header. Without it, you get 401.

3. **Confusing `remoteJid` with phone number** — `remoteJid` can be `59168964000@s.whatsapp.net` (user) or `59168964000@g.us` (group). Extract the number before `@`.

4. **Not filtering `fromMe`** — Webhooks include your own outgoing messages. Always check `$data['key']['fromMe'] === false` to avoid loops. **For our project: process BOTH directions** (admin echoes need to be saved too).

5. **Ignoring duplicate messages** — Evolution API may re-send webhooks. Check `external_id` before creating a Message.

6. **`send.message` only fires for outgoing** — Use `messages.upsert` with `fromMe: true` if you need outgoing message tracking, or handle `send.message` separately.

7. **`connecting` appears in two events** — Both `connection.update` and `APPLICATION_STARTUP` can emit `connecting` state.

8. **Not all `messageType` values have content** — Types like `albumMessage`, `protocolMessage`, `groupStatusMentionMessage` should be skipped explicitly.

9. **Instance must exist before sending** — Call `fetchInstances` to verify the instance name matches an active instance.

10. **Media URLs are temporary** — WhatsApp media URLs (`mmg.whatsapp.net`) expire in ~5 minutes. Download and store media promptly via `getBase64FromMediaMessage`.

11. **Status values differ between events** — `messages.update` uses `SERVER_ACK`/`DELIVERY_ACK`/`READ`, while your DB uses `sent`/`delivered`/`read`. Always map them.

12. **FromMe=true semantics inverted** — `remoteJid` is the **recipient** (client) when `fromMe=true`. `pushName` is the **admin's own name** (NOT the client's). Don't use `pushName` to create the user in `fromMe=true` branch.

13. **`getBase64FromMediaMessage` can fail** — Returns HTTP 400 with "TypeError: Cannot read properties of undefined" for expired media URLs. The enricher handles this by setting `media_enrichment_failed_at`.

14. **Link preview job must be dispatched from webhook** — don't wait for admin to navigate to the chat. The `maybeDispatchLinkPreview()` method handles this automatically.

15. **`fetchProfile` with admin's own instance returns empty `name`** — the admin's own contact doesn't have a profile in their own instance. Use a different instance to get the real name.

16. **`ChatController::dispatchMissingLinkPreviews` must filter by `type===Text`** — Iterating over ALL messages will set `media_kind=link` on stickers/images/etc.

17. **`media_base64` is LARGE** (~10-100KB) — Don't send it via socket broadcast. `ChatBotMessageReceived::broadcastWith()` should send the metadata WITHOUT `media_base64`. The frontend gets the full data via `ChatController::index` reload.

18. **`webhookByEvents` and `webhookBase64` are NOT user-configurable via the API** — Despite the spec saying they're accepted in `set`, the live server ignores them on `find`. Don't show them in the UI as user-controlled flags.

19. **Stickers often DON'T include `media_url` in webhook payload** — Only `getBase64FromMediaMessage` returns the base64. After expiration (>5 min), the sticker is lost.

20. **The `webhook.find` response from live server returns extra fields** (`webhookByEvents`, `webhookBase64`, `id`, `headers`, `createdAt`, `updatedAt`, `instanceId`) that are NOT in the official spec. **Only rely on `enabled`, `url`, `events`** for the source of truth.

## Testing Notes

- 402+ tests passing (Pest 4)
- All SQLite-compatible (no raw `JSON_EXTRACT` in queries — use PHP `filter()` on collections)
- Test the webhook flow with `EvolutionChannelUserLinkingTest`, `EvolutionChannelMessageTypesTest`, `EvolutionMediaEnricherTest`
- Test link preview with `RefetchLinkPreviewsTest` (uses `metadata.media_preview`)
- Use `Http::fake()` to mock Evolution API calls
- For media enrichment tests, provide base64 of small test images/audios in `tests/Fixtures/`

## Frontend Rendering (resources/js/components/chat/MessageBubble.tsx)

The `MessageBubble` component renders different bubbles based on `m.type`:
- `text` → `MessageBody` (text + link previews from `m.metadata?.media_preview`)
- `image` → `ImageBubble`
- `video` → `VideoBubble`
- `audio` → `AudioBubble`
- `sticker`, `file` → `FileBubble`
- `location` → inline render with coordinates + Google Maps link
- `contact` → inline render with avatar + name + phone (clickable `tel:`)

**Important**: `hasMedia = Boolean(m.attachment_url) || Boolean(m.metadata?.media_url)` (fallback for when enricher failed but external URL is available).

## E2E Smoke Test (verified in production)

```bash
# 1. Send text from phone → webhook → message created
# 2. Send image from phone → webhook → message + media downloaded
# 3. Send sticker from phone → webhook → message + media downloaded
# 4. Send link from phone → webhook → message + FetchLinkPreviewsJob dispatched
# 5. Open /admin/chats → renders all messages with metadata
# 6. Receive new message → socket broadcasts → sidebar updates
```

All steps verified working. Socket works for non-media and small media. Large media (>50KB base64) needs frontend reload after broadcast.

## Inbox Creation Flow (Evolution)

1. User visits `/admin/canales/evolution` (one page, no separate selector)
2. Page loads with `available` data from `GET /instance/fetchInstances` (server-side via Evolution strategy)
3. User clicks an instance in the list → `router.visit('/admin/canales/evolution?instance_name=X')` (SSR navigation)
4. Server calls `GET /webhook/find/X` to fetch current webhook config
5. Card shows: URL, events (chips), enabled badge (green/red)
6. User clicks "Guardar" → `POST /admin/canales/evolution` → backend calls `POST /webhook/set/{instance}` with `events: ['MESSAGES_UPSERT']`
7. On success, `Channel` is created in DB and admin is redirected to `/admin/canales`
