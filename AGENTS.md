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
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll" requires adjacent words in order`.
3. Combine words and phrases for mixed queries: `rate limit` `middleware`.
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

# Inertia v3 — Layout as Wrapper (DYNAMIC BREADCRUMBS)

The `Page.layout` can be a **wrapper component** (function returning JSX) for pages that need **dynamic breadcrumbs** based on page props:

```tsx
// resources/js/Pages/Some/Edit.tsx
function SomeEditLayout({ children }: { children: React.ReactNode }) {
    const { props } = usePage<{ item: SomeItem }>();
    return <AppLayout breadcrumbs={buildBreadcrumbs(props.item)}>{children}</AppLayout>;
}

SomeEdit.layout = (page: React.ReactNode): React.ReactElement => <SomeEditLayout>{page}</SomeEditLayout>;
```

**DO NOT** use `(reply) => ({breadcrumbs: ...})` — Inertia calls `layout` with `(page: ReactNode)`, not with your custom prop. That pattern produces `/undefined` in breadcrumbs.

The simple object pattern `Page.layout = { breadcrumbs: [...] }` is fine for STATIC breadcrumbs (no page data needed).

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

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up a model.
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
| `app/Services/LinkPreviewService.php` | Fetches OpenGraph metadata, `SKIP_DOMAINS` list, `SCRIPT_TIMEOUT=180s`, **scheme-less URL detection** |
| `Modules/ChatBot/Http/Controllers/Api/Evolution/EvolutionWebhookController.php` | Receives webhook POSTs |
| `Modules/ChatBot/Http/Controllers/Admin/ChatController.php` | `index/show/reply/read/destroy/update`. `attachmentData()` resolves `attachment_url` (priority: `media_base64` > `Media` row > `media_url` external). `dispatchMissingLinkPreviews()` uses `LinkPreviewService::extractUrls()`. |
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
21. **Scheme-less URLs (e.g. `123.bo/mtv4`, `google.com`, `www.ejemplo.com`) were IGNORED** by the old `https?://...` regex in `LinkPreviewService::extractUrls()` and `ChatController::dispatchMissingLinkPreviews()`. **Fix:** both now use an extended regex that detects domain.tld patterns (with TLD whitelist) and normalizes them to `https://`. Email addresses are filtered out. `message-body.tsx` `autoLinkify()` was also updated to render them as clickable links. **See `app/Services/LinkPreviewService.php::extractUrls()` and `resources/js/components/message-body.tsx` for the canonical implementation.**
22. **Short link services (`bit.ly`, `123.bo`, etc.) trigger Chromium downloads** — `acceptDownloads: false` + `page.on('download')` handler + `page.on('response')` content-type check bail with `error: "download_blocked"` instead of cryptic "Download is starting" Playwright error. The job still persists the error in `metadata.media_preview.error` for `php artisan link-previews:refetch-failed` to retry.
23. **`page.goto` `waitUntil: 'load'` HANGS on SPAs** — Use `'commit'` first, then `waitForLoadState('domcontentloaded')` with short timeout. `'load'` and `'networkidle'` can hang 30s on TikTok/Instagram.

## Testing Notes

- **402 tests passing** (Pest 4) — was 366 in 2026-06, +36 new tests in 2026-06-13 batch
- All SQLite-compatible (no raw `JSON_EXTRACT` in queries — use PHP `filter()` on collections)
- Test the webhook flow with `EvolutionChannelUserLinkingTest`, `EvolutionChannelMessageTypesTest`, `EvolutionMediaEnricherTest`
- Test link preview with `RefetchLinkPreviewsTest` (uses `metadata.media_preview`)
- Test web widget with `WidgetConfigTest` (24 tests covering CRUD, public_key, allowed_domain, wildcards, webhooks)
- Test quick replies with `QuickReplyTest` (16 tests covering CRUD, shortcut uniqueness, regex, slash command API)
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

## Re-fetching Link Previews for Old Messages

