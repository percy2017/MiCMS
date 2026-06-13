<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/ai (AI) - v0
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/wayfinder (WAYFINDER) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/echo-react (ECHO_REACT) - v2
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- laravel-echo (ECHO) - v2
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

=== chatbot-evolution rules ===

# ChatBot Module - Evolution Channel Architecture

This module implements a **Channel Driver pattern** (Strategy + Registry) for processing WhatsApp messages via Evolution API v2.

## Inbound Webhook Flow (Super Simple)

1. **Webhook arrives** at `POST /api/webhooks/evolution/{channel}` → `EvolutionWebhookController::handle()`
2. **Detect direction** → `$fromMe = $key['fromMe']` (true=outgoing admin, false=incoming user)
3. **Detect type** → `EvolutionMessageParser::detectType()` (text/image/video/audio/sticker/file/location/contact)
4. **Create conversation if not exists** → `Conversation::firstOrCreate([channel_id, external_id])`
5. **Create user if not exists** → `userLinker->linkOrCreate()` (by phone or whatsapp_jid)
6. **Save message** → with type, content, role, metadata (uses `media_*` prefix keys)
7. **If media type (image/video/audio/sticker/file)** → `mediaEnricher->enrich()` downloads base64 from Evolution, saves to `media` table, sets `attachment_media_id`, **ALSO persists `media_base64` in metadata as fallback**
8. **If text with URLs** → `maybeDispatchLinkPreview()` dispatches `FetchLinkPreviewsJob` to fetch OpenGraph metadata
9. **Broadcast** → `ChatBotMessageReceived` event → Reverb → frontend updates sidebar + active chat in real time

## Key Files

| File | Purpose |
|------|---------|
| `Modules/ChatBot/Channels/Evolution/EvolutionChannel.php` | Orchestrator (9 collaborators) — `processIncoming()` dispatches to user/conversation/media/job/broadcast |
| `Modules/ChatBot/Channels/Evolution/EvolutionMessageParser.php` | Pure parsing: `detectType()`, `extractMediaMeta()`, `extractLocationMeta()`, `extractContactMeta()` |
| `Modules/ChatBot/Channels/Evolution/EvolutionMediaEnricher.php` | Downloads base64 from Evolution, saves to `media` table, persists `media_base64` in metadata as fallback |
| `Modules/ChatBot/Channels/Evolution/EvolutionUserLinker.php` | Auto-creates users from phone/jid (used in BOTH `fromMe` branches) |
| `Modules/ChatBot/Channels/Evolution/EvolutionApiClient.php` | HTTP client wrapper for Evolution REST API |
| `app/Jobs/FetchLinkPreviewsJob.php` | Async job: scrapes URLs via Node+Chromium, saves to `metadata.media_preview` |
| `app/Services/LinkPreviewService.php` | Fetches OpenGraph metadata, `SKIP_DOMAINS` list, `SCRIPT_TIMEOUT=180s` |
| `Modules/ChatBot/Http/Controllers/Api/Evolution/EvolutionWebhookController.php` | Receives webhook POSTs |
| `Modules/ChatBot/Http/Controllers/Admin/ChatController.php` | `index/show/reply/read/destroy/update`. `attachmentData()` resolves `attachment_url` (priority: `media_base64` > `Media` row > `media_url` external) |
| `Modules/ChatBot/Events/ChatBotMessageReceived.php` | ShouldBroadcastNow event to `private-chatbot.admin` |
| `Modules/ChatBot/Events/LinkPreviewsReady.php` | Broadcasts when link preview is ready |
| `Modules/ChatBot/Models/Message.php` | Eloquent model with `metadata` JSON column (UNIFIED for all media data) |
| `Modules/ChatBot/Enums/MessageType.php` | Enum with `isMediaDownloadable()` method |

## Messages Table Schema (UNIFIED)

The `messages` table has **ONE JSON column** for all media metadata: `metadata`.

### Columns
- `id`, `conversation_id`, `role`, `type`, `content`, `external_id`
- **`metadata` (JSON)** — single source of truth for all media data (uses `media_*` prefix)
- `attachment_media_id` (FK to `media` table) — for downloadable media
- `delivered_at`, `read_at`, timestamps, soft delete

### ⚠️ The `link_previews` column was DROPPED (migration: `2026_06_13_030000_drop_link_previews_from_messages_table`)

All link preview data now lives in `metadata.media_preview`. The `link_previews` column no longer exists in the schema.

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
  "media_url": "https://...",            // temporary WhatsApp URL (mmg.whatsapp.net)
  "media_mimetype": "image/jpeg",
  "media_caption": "...",
  "media_size": 12345,
  "media_base64": "UklGR...",            // ONLY after mediaEnricher runs successfully (large!)
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
  "media_phone": "+59172811368",          // E.164 format with +, min 8 digits
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

