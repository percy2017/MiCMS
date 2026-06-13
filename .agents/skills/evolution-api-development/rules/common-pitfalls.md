# Evolution API v2 — Common Pitfalls

15+ pitfalls discovered in production (as of 2026-06-13).

## 1. Missing `apikey` header
Every request MUST include `apikey` header. Without it, you get 401.

```php
// ✅ Correct
Http::withHeaders(['apikey' => $this->apiKey])
    ->post("{$this->serverUrl}/chat/fetchProfile/{$this->instanceName}", ['number' => '...']);

// ❌ Wrong
Http::post("{$this->serverUrl}/chat/fetchProfile/{$this->instanceName}", ['number' => '...']);
```

## 2. Confusing `remoteJid` with phone number
`remoteJid` can be `59168964000@s.whatsapp.net` (user) or `59168964000@g.us` (group). Extract the number before `@`.

```php
$phonePart = explode('@', $remoteJid)[0]; // "59168964000"
```

## 3. Not filtering `fromMe`
Webhooks include your own outgoing messages. **For our project: process BOTH directions** (admin echoes need to be saved too):

```php
$fromMe = (bool) ($key['fromMe'] ?? false);
if ($fromMe) {
    // Admin echo: save as role=admin
} else {
    // User message: save as role=user
}
```

## 4. Ignoring duplicate messages
Evolution API may re-send webhooks (network retries). Check `external_id` before creating a Message:

```php
if ($messageId && Message::withTrashed()->where('external_id', $messageId)->exists()) {
    return null; // duplicate
}
```

## 5. `send.message` only fires for outgoing
Use `messages.upsert` with `fromMe: true` if you need outgoing message tracking, or handle `send.message` separately.

## 6. `connecting` appears in two events
Both `connection.update` and `APPLICATION_STARTUP` can emit `connecting` state. Handle both.

## 7. Not all `messageType` values have content
Types like `albumMessage`, `protocolMessage`, `groupStatusMentionMessage` should be skipped explicitly. Check for them in the parser.

## 8. Instance must exist before sending
Call `fetchInstances` to verify the instance name matches an active instance before sending.

## 9. Media URLs are temporary
WhatsApp media URLs (`mmg.whatsapp.net`) expire in ~5 minutes. Download and store media promptly via `getBase64FromMediaMessage`. **Don't rely on the original URL for display**.

## 10. Status values differ between events
`messages.update` uses `SERVER_ACK`/`DELIVERY_ACK`/`READ`/`ERROR`. Always map to your DB schema:
```php
$statusMap = [
    'SERVER_ACK' => 'sent',
    'DELIVERY_ACK' => 'delivered',
    'READ' => 'read',
    'ERROR' => 'failed',
];
```

## 11. FromMe=true semantics inverted
When `fromMe=true`:
- `remoteJid` = **recipient** (the client being messaged) — NOT the admin
- `pushName` = **admin's own name** — NOT the client's

**Don't use `pushName` to create the user in `fromMe=true` branch** (it would create a user with the admin's name but the client's phone/JID).

```php
// ✅ Correct (fromMe=true): use remoteJid for JID, NOT pushName for name
$phonePart = explode('@', $remoteJid)[0] ?? null;
$this->userLinker->linkOrCreate($conversation, $remoteJid, null, $phonePart);
//                                                    ↑ pass null, fallback is 'Visitante WhatsApp'
```

## 12. `getBase64FromMediaMessage` can fail
For expired media URLs, Evolution returns **HTTP 400 with "TypeError: Cannot read properties of undefined (reading 'key')"**.

The `EvolutionMediaEnricher` handles this gracefully by:
- Setting `media_enrichment_failed_at` timestamp
- NOT setting `attachment_media_id`
- The frontend can then show the placeholder `[Sticker]` etc.

## 13. Link preview job must be dispatched from webhook
Don't wait for admin to navigate to the chat. The `maybeDispatchLinkPreview()` method in `EvolutionChannel` dispatches the job automatically when a text message with URLs arrives.

```php
private function maybeDispatchLinkPreview(Message $message): void
{
    if ($message->type !== MessageType::Text) {
        return;
    }
    $content = (string) $message->content;
    if ($content === '' || ! preg_match('#https?://[^\s<>"\'\\)\]]+#i', $content)) {
        return;
    }
    FetchLinkPreviewsJob::dispatch([$message->id]);
}
```