```bash
# Re-fetch ALL text messages that have URLs in content (or no media_kind yet)
php artisan tinker --execute '
use Modules\ChatBot\Models\Message;
use App\Jobs\FetchLinkPreviewsJob;
use App\Services\LinkPreviewService;

$svc = app(LinkPreviewService::class);
$ids = [];
Message::where("type", "text")
  ->whereNotNull("content")
  ->where("content", "!=", "")
  ->get()
  ->filter(fn (Message $m) => ($m->metadata["media_kind"] ?? null) !== "link")
  ->each(function (Message $m) use ($svc, &$ids) {
    if (count($svc->extractUrls($m->content)) > 0) {
      $ids[] = $m->id;
    } else {
      $m->forceFill(["metadata" => ["media_kind" => "text"] + ($m->metadata ?? [])])->save();
    }
  });
if ($ids) FetchLinkPreviewsJob::dispatch($ids);
echo "Dispatched: " . count($ids) . PHP_EOL;
'

# Re-fetch only previously FAILED previews (error is set)
php artisan link-previews:refetch-failed
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

=== chatbot-web-widget rules ===

# ChatBot Module - Web Widget (Multi-Inbox)

Embeddable chat widget for third-party sites. **Multi-inbox**: one channel per domain, each with its own public_key, webhook_token, and embed snippet.

## Key Concepts

- Each `Channel` with `type='web_widget'` is an **independent inbox** for a single domain
- `allowed_domain` is **required** (no widget can be created without one)
- `public_key` (16 hex chars) is auto-generated on create; used as embed script key
- `webhook_token` (32 hex chars) is auto-generated on create; validated with `hash_equals()` constant-time compare
- Domain validation is **strict** (case-insensitive, strips `https://`, `/path`, etc.)
- **Wildcards supported**: `*.mitienda.com` matches `shop.mitienda.com` but not `mitienda.com`
- Embed JS is generated server-side at `/embed/widget/{public_key}.js` with config inlined

## Inbound Webhook Flow (Super Simple)

1. **Script loads** in third-party site via `<script src="https://hostbol.lat/embed/widget/{key}.js" data-channel="{key}" async></script>`
2. **Browser fetches the script** → server returns JS inline (NOT a static file) with `CFG = {key, name, title, greeting, position, show_typing, webhook_url, ...}` injected
3. **Origin check** — server validates `Origin`/`Referer` header against `allowed_domain`. If `allowed_domain='mitienda.com'` and origin is `https://evil.com` → returns `// domain_not_allowed` (empty script). **No 4xx**, to avoid breaking the page.
4. **Script renders** floating button + panel with `<form>` (name + email + message)
5. **Visitor submits** → `POST /api/webhooks/widget/{channel}/{token}` with `{visitor: {name, email, phone}, message: {content, attachment_media_id}}`
6. **Webhook validates**:
   - `Channel::type === 'web_widget'`
   - `hash_equals($channel->webhook_token, $token)` (constant-time)
   - `Channel::enabled === true`
   - `Origin` matches `allowed_domain`
7. **Backend creates/finds** `User` (by email, assigns role `user`), creates/loads `Conversation` (by `channel_id + user_id`), creates `Message` (role=user)
8. **Broadcasts** `ChatBotMessageReceived` event → admin `/admin/chats` updates in real time

## Routes

```php
// Admin (auth + verified)
GET    /admin/canales/web-widget              → chatBot.admin.widget (list)
GET    /admin/canales/web-widget/nuevo        → chatBot.admin.widget.create
POST   /admin/canales/web-widget              → chatBot.admin.widget.store
GET    /admin/canales/web-widget/{channel}     → chatBot.admin.widget.edit
PATCH  /admin/canales/web-widget/{channel}     → chatBot.admin.widget.update
DELETE /admin/canales/web-widget/{channel}     → chatBot.admin.widget.destroy

// Public
ANY    /api/webhooks/widget/{channel}/{token} → webhooks.widget (webhook receiver)
GET    /embed/widget/{key}.js                 → embed.widget (serves inline JS)
```

## Database Schema (channels table additions)

Migration: `2026_06_13_042205_migrate_web_widget_to_multi_inbox` + `2026_06_13_042943_simplify_web_widget_to_single_domain`

```php
// channels (added columns for web_widget)
$table->string('allowed_domain')->nullable();   // required for web_widget (single domain)
$table->string('public_key', 32)->nullable()->unique();  // 16 hex chars, auto-generated
$table->string('webhook_token', 32)->nullable()->unique(); // 32 hex chars, auto-generated
```

