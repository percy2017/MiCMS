# Evolution API v2 Development

Expert guide for working with Evolution API v2 in this Laravel ChatBot module.

## Documentation

Always fetch the official OpenAPI spec for exact request/response schemas:
- **JSON spec**: `https://doc.evolution-api.com/openapi/openapi-v2.json`
- **Official docs**: `https://doc.evolution-api.com/v2/api-reference/get-information`

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
| `app/Jobs/FetchLinkPreviewsJob.php` | Async job: scrapes URLs via Node+Chromium, saves to `metadata.media_preview` |
| `app/Services/LinkPreviewService.php` | Fetches OpenGraph metadata, SKIP_DOMAINS list, SCRIPT_TIMEOUT=180s |
| `Modules/ChatBot/Http/Controllers/Admin/ChatController.php` | `index/show/reply/read/destroy/update`. `attachmentData()` resolves `attachment_url` (priority: base64 > Media row > external URL) |
| `Modules/ChatBot/Events/ChatBotMessageReceived.php` | ShouldBroadcastNow event to `private-chatbot.admin` |
| `Modules/ChatBot/Events/LinkPreviewsReady.php` | Broadcasts when link preview is ready |
| `Modules/ChatBot/Models/Message.php` | Eloquent model with `metadata` JSON column (UNIFIED for all media data) |

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

## Inbound Webhook Flow (5 steps, super simple)

1. **Webhook arrives** at `POST /api/webhooks/evolution/{channel}` → `EvolutionWebhookController::handle()`
2. **Detect direction** → `$fromMe = $key['fromMe']` (true=outgoing admin echo, false=incoming user)
3. **Detect type** → `EvolutionMessageParser::detectType()` (text/image/video/audio/sticker/file/location/contact)
4. **Create conversation if not exists** → `Conversation::firstOrCreate([channel_id, external_id])`
5. **Create user if not exists** → `userLinker->linkOrCreate()` (by phone or whatsapp_jid)
6. **Save message** with type, content, role, metadata (uses `media_*` prefix keys)
7. **If media type (image/video/audio/sticker/file)** → `mediaEnricher->enrich()` downloads base64 from Evolution, saves to `media` table, sets `attachment_media_id`, ALSO persists `media_base64` in metadata as fallback
8. **If text with URLs** → `maybeDispatchLinkPreview()` dispatches `FetchLinkPreviewsJob` to fetch OpenGraph metadata
9. **Broadcast** → `ChatBotMessageReceived` event → Reverb → frontend updates sidebar + active chat in real time

## Messages Table Schema (UNIFIED — single column for all media)

The `messages` table has **ONE JSON column** for all media metadata: `metadata`.

### Columns
- `id`, `conversation_id`, `role`, `type`, `content`, `external_id`
- **`metadata` (JSON)** — single source of truth for all media data (uses `media_*` prefix)
- `attachment_media_id` (FK to `media` table) — for downloadable media
- `delivered_at`, `read_at`, timestamps, soft delete

### ⚠️ The `link_previews` column was DROPPED (migration: `2026_06_13_030000_drop_link_previews_from_messages_table`)

All link preview data now lives in `metadata.media_preview`. The `link_previews` column no longer exists.

## Metadata JSON Convention (media_* prefix)

All keys use the `media_*` prefix. The `media_kind` field is the discriminator.

```json
// Text (no media, no URL)
{
  "media_kind": "text"
}

// Text with link (preview scraped by Chromium)
{
  "media_kind": "link",
  "media_external_url": "https://...",
  "media_preview": {
    "title": "...",
    "description": "...",
    "image": "...",
    "site_name": "...",
    "favicon": "...",
    "final_url": "..."
  }
}

// Image / Video / Audio / Sticker / File
{
  "media_kind": "image",
  "media_url": "https://...",          // temporary WhatsApp URL (mmg.whatsapp.net)
  "media_mimetype": "image/jpeg",
  "media_caption": "...",
  "media_size": 12345,
  "media_base64": "UklGR...",          // ONLY after mediaEnricher runs successfully (large!)
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
  "media_phone": "+59172811368",       // E.164 format with +, min 8 digits
  "media_vcard": "BEGIN:VCARD..."
}
```

### Naming convention (do NOT break)
- `media_kind` — discriminator (`text` | `link` | `image` | `video` | `audio` | `sticker` | `file` | `location` | `contact`)
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

**Always check `isMediaDownloadable()`** before calling `mediaEnricher->enrich()`. Location and Contact have their own data in the webhook payload (no download needed).

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
| Fetch instances | GET | `/instance/fetchInstances` |
| Connection state | GET | `/instance/connectionState/{instance}` |
| Create instance | POST | `/instance/create` |
| Delete instance | DELETE | `/instance/delete/{instance}` |
| Logout | PUT | `/instance/logout/{instance}` |
| Restart | PUT | `/instance/restart/{instance}` |
| Set presence | PUT | `/instance/setPresence/{instance}` |
| Find contacts | GET | `/chat/findContacts/{instance}` |
| Find chats | GET | `/chat/findChats/{instance}` |
| Find messages | GET | `/chat/findAllMessages/{instance}` |
| Mark as read | PUT | `/chat/markAsRead/{instance}` |
| Check WhatsApp | POST | `/chat/checkIsWhatsApp/{instance}` |
| **Get base64 media** | POST | `/chat/getBase64FromMediaMessage/{instance}` |
| Profile picture | GET | `/chat/fetchProfilePictureUrl/{instance}` |
| **Fetch profile (pushName)** | POST | `/chat/fetchProfile/{instance}` |
| Fetch groups | GET | `/group/fetchAllGroups/{instance}` |
| Create group | POST | `/group/create/{instance}` |
| Group participants | GET | `/group/findParticipants/{instance}` |
| Update participant | PUT | `/group/updateParticipant/{instance}` |
| Set webhook | PUT | `/webhook/setWebhook/{instance}` |
| Find webhook | GET | `/webhook/findWebhook/{instance}` |
| Settings | GET/PUT | `/settings/findSettings/{instance}` |