## Evolution API Endpoints Used

| Action | Endpoint | Method |
|--------|----------|--------|
| Send text | `/message/sendText/{instance}` | POST |
| Send media | `/message/sendMedia/{instance}` | POST |
| Send sticker | `/message/sendSticker/{instance}` | POST |
| Get base64 media | `/chat/getBase64FromMediaMessage/{instance}` | POST |
| Fetch profile (pushName) | `/chat/fetchProfile/{instance}` | POST |
| Fetch instances | `/instance/fetchInstances` | GET |
| Connection state | `/instance/connectionState/{instance}` | GET |
| Set webhook | `/webhook/setWebhook/{instance}` | PUT |
| Find webhook | `/webhook/findWebhook/{instance}` | GET |

## Common Pitfalls (20+ from production)

1. **`fromMe=true` semantics inverted** — `remoteJid` is the **recipient** (client), `pushName` is the **admin's own name**. Don't use `pushName` to create the user in `fromMe=true` branch.
2. **Media URLs expire fast** (~5 min) — `mediaEnricher` must run immediately, not later. For stickers: the URL is often NOT in the payload, so `getBase64FromMediaMessage` is the only way to get the data.
3. **`chat.whatsapp.com` blocks scraping** — In `LinkPreviewService::SKIP_DOMAINS`. Returns `error: "domain_skipped"`. SCRIPT_TIMEOUT is 180s.
4. **Don't call `mediaEnricher` for Location/Contact** — they have no media to download. Use `isMediaDownloadable()` check.
5. **Messages with `user_id=null` are orphan conversations** — the admin sending to themselves creates phantom users. The `EvolutionUserLinker` is now called in BOTH `fromMe` branches to backfill `user_id`.
6. **Socket doesn't update sidebar by default** — the `useChatSync.onMessage` handler must update `filteredConversations` to add new conversations to the sidebar and increment `unread_by_admin` for `role=user` messages.
7. **Link preview job must be dispatched from webhook** — don't wait for admin to navigate to the chat. The `maybeDispatchLinkPreview()` method handles this automatically.
8. **The `firstOrCreate` in `Conversation`** uses `[channel_id, external_id]` as unique key. If `external_id` changes (e.g. admin writing to themselves), a new conversation is created.
9. **`fetchProfile` with admin's own instance returns empty `name`** — the admin's own contact doesn't have a profile in their own instance. Use a different instance to get the real name.
10. **`getBase64FromMediaMessage` can fail** — Returns HTTP 400 with "TypeError: Cannot read properties of undefined (reading 'key')" for expired media URLs. The enricher handles this by setting `media_enrichment_failed_at`.
11. **`ChatController::dispatchMissingLinkPreviews` MUST filter by `type===Text`** — Iterating over ALL messages will set `media_kind=link` on stickers/images/etc. (broke our production in 2026-06).
12. **`media_base64` is LARGE** (~10-100KB) — Don't include it in `ChatBotMessageReceived::broadcastWith()` (exceeds Pusher/Reverb payload limits). The frontend gets the full data via `ChatController::index` reload.
13. **Stickers often DON'T include `media_url` in webhook payload** — Only `getBase64FromMediaMessage` returns the base64. After expiration (>5 min), the sticker is lost.
14. **Location phone extraction regex** — Use `/TEL[^:]*:([^\r\n]+)/` + `preg_replace('/[^+\d]/', '')` to extract E.164 format. The old regex `/TEL[^:]*:(?:[^+\d]*\+?(\d+))/` truncated at spaces.
15. **The admin writing to themselves creates a phantom user** — When `fromMe=true` and `remoteJid=admin's_own_jid`, the system creates a user with `name=''` and `phone=admin's_own_number`. Workaround: compare `remoteJid` with `owner_jid` of the channel (requires `connection.update` to capture `wuid` as `owner_jid`).
16. **`media_kind=link` for text without URLs is misleading** — When the `FetchLinkPreviewsJob` processes a text message that has no URLs, set `media_kind=text` (not `link`). `media_kind=link` should only be set when there IS a preview (or `media_preview.error='domain_skipped'`).
17. **SQLite doesn't support `JSON_EXTRACT` with `JSON_UNQUOTE`** — For migrations or queries that need to extract JSON values, use PHP loops on collections.
18. **Webhooks may not include `media_url` for stickers** — Always call `mediaEnricher` for `isMediaDownloadable()` types, even if the parser extracted `media_url` (stickers typically don't have one).
19. **The `ChatController::attachmentData` resolves attachment_url with priority**:
    1. `metadata.media_base64` → data URL (highest priority, inline display)
    2. `Message.attachment` (Media local) → URL pública
    3. `metadata.media_url` → URL externa (fallback, may be expired)
    4. `null` if nothing
20. **The `MessageBubble.tsx` hasMedia check** — `hasMedia = Boolean(m.attachment_url) || Boolean(m.metadata?.media_url)`. The fallback to `media_url` ensures stickers/images render even if the enricher failed.

## Testing Notes

- 366 tests passing (Pest 4)
- All SQLite-compatible (no raw `JSON_EXTRACT` in queries — use PHP `filter()` on collections)
- Test the webhook flow with `EvolutionChannelUserLinkingTest`, `EvolutionChannelMessageTypesTest`, `EvolutionMediaEnricherTest`
- Test link preview with `RefetchLinkPreviewsTest` (uses `metadata.media_preview`)
- Use `Http::fake()` to mock Evolution API calls
- For media enrichment tests, provide base64 of small test images/audios

## Frontend Rendering (resources/js/components/chat/MessageBubble.tsx)

The `MessageBubble` component renders different bubbles based on `m.type`:

- `text` → `MessageBody` (text + link previews from `m.metadata?.media_preview`)
- `image` → `ImageBubble`
- `video` → `VideoBubble`
- `audio` → `AudioBubble`
- `sticker`, `file` → `FileBubble`
- `location` → inline render with coordinates + Google Maps link
- `contact` → inline render with avatar + name + phone (clickable `tel:`)

**`hasMedia` check** (line ~25 of MessageBubble.tsx):
```typescript
const fallbackMediaUrl = (m.metadata?.media_url as string | undefined) ?? null;
const hasMedia = Boolean(m.attachment_url) || Boolean(fallbackMediaUrl);
```

This ensures stickers/images render even if the enricher failed but the external URL is still valid.

All media bubbles use `m.attachment_url` (from `Media::url()` via `Message::attachment` relation). The `metadata` is also passed for fallback or extra context.

## Socket / Real-time Updates

- **Channel**: `private-chatbot.admin`
- **Event**: `ChatBotMessageReceived` (ShouldBroadcastNow)
- **Echo endpoint**: `/broadcasting/auth`
- **Frontend listener**: `useChatSync.onMessage` in `resources/js/hooks/use-chat-sync.ts`
- **Important**: `ChatBotMessageReceived::broadcastWith()` does NOT include `media_base64` (too large for socket). Frontend falls back to `router.reload({ only: ['active'] })` for media messages.

## Data Cleanup (one-shot script)

If you have old data with deprecated keys (`location_latitude`, `contact_displayName`, `link_previews` column, `media_kind=link` on non-text msgs), use this PHP loop:

```php
use Illuminate\Support\Facades\DB;

$count = 0;
$msgs = DB::table("messages")->get(["id", "type", "metadata"]);
foreach ($msgs as $row) {
    $meta = json_decode($row->metadata, true);
    if (!is_array($meta)) continue;

    // Clean: non-text messages with media_kind=link
    if (($meta["media_kind"] ?? null) === "link" && $row->type !== "text") {
        unset($meta["media_kind"], $meta["media_preview"], $meta["media_external_url"]);
        DB::table("messages")->where("id", $row->id)->update(["metadata" => json_encode($meta)]);
        $count++;
    }
    // Clean: text without URL but media_kind=link
    elseif (($meta["media_kind"] ?? null) === "link" && $row->type === "text" && empty($meta["media_preview"])) {
        $meta["media_kind"] = "text";
        unset($meta["media_preview"], $meta["media_external_url"]);
        DB::table("messages")->where("id", $row->id)->update(["metadata" => json_encode($meta)]);
        $count++;
    }
}
echo "Total limpiados: $count" . PHP_EOL;
```

## E2E Smoke Test (verified working in production as of 2026-06-13)

```bash
# 1. Send text from phone → webhook → message created with role=user
# 2. Send image from phone → webhook → message + media downloaded, attachment_media_id set
# 3. Send sticker from phone → webhook → message + media downloaded, attachment_media_id set
# 4. Send link from phone → webhook → message + FetchLinkPreviewsJob dispatched
# 5. Open /admin/chats → renders all messages with metadata
# 6. Receive new message → socket broadcasts → sidebar updates in real time
```

All steps verified working. Sticker flow:
- Webhook received with `type=sticker`
- `EvolutionMessageParser` extracts `media_kind=sticker`, `media_mimetype=image/webp`
- `EvolutionMediaEnricher` calls `getBase64FromMediaMessage` → decodes base64 → saves to disk + creates `Media` row + sets `attachment_media_id`
- Also persists `media_base64` in metadata as fallback
- Broadcast event sent (WITHOUT `media_base64` to avoid payload size)
- Frontend receives event → renders sticker immediately

</laravel-boost-guidelines>