## Domain Normalization (WidgetController::normalizeDomain)

Input `"  https://WWW.MitIenda.com/some/path  "` → stored as `www.mitienda.com`

```php
$d = trim($domain);
$d = preg_replace('#^https?://#i', '', $d);  // strip scheme
$d = rtrim($d, '/');                          // strip trailing /
$d = preg_replace('#/.*$#', '', $d);          // strip path
return strtolower($d);
```

## Key Files

| File | Purpose |
|------|---------|
| `Modules/ChatBot/Models/Channel.php` | Eloquent model. `booted()` hook auto-generates `public_key` + `webhook_token` on `creating` for `web_widget`. `webhookUrl()` returns the full webhook URL. |
| `Modules/ChatBot/Http/Controllers/Admin/WidgetController.php` | CRUD for widgets. `store/update` validate `allowed_domain` (required, unique per type, not empty after normalization). |
| `Modules/ChatBot/Http/Controllers/Api/WebWidget/WidgetWebhookController.php` | Webhook receiver. Validates channel type, token (constant-time), enabled, origin. Calls `ChatBotMessageService::findOrCreateUser()`. |
| `Modules/ChatBot/Http/Controllers/Api/WebWidget/WidgetEmbedController.php` | `__invoke(Request, $key)` — returns inline JS with CFG object inlined. Bails to `// domain_not_allowed` (no 4xx) on bad origin. |
| `resources/js/components/chatbot/ChatBotWidget.tsx` | NOT used by external embeds. Detects `data-channel` from `<script>` and fetches `/api/chatbot/widget?key=...`. |
| `resources/js/components/chatbot/ChatBotPanel.tsx` | NOT used by external embeds. Renders the panel inside the admin app. |

## Channel Model Hooks (booted)

```php
protected static function booted(): void
{
    static::creating(function (Channel $channel): void {
        if ($channel->type === ChannelType::WebWidget) {
            if (empty($channel->public_key)) {
                $channel->public_key = self::generatePublicKey(); // 16 hex
            }
            if (empty($channel->webhook_token)) {
                $channel->webhook_token = self::generateWebhookToken(); // 32 hex
            }
        }
    });
}
```

## Embed Script Generation (WidgetEmbedController)

The endpoint returns **inline JavaScript** (NOT a static file). The IIFE:
1. Reads `data-channel` and `data-webhook` from the `<script>` tag
2. Injects a floating button (position: fixed, bottom + 16px, blue `#2563eb`, 56x56)
3. On click, opens a panel (360px wide, 520px tall) with header, messages area, and form
4. Form: name input (first load) → textarea + Send button (subsequent)
5. On submit: `POST {webhook_url}` with `{visitor: {name, email}, message: {content}}` + `credentials: 'omit'`
6. Handles errors with system message bubbles
7. NO WebSocket client (admin reply not yet visible to visitor — needs separate iteration)

**Important**: The embed script `content-type` is `application/javascript` and is cached for 5 minutes (`Cache-Control: public, max-age=300`).

## Common Pitfalls

1. **The `<script>` pointing to `/embed/widget/{key}.js` is a Laravel route**, NOT a static file in `public/`. Make sure `routes/web.php` is not blocked by the `{slug}` catch-all. The catch-all regex MUST include `embed` in the exclusion list: `^(?!admin|api|...|embed).*$`. (See `routes/web.php`.)
2. **The embed endpoint must NOT return 4xx on bad origin** — that breaks the third-party site. Instead, return a valid JS comment (`// domain_not_allowed`) so the script fails silently. Same pattern for `widget_disabled` and `invalid_key`.
3. **`hash_equals()` is mandatory** for `webhook_token` validation — never use `===` (timing attack risk).
4. **Cross-origin POST without `credentials: 'omit'`** will be blocked by CORS. The embed script uses `credentials: 'omit'` and doesn't send cookies. CSRF is not needed for public webhooks (token replaces it).
5. **The admin inbox (`/admin/chats`) receives messages from web widgets in the same way as Evolution** — the `ChatBotMessageReceived` event fires for both. Frontend doesn't distinguish.
6. **Visitor cannot see admin replies** (no Reverb in embed script yet). If needed, add polling: `setInterval` calling a public endpoint that returns new messages by `conversation_id` + `last_message_id`.