For the complete list of all 50+ endpoints, see `rules/api-endpoints.md`.

## Sub-Rules (Detailed Reference)

| File | Covers |
|------|--------|
| `rules/api-endpoints.md` | Full endpoint reference with request/response shapes |
| `rules/webhook-events.md` | All webhook events, payloads, filtering logic, and DB mapping |
| `rules/client-extension.md` | How to add new methods to `EvolutionApiClient` |
| `rules/channel-architecture.md` | `ChannelInterface` contract, registry, extending with new features |
| `rules/message-types.md` | All `messageType` values, extraction logic, supported media types |
| `rules/common-pitfalls.md` | 15+ known pitfalls from production (data corruption, payload size, etc.) |

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

## Common Pitfalls (15+ from production)

See `rules/common-pitfalls.md` for the complete list. Top 5:

1. **Missing `apikey` header** — Every request MUST include `apikey` header. Without it, you get 401.
2. **Confusing `remoteJid` with phone number** — `remoteJid` can be `59168964000@s.whatsapp.net` (user) or `59168964000@g.us` (group). Extract the number before `@`.
3. **Not filtering `fromMe`** — Webhooks include your own outgoing messages. Always check `$data['key']['fromMe'] === false` to avoid loops. **For our project: process BOTH directions** (admin echoes need to be saved too).
4. **Ignoring duplicate messages** — Evolution API may re-send webhooks. Check `external_id` before creating a Message.
5. **`send.message` only fires for outgoing** — Use `messages.upsert` with `fromMe: true` if you need outgoing message tracking, or handle `send.message` separately.
6. **`connecting` appears in two events** — Both `connection.update` and `APPLICATION_STARTUP` can emit `connecting` state.
7. **Not all `messageType` values have content** — Types like `albumMessage`, `protocolMessage`, `groupStatusMentionMessage` should be skipped explicitly.
8. **Instance must exist before sending** — Call `fetchInstances` to verify the instance name matches an active instance.
9. **Media URLs are temporary** — WhatsApp media URLs (`mmg.whatsapp.net`) expire in ~5 minutes. Download and store media promptly via `getBase64FromMediaMessage`.
10. **Status values differ between events** — `messages.update` uses `SERVER_ACK`/`DELIVERY_ACK`/`READ`, while your DB uses `sent`/`delivered`/`read`. Always map them.
11. **FromMe=true semantics inverted** — `remoteJid` is the **recipient** (client) when `fromMe=true`. `pushName` is the **admin's own name** (NOT the client's). Don't use `pushName` to create the user in `fromMe=true` branch.
12. **`getBase64FromMediaMessage` can fail** — Returns HTTP 400 with "TypeError: Cannot read properties of undefined" for expired media URLs. The enricher handles this by setting `media_enrichment_failed_at`.
13. **Link preview job must be dispatched from webhook** — don't wait for admin to navigate to the chat. The `maybeDispatchLinkPreview()` method handles this automatically.
14. **`fetchProfile` with admin's own instance returns empty `name`** — the admin's own contact doesn't have a profile in their own instance. Use a different instance to get the real name.
15. **`ChatController::dispatchMissingLinkPreviews` must filter by `type===Text`** — Iterating over ALL messages will set `media_kind=link` on stickers/images/etc. (broke our production in 2026-06).
16. **`media_base64` is LARGE** (~10-100KB) — Don't send it via socket broadcast. `ChatBotMessageReceived::broadcastWith()` should send the metadata WITHOUT `media_base64` (or limit to a small subset). The frontend gets the full data via `ChatController::index` reload.

## Testing Notes

- 366 tests passing (Pest 4)
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

All media bubbles use `m.attachment_url` (from `Media::url()` via `Message::attachment` relation). The `metadata` is also passed for fallback or extra context.

## Data Cleanup

Old data with deprecated keys (`location_latitude`, `contact_displayName`, `link_previews` column) needs cleanup. One-shot script in `scripts/clean-metadata.php` or use tinker:

```php
// Clean non-text messages with incorrect media_kind
$msgs = DB::table("messages")->get();
foreach ($msgs as $row) {
    $meta = json_decode($row->metadata, true);
    if (($meta["media_kind"] ?? null) === "link" && $row->type !== "text") {
        unset($meta["media_kind"], $meta["media_preview"], $meta["media_external_url"]);
        DB::table("messages")->where("id", $row->id)->update(["metadata" => json_encode($meta)]);
    }
}
```

## E2E Smoke Test (what works in production)

```bash
# 1. Send text from phone → webhook → message created
# 2. Send image from phone → webhook → message + media downloaded
# 3. Send sticker from phone → webhook → message + media downloaded
# 4. Send link from phone → webhook → message + FetchLinkPreviewsJob dispatched
# 5. Open /admin/chats → renders all messages with metadata
# 6. Receive new message → socket broadcasts → sidebar updates
```

All steps verified working as of 2026-06-13. Socket works for non-media and small media. Large media (>50KB base64) needs frontend reload after broadcast.