## 14. `fetchProfile` with admin's own instance returns empty `name`
The admin's own contact doesn't have a profile in their own instance. If the admin is messaging themselves (echo), the `name` field will be empty.

Workaround: use a different instance to get the real name. Or accept the empty name and the frontend will show "?".

## 15. `ChatController::dispatchMissingLinkPreviews` MUST filter by `type===Text`
Iterating over ALL messages will set `media_kind=link` on stickers/images/etc. **This was a production bug on 2026-06-13**.

```php
// ✅ Correct: filter by text type ONLY
$messages = $conversation->messages
    ->filter(fn (Message $m): bool => $m->type === MessageType::Text
        && $m->content !== null
        && $m->content !== ''
        && (($m->metadata['media_kind'] ?? null) !== 'link'));

// ❌ Wrong: sets media_kind=link on stickers (broke our production)
$messages = $conversation->messages
    ->filter(fn (Message $m): bool => $m->content !== null
        && $m->content !== ''
        && (($m->metadata['media_kind'] ?? null) !== 'link'));
```

## 16. `media_base64` is LARGE (~10-100KB)
The base64 of media is persisted in `metadata.media_base64` by `EvolutionMediaEnricher`. **Don't send it via socket broadcast** — it exceeds Pusher/Reverb payload limits.

**Solution**: `ChatBotMessageReceived::broadcastWith()` should send the metadata WITHOUT `media_base64`. The frontend gets the full data via `ChatController::index` reload (when user opens/navigates the chat).

```php
// In ChatBotMessageReceived::broadcastWith():
return [
    'message' => [
        'id' => $this->message->id,
        'type' => $this->message->type?->value,
        'content' => $this->message->content,
        'attachment_url' => $this->message->attachment?->url(),
        // 'media_base64' is NOT sent (too large for socket)
        'metadata' => $this->message->metadata, // contains media_base64 but frontend ignores it
    ],
];
```

The frontend handler `useChatSync.onMessage` should:
1. Update the `activeConv` with the basic message data (no base64)
2. If the message is media, do `router.reload({ only: ['active'] })` to get the full data

## 17. SQLite doesn't support `JSON_EXTRACT` with `JSON_UNQUOTE`
For migrations or queries that need to extract JSON values, use PHP loops:

```php
// ✅ SQLite-compatible
$msgs = DB::table("messages")->get(["id", "metadata"]);
foreach ($msgs as $row) {
    $meta = json_decode($row->metadata, true);
    if (($meta["media_kind"] ?? null) === "link") {
        // process
    }
}

// ❌ Fails on SQLite
DB::table("messages")
    ->whereRaw("JSON_EXTRACT(metadata, '$.media_kind') = ?", ['"link"'])
    ->get();
```

## 18. Webhook payload structure may include `media_url` or not
For **images, videos, audio, files**: Evolution includes `media.url` in the webhook payload.
For **stickers**: Evolution does NOT include `media.url` in the webhook payload. Only `getBase64FromMediaMessage` returns the base64.

This is why `extractMediaMeta` should always run for `isMediaDownloadable()` types — for stickers, the URL is only available after calling `getBase64FromMediaMessage`.

## 19. The admin writing to themselves creates a phantom user
When the admin sends a message to themselves (common during testing), the system:
- Creates a new user (or finds existing) with `name=''` and `phone=admin's_own_number`
- Creates a new conversation (or finds existing) with `external_id=admin's_own_jid`
- The user is technically valid but represents the admin, not a real client

**Workaround** (not yet implemented): compare `remoteJid` with `owner_jid` of the channel. If they match, skip creating the user (it's an echo). Requires `connection.update` to capture `wuid` as `owner_jid`.

## 20. The `media_kind=link` for text without URLs is misleading
When the `FetchLinkPreviewsJob` processes a text message that has no URLs, the old code set `media_kind=link` and `media_preview=null`. This is **misleading** because the message is not a link.

**Correct convention**:
- Text without URL → `media_kind=text` (or omit)
- Text with URL and valid preview → `media_kind=link` with `media_preview` filled
- Text with URL but domain is skipped → `media_kind=link` with `media_preview.error='domain_skipped'`