## Tests

`tests/Feature/ChatBot/WidgetConfigTest.php` — 24 tests covering:
- CRUD (create, update, delete, list, edit form)
- Domain normalization (`https://WWW.MitIenda.com/path` → `www.mitienda.com`)
- Domain uniqueness (cannot create two widgets for same domain)
- Public_key + webhook_token auto-generation, uniqueness
- API endpoint validation (key required, invalid key, disabled, bad origin, allowed origin, wildcard subdomain, empty allowed_domain rejected)
- Webhook receiver: creates user + conversation + message, validates token, blocks bad origin, requires visitor data, reuses existing user/conversation

## Embed Snippet (what admin copies)

```html
<script src="https://hostbol.lat/embed/widget/{public_key}.js"
        data-channel="{public_key}"
        data-webhook="https://hostbol.lat/api/webhooks/widget/{channel_id}/{webhook_token}"
        async></script>
```

Paste in the third-party site's footer (before `</body>`). Works in WordPress via "Insert Headers and Footers" plugin or direct `footer.php` edit.

=== chatbot-quick-replies rules ===

# ChatBot Module - Quick Replies (Slash Commands)

Reusable canned responses admins can invoke from the chat composer with `/shortcut` syntax (like WhatsApp Web, Slack, Telegram, Discord).

## Key Concepts

- Each `QuickReply` is a `shortcut` + `content` + optional `media_id` + optional `category`
- **1 quick reply = 1 message** sent to WhatsApp (text + optional media). NOT a multi-message template.
- The `shortcut` is unique, lowercase, alphanumeric + `_-` only, max 50 chars (the `/` is added by the UI)
- At least one of `content` or `media_id` is required
- **`enabled=false`** responses don't appear in the composer dropdown but stay in the admin UI
- **`soft deletes`** — when deleted, the response is gone from `find()` but stays in DB for audit
- **Composer uses `/` slash commands** (WhatsApp Web style) — typing `/` opens a dropdown filtered by the query
- The dropdown shows: shortcut (mono), title, media badge, category badge, content preview
- Selection (Tab/Enter/click) replaces the `/query` line with the response content and attaches the media
- The admin can then edit the text and click Send (or hit Enter) to actually send to WhatsApp

## Routes

```php
// Admin CRUD
GET    /admin/canales/respuestas-rapidas                 → chatBot.admin.quick-replies.index
GET    /admin/canales/respuestas-rapidas/nueva           → chatBot.admin.quick-replies.create
POST   /admin/canales/respuestas-rapidas                 → chatBot.admin.quick-replies.store
GET    /admin/canales/respuestas-rapidas/{qr}/edit      → chatBot.admin.quick-replies.edit
PATCH  /admin/canales/respuestas-rapidas/{qr}           → chatBot.admin.quick-replies.update
DELETE /admin/canales/respuestas-rapidas/{qr}           → chatBot.admin.quick-replies.destroy

// Public API (auth required, for the composer dropdown)
GET    /api/chatbot/quick-replies                        → chatBot.api.quick-replies
```

## Database Schema (quick_replies table)

Migration: `2026_06_13_142605_create_quick_replies_table`

```php
Schema::create('quick_replies', function (Blueprint $table) {
    $table->id();
    $table->string('shortcut', 50)->unique();
    $table->string('title', 100);
    $table->text('content')->nullable();
    $table->string('category', 50)->nullable();
    $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
    $table->integer('sort')->default(0);
    $table->boolean('enabled')->default(true);
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['enabled', 'category', 'sort']);
});
```

## Permissions

Added to `PermissionSeeder::PERMISSIONS` and `RoleSeeder::EDITOR_PERMISSIONS`:
- `view quick replies` (editor: yes)
- `create quick replies` (admin only)
- `update quick replies` (admin only)
- `delete quick replies` (admin only)

## Default Seeder (QuickRepliesSeeder)

Added to `DatabaseSeeder`. Creates 3 defaults on `php artisan db:seed`:

| Shortcut | Title | Content | Category |
|---|---|---|---|
| `/saludo` | Saludo inicial | "¡Hola! 👋 Bienvenido a *Hostbol*... ¿En qué podemos ayudarte?" | saludos |
| `/gracias` | Agradecimiento | "Muchas gracias por contactarnos. ✨ Te responderemos a la brevedad." | saludos |
| `/horario` | Horario de atención | Horario con `_cursiva_` y `*negrita*` | informacion |

Uses `updateOrCreate(['shortcut' => ...])` → idempotent.

## Key Files

| File | Purpose |
|------|---------|
| `Modules/ChatBot/Models/QuickReply.php` | Eloquent model with `media()` and `creator()` relations |
| `Modules/ChatBot/database/factories/QuickReplyFactory.php` | Factory with `mediaOnly()` state |
| `Modules/ChatBot/Http/Controllers/Admin/QuickReplyController.php` | CRUD + `api()` endpoint returning only `enabled=true` |
| `Modules/ChatBot/Http/Requests/StoreQuickReplyRequest.php` | Validates shortcut (regex `[a-zA-Z0-9_-]+`, unique), content/media_id required_without each other |
| `Modules/ChatBot/Http/Requests/UpdateQuickReplyRequest.php` | Same as Store but with unique:shortcut,{id} |
| `Modules/ChatBot/resources/js/Pages/QuickReplies/Index.tsx` | List page (yellow theme, Zap icon) |
| `Modules/ChatBot/resources/js/Pages/QuickReplies/Edit.tsx` | Form with auto-slugify + WhatsAppEditor + media upload |
| `resources/js/components/ui/whatsapp-editor.tsx` | **Reusable** textarea + WhatsApp preview component |
| `resources/js/lib/whatsapp-markdown.ts` | `renderWhatsAppMarkdown()` + `slugifyShortcut()` helpers |
| `resources/js/components/chat/QuickReplyDropdown.tsx` | Slash command dropdown UI (keyboard nav, mouse hover, click to select) |
| `Modules/ChatBot/resources/js/Hooks/use-quick-replies.ts` | Cached fetch hook for the dropdown list |
| `database/seeders/QuickRepliesSeeder.php` | Default 3 responses |

## WhatsApp Markdown Editor (resources/js/components/ui/whatsapp-editor.tsx)

Reusable component for any message that will be sent to WhatsApp. Layout: toolbar (B/I/S/code/link buttons) + textarea on the left, live preview on the right (WhatsApp bubble colors `#ECE5DD` bg, `#DCF8C6` bubble).

Supported syntax (rendered live in preview):
- `*bold*` → **negrita**
- `_italic_` → *cursiva*
- `~strike~` → ~~tachado~~
- ````code```` → `código` monoespaciado
- `[texto](url)` → link azul underlined
- `\n` → `<br>`

Preview CSS lives in `resources/css/app.css` under `.wa-preview` (link color `#027EB5`, code background, etc.).

## Slugify Logic (resources/js/lib/whatsapp-markdown.ts::slugifyShortcut)

Transforms a title into a valid shortcut:
1. `NFD` unicode normalization → separates accents
2. Strip combining marks (á → a, ñ → n, ï → i, etc.)
3. `lowercase`
4. `[^a-z0-9]+` → `-`
5. Collapse `--` → `-`
6. Trim `-` from start/end
7. Truncate to 50 chars

Examples:
- `"Saludo Inicial"` → `saludo-inicial`
- `"Hola, ¿en qué podemos ayudarte?"` → `hola-en-que-podemos-ayudarte`
- `"100% Oferta!!"` → `100-oferta`
- `"Añoro más info"` → `anoro-mas-info` (accents removed)

## Auto-slugify UX in Edit Form

- When admin types the **title**, the **shortcut** auto-fills with the slugified version
- If the admin manually edits the shortcut, the auto-fill STOPS (sets `shortcutTouched=true`)
- A "↻" button next to the shortcut re-enables auto-fill from the current title
- A blue badge "Auto desde título" indicates the auto-sync is active
- On initial load of an existing reply, `shortcutTouched` is `true` (don't overwrite saved value)

## Composer Integration (Modules/ChatBot/resources/js/Pages/Chats/Index.tsx)

The composer now has a `QuickReplyDropdown` triggered by typing `/`:

```tsx
// State added to ChatsIndex component
const { replies: quickReplies, loading: quickRepliesLoading } = useQuickReplies();
const [qrOpen, setQrOpen] = useState(false);
const [qrSelectedIndex, setQrSelectedIndex] = useState(0);
const [pickedMediaId, setPickedMediaId] = useState<number | null>(null);
const [pickedMediaMeta, setPickedMediaMeta] = useState<...>(null);

// Detection: when last line of draft is /query, open dropdown
onChange={(e) => {
  const value = e.target.value;
  setDraft(value);
  const lastLine = value.split('\n').pop() ?? '';
  if (/^\/[a-zA-Z0-9_-]*$/.test(lastLine)) {
    setQrOpen(true);
    setQrSelectedIndex(0);
  } else {
    setQrOpen(false);
  }
}}

// Keyboard handler
onKeyDown={(e) => {
  if (qrOpen) {
    if (e.key === 'ArrowDown') { ... }
    if (e.key === 'ArrowUp') { ... }
    if (e.key === 'Tab' || e.key === 'Enter') {
      e.preventDefault();
      if (qrFiltered[qrSelectedIndex]) applyQuickReply(qrFiltered[qrSelectedIndex]);
    }
    if (e.key === 'Escape') { setQrOpen(false); }
  }
  if (e.key === 'Enter' && !e.shiftKey) { sendMessage(); }
}}

// sendMessage() now also sends attachment_media_id when picked from quick reply
const formData = new FormData();
formData.append('content', content);
if (file) formData.append('file', file);
else if (mediaId) formData.append('attachment_media_id', String(mediaId));
```

## Common Pitfalls

1. **Don't send `media_base64` for quick replies** — only `attachment_media_id`. The `Media` row already has the file on disk. The `ChatController::reply` already supports both.
2. **The `useQuickReplies` hook caches the list in a module-level variable** — first call fetches, subsequent calls return cache. If the admin creates a new reply, the cache is stale until the page reloads.
3. **`/shortcut` is a single-line trigger** — if the user has multi-line text and the last line is `/query`, the dropdown opens. If they navigate to a different line, the dropdown closes. This is intentional (matches Slack/Discord behavior).
4. **The dropdown doesn't auto-send** — the admin always clicks Send (or hits Enter without the dropdown). This prevents accidental sends.
5. **The shortcut field in the form has `maxLength=50`** but the `slugifyShortcut` already truncates. The DB column is `string(50)` unique.
6. **Media `nullOnDelete`** — if a `Media` row is deleted, the `quick_replies.media_id` becomes NULL but the reply stays. The reply is marked "incomplete" (no preview shown).
7. **The `bail-on-` empty content** validation (`required_without:media_id`) — if both `content` and `media_id` are empty, the request fails. This is enforced on both Store and Update.
8. **The Edit form uses the layout wrapper pattern** (NOT `(reply) => ({breadcrumbs})`) to get dynamic breadcrumbs showing the shortcut name. See "Inertia v3 — Layout as Wrapper" above.

## Tests

`tests/Feature/ChatBot/QuickReplyTest.php` — 16 tests covering:
- CRUD (list, create, view, edit, update, delete)
- Permission gates (admin, basic user forbidden)
- Shortcut validation (required, unique, regex, max 50, leading `/` stripped)
- Content/media_id validation (at least one required)
- API endpoint: returns only enabled, requires auth, requires permission
- Soft deleted replies are not in API

## E2E Flow (verified 2026-06-13)

1. Admin types `/` in the composer textarea → dropdown appears
2. Admin types `salu` → filters to `/saludo`
3. Admin presses Tab → `/saludo` replaced with "¡Hola! 👋 Bienvenido a *Hostbol*..."
4. Admin can edit the text, then clicks Send
5. Message goes to WhatsApp via the same `ChatController::reply` flow
6. Visitor receives the formatted message (WhatsApp renders `*bold*` as bold, `_italic_` as italic)

</laravel-boost-guidelines>
