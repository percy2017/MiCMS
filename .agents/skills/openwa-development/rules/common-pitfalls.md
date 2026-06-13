# OpenWA — Common Pitfalls & Solutions

This document catalogs real-world issues encountered when integrating OpenWA, with concrete workarounds. Updated as we learn from production.

---

## 1. Authentication

### 1.1 Using `Authorization: Bearer` Instead of `X-API-Key`

**Symptom:** Every request returns `401 UNAUTHORIZED`, even with the correct key.

**Cause:** The OpenAPI spec uses `security: [bearer: []]` which makes Swagger UI suggest `Authorization: Bearer <key>`. But OpenWA doesn't validate the `Authorization` header — it uses `X-API-Key`.

**Fix:**
```http
X-API-Key: openwa_sk_abc123...   # ✓ correct
Authorization: Bearer openwa_sk_abc123...   # ✗ wrong
```

### 1.2 Storing the Plaintext Key in DB

**Symptom:** Audit log shows the key in plaintext if anyone dumps the DB.

**Cause:** OpenWA already stores only the SHA-256 hash (`keyHash`). But YOUR app may be storing the plaintext in `services.openwa.api_key` config or in `.env`.

**Fix:** Make sure `.env` is in `.gitignore` and consider using a secret manager (Vault, AWS Secrets Manager). Laravel's `env()` returns plaintext — use `config()` only.

### 1.3 Forgetting to Set the Master Key

**Symptom:** All your API keys are accidentally deleted, you can't recover.

**Fix:** Set `API_MASTER_KEY` in `.env` BEFORE deleting any other key. Store it in your password manager. It's your only way to recover.

---

## 2. Session Lifecycle

### 2.1 Sending Before `start` Completed

**Symptom:** `POST /messages/send-text` returns `400 SESSION_NOT_READY`.

**Cause:** `POST /sessions` only creates a record. You need `POST /sessions/{id}/start` to boot the engine. Until status is `CONNECTED`, all sends fail.

**Fix:** Wait for `session.status: CONNECTED` event or poll `GET /sessions/{id}` until `status === 'CONNECTED'`.

### 2.2 QR Code Expired

**Symptom:** `GET /sessions/{id}/qr` returns `400 SESSION_QR_EXPIRED`.

**Cause:** QR codes expire after ~60 seconds. You need to re-fetch.

**Fix:** Subscribe to `session.qr` webhook to get fresh QR codes automatically. Don't cache QR for >60s.

### 2.3 Session Disconnects Repeatedly

**Symptom:** Session goes `CONNECTED` → `DISCONNECTED` every few minutes/hours.

**Cause:** Memory pressure, network issues, or WhatsApp anti-ban for high message volume.

**Fix:**
- Increase container memory limits (1GB+ per session)
- Reduce message frequency (use `delayBetweenMessages` in bulk)
- Implement exponential backoff on reconnect
- Subscribe to `session.disconnected` webhook and call `start` again automatically

### 2.4 `SESSION_LIMIT_REACHED`

**Symptom:** `POST /sessions` returns `403 SESSION_LIMIT_REACHED`.

**Cause:** Some OpenWA versions have a per-instance session limit (configurable). Default varies by deployment.

**Fix:** Either scale horizontally (multiple OpenWA instances, shared DB) or increase the limit in your deployment config.

---

## 3. Message Sends

### 3.1 Wrong `chatId` Format

**Symptom:** `400 MESSAGE_INVALID_CHAT_ID` or message silently fails to deliver.

**Cause:** Using Evolution API's format `59169387181@s.whatsapp.net` instead of OpenWA's `59169387181@c.us`.

**Fix:**
```php
// Convert Evolution-style to OpenWA-style
$chatId = str_replace('@s.whatsapp.net', '@c.us', $evolutionChatId);
```

| Source                              | Format                              |
|-------------------------------------|-------------------------------------|
| **OpenWA (this)**                   | `59169387181@c.us`                  |
| Evolution API                       | `59169387181@s.whatsapp.net`        |
| whatsapp-web.js (raw)               | `59169387181@c.us`                  |
| Twilio                              | `whatsapp:+59169387181`             |
| Cloud API (Meta)                    | `59169387181`                       |

### 3.2 `MESSAGE_NUMBER_NOT_ON_WHATSAPP`

**Symptom:** Send returns `400` with this code.

**Cause:** The number is not registered on WhatsApp. Could be:
- Typo in the number
- Missing country code
- Number was deleted
- User uninstalled WhatsApp

**Fix:** Use `GET /sessions/{id}/contacts/check/{number}` to verify before sending. Filter out users who return `exists: false`.

### 3.3 Media Too Large

**Symptom:** `413 MESSAGE_MEDIA_TOO_LARGE`.

**Cause:** Exceeded the per-type limit:
- Image: 16 MB
- Video: 64 MB
- Audio: 16 MB
- Document: 100 MB
- Sticker: 500 KB

**Fix:** Resize/compress client-side before sending. Use a CDN that transcodes on the fly.

### 3.4 Voice Note Sent as File Instead of PTT

**Symptom:** Audio file appears as an attachment instead of a voice note bubble.

**Cause:** Missing `ptt: true` flag.

**Fix:**
```json
{
  "chatId": "...",
  "audio": { "url": "..." },
  "ptt": true,
  "mimetype": "audio/ogg; codecs=opus"
}
```

### 3.5 Document Without Filename

**Symptom:** WhatsApp shows the file with a blank name or "file".

**Cause:** `filename` field missing from `send-document`.

**Fix:** Always set `filename` for documents:
```json
{ "chatId": "...", "document": { "url": "..." }, "filename": "Q4-report.pdf" }
```

### 3.6 Sticker Format Rejected

**Symptom:** `400 MESSAGE_MEDIA_INVALID_FORMAT` for stickers.

**Cause:** Sticker MUST be WebP. PNG/JPG will be rejected.

**Fix:** Convert to WebP before sending. Use `cwebp` or a library like `sharp`:
```bash
cwebp -q 90 input.png -o output.webp
```

---

## 4. Webhooks

### 4.1 HMAC Verification Fails Inconsistently

**Symptom:** Sometimes signature matches, sometimes not.

**Cause:** You're computing the HMAC over `$request->all()` (parsed array) or `json_encode($request->json()->all())` (re-serialized). The re-serialization is not bit-identical to the original body.

**Fix:** Use the **raw** body:
```php
$rawBody = $request->getContent();   // raw string
$expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
return hash_equals($expected, $request->header('X-OpenWA-Signature'));
```

In Laravel, also make sure the route is NOT inside a group that calls `TrimStrings` or `ConvertEmptyStringsToNull` middleware on the body.

### 4.2 Webhook Returns 200 but OpenWA Keeps Retrying

**Symptom:** OpenWA shows retry attempts in logs even though you returned 200.

**Cause:** Your response was 200 but the body is empty or non-JSON. OpenWA may treat empty body as failure.

**Fix:** Always return a valid JSON body with 2xx:
```php
return response()->json(['status' => 'processed'], 200);
```

### 4.3 Duplicate Messages

**Symptom:** Same message appears twice in your DB.

**Cause:** Not deduping on `idempotencyKey`, or deduping on the wrong field.

**Fix:** Dedupe ONLY on `X-OpenWA-Idempotency-Key` (the body field), NOT on `X-OpenWA-Delivery-Id` (changes on retry) or `event.id` (use the `wa_message_id` for that, not for dedup).

```php
$key = $request->header('X-OpenWA-Idempotency-Key');
if (Cache::has("webhook:{$key}")) return;  // skip
Cache::put("webhook:{$key}", true, 86400);
```

### 4.4 `MESSAGE_BLOCKED_CONTACT`

**Symptom:** Cannot send to a specific number.

**Cause:** Either YOU have blocked them, or THEY have blocked you. WhatsApp is silent about which.

**Fix:** Use `DELETE /contacts/{contactId}/block` to unblock (if it was you). If they blocked you, there's nothing to do — wait for them to unblock.

### 4.5 Webhook Times Out

**Symptom:** OpenWA logs show `ETIMEDOUT` or `AbortError` for webhook delivery.

**Cause:** Your endpoint takes >`WEBHOOK_TIMEOUT` (default 10s) to respond.

**Fix:**
- ACK fast (return 200 immediately) and process async (queue a Laravel job).
- Optimize your DB writes.
- If processing is inherently slow, increase `WEBHOOK_TIMEOUT` env (max 30s recommended).

### 4.6 No Webhook on First Send

**Symptom:** You sent a message but never received `message.sent`.

**Cause:** The webhook was registered AFTER the session was started, or the events array doesn't include `message.sent`.

**Fix:** Always include `message.sent` in `events`. Re-register the webhook if needed:
```bash
php artisan openwa:setup-webhook tigo1
```

---

## 5. Media (Receive Side)

### 5.1 Media URL Expired

**Symptom:** Trying to download a media URL returns 404 or 401.

**Cause:** WhatsApp CDN URLs (`mmg.whatsapp.net`) expire in ~5-30 minutes.

**Fix:** Download media immediately on `message.received` webhook:
```php
if ($data['hasMedia'] && $data['mediaUrl']) {
    Http::timeout(30)->get($data['mediaUrl'])->throw();
    Storage::put("whatsapp/{$data['id']}", $response->body());
}
```

For long-lived storage, configure OpenWA with `STORAGE_TYPE=s3` and persist the local path.

### 5.2 Missing `mimetype`

**Symptom:** Cannot determine if the media is image or video.

**Cause:** Some message types don't include `mimetype` in the webhook (engine quirk).

**Fix:** Use the `type` field to infer the MIME type:
```php
$mime = match ($data['type']) {
    'image' => 'image/jpeg',
    'video' => 'video/mp4',
    'ptt', 'audio' => 'audio/ogg',
    'document' => 'application/octet-stream',
    'sticker' => 'image/webp',
    default => 'application/octet-stream',
};
```

### 5.3 Base64 Media Payload Too Large

**Symptom:** OpenWA memory spikes when receiving large media.

**Cause:** Some OpenWA configurations include the full base64 in the webhook payload.

**Fix:** Configure webhooks to send URL instead of base64 (preferred for files >5MB). Or stream-process the base64 in chunks.

---

## 6. Database / Performance

### 6.1 SQLite Lock Errors Under Load

**Symptom:** `SQLITE_BUSY` errors during high message volume.

**Cause:** SQLite locks the entire DB on writes; concurrent webhook processing blocks.

**Fix:**
- Switch to PostgreSQL (`DATABASE_TYPE=postgres`).
- Or batch your writes (use a queue to insert in bulk).
- Or use a write-through cache.

### 6.2 `Message` Table Grows Unbounded

**Symptom:** Disk fills up, queries get slow.

**Cause:** Messages are never archived.

**Fix:** Add a cron job to delete messages older than N days:
```sql
DELETE FROM messages WHERE createdAt < NOW() - INTERVAL '90 days';
```

Run via Laravel:
```php
// app/Console/Kernel.php
$schedule->call(function () {
    DB::table('messages')->where('created_at', '<', now()->subDays(90))->delete();
})->daily();
```

### 6.3 Slow `getMessages` Queries

**Symptom:** `GET /messages?chatId=...` takes >5s for large conversations.

**Cause:** Missing index on `(sessionId, chatId, timestamp)`.

**Fix:** Add a composite index in a TypeORM migration:
```typescript
await queryRunner.createIndex('messages', new TableIndex({
  name: 'IDX_SESSION_CHAT_TS',
  columnNames: ['sessionId', 'chatId', 'timestamp'],
}));
```

---

## 7. Group Chats

### 7.1 Bot's Own Messages Echo Back

**Symptom:** The webhook fires with `data.from = bot's own phone` when the bot sends a message in a group.

**Cause:** This is the `message.sent` event, which is normal. You can distinguish by `data.id` starting with `true_`.

**Fix:** Filter on `data.id` prefix:
```php
$isOutgoing = str_starts_with($data['id'], 'true_');
```

### 7.2 Group Member Changes Not Reflected

**Symptom:** `GET /groups/{groupId}` returns stale participant list.

**Cause:** OpenWA caches the participant list from when the group was last fetched.

**Fix:** Call `GET /groups/{groupId}` after a `group.update` or `group.join`/`group.leave` event to refresh.

---

## 8. CORS & Network

### 8.1 Browser Cannot Connect

**Symptom:** Browser console shows `CORS error` when calling OpenWA from JS.

**Cause:** `CORS_ORIGINS` is set to `*` but you need explicit origins for credentials.

**Fix:** Set `CORS_ORIGINS=https://yourapp.com,https://dashboard.yourapp.com` in `.env`. Or use a backend proxy and never expose the API key to the browser.

### 8.2 Traefik SSL Issues

**Symptom:** `502 Bad Gateway` from OpenWA behind Traefik.

**Cause:** Traefik can't reach the OpenWA container's port 2785.

**Fix:**
- Ensure the OpenWA service is in the same Docker network as Traefik
- Use `expose: [2785]` (not `ports`) so only Traefik can reach it
- Check `traefik.http.routers.openwa.rule=Host(\`api.yourdomain.com\`)`

---

## 9. API Key Rotation

### 9.1 Lost API Key

**Symptom:** The plaintext key was never stored.

**Cause:** You didn't capture the response from `POST /auth/api-keys`.

**Fix:** Revoke the old key, create a new one:
```bash
# Get the key ID from the audit log
php artisan openwa:list-keys   # custom command you should write

# Revoke and recreate
php artisan openwa:rotate-key tigo1
```

### 9.2 Can't Find Any Admin Key

**Symptom:** You can't create new admin keys because all admins are revoked/expired.

**Fix:** Use `API_MASTER_KEY` env var. Restart the container with this set to a new random string. The master key has implicit admin role and works regardless of DB state.

---

## 10. Multi-Session Edge Cases

### 10.1 Two Sessions, Same WhatsApp Number

**Symptom:** You create two OpenWA sessions with the same phone number, second one disconnects first.

**Cause:** WhatsApp only allows one active session per phone number (multi-device beta aside).

**Fix:** Use one session per phone. If you need separate "bots", use a single session with logic in your app to route by chat.

### 10.2 Session "Fights" With Phone App

**Symptom:** When OpenWA session is connected, the phone shows "online" but the user can't send messages.

**Cause:** This is normal. Multi-device is supported but the primary device must be online. If the user force-closes the OpenWA session, the phone regains control.

**Fix:** Don't run OpenWA 24/7 for a personal number. Use a dedicated business number for OpenWA bots.

---

## 11. Idempotency Gotchas

### 11.1 `idempotencyKey` Not Unique Across Sessions

**Symptom:** Events from different sessions share the same `idempotencyKey` for the same content.

**Cause:** The key format includes `sessionId` since v0.1.1, but check your version.

**Fix:** Always include `sessionId` in your dedup key:
```php
$dedupKey = "{$sessionId}:{$idempotencyKey}";
```

### 11.2 Bulk Messages Have No Individual ACK

**Symptom:** You send 100 messages in a batch and only get 100 `message.sent` events, no per-recipient `message.ack`.

**Cause:** Each batched message DOES get its own `message.ack`, but only if the recipient's phone sends an ack. If the recipient has read receipts off, you won't get `read` events.

**Fix:** Track per-message `status` based on the events you do receive. Don't assume 100% ack coverage.

---

## 12. Upgrades & Migrations

### 12.1 Migration Crash on PostgreSQL After Switching from SQLite

**Symptom:** After switching `DATABASE_TYPE=postgres`, the first boot crashes with `SQL syntax error`.

**Cause:** Old migrations were generated for SQLite syntax (`datetime('now')`).

**Fix:** Apply v0.1.6+ which auto-detects database type and uses appropriate syntax. Or regenerate migrations from scratch on PostgreSQL.

### 12.2 Webhook Format Changed After Upgrade

**Symptom:** Webhooks come in a different shape than your client expects.

**Cause:** OpenWA occasionally adds new fields to payloads.

**Fix:** Treat webhook payloads as "open schemas" — be tolerant of unknown fields, only require the ones you use.

---

## 13. Plugin Pitfalls

### 13.1 Plugin Hangs Block Webhook Delivery

**Symptom:** `webhook:delivered` events stop firing for a session.

**Cause:** A plugin's `webhook:delivered` hook is throwing or hanging.

**Fix:** Plugins should always wrap async work in try/catch and return quickly. Heavy processing should be offloaded to a queue.

---

## 14. Testing Pitfalls

### 14.1 Test Webhook Returns 200 but Signature Mismatch

**Symptom:** `POST /webhooks/{id}/test` reports success but your handler rejects the signature.

**Cause:** The test endpoint may use a different signing logic (or no signature at all).

**Fix:** Verify on actual `message.received` events, not on test events. Test events are for reachability, not signature verification.

### 14.2 Database State Leaks Between Tests

**Symptom:** After running tests, your dev DB has test sessions.

**Cause:** Not using `RefreshDatabase` or transactional tests.

**Fix:** Wrap tests in DB transactions:
```php
use Illuminate\Foundation\Testing\RefreshDatabase;
class OpenWaTest extends TestCase {
    use RefreshDatabase;
}
```

Or in Pest:
```php
uses(RefreshDatabase::class);
```

---

## 15. Network / Connectivity

### 15.1 "Network unreachable" in Container

**Symptom:** OpenWA can't reach WhatsApp servers.

**Cause:** Missing DNS, firewall rules, or NAT issues.

**Fix:**
- Use `network_mode: host` in docker-compose (Linux only)
- Or ensure the bridge network has internet access
- Check `iptables` rules: `iptables -L -n`

### 15.2 WhatsApp Bans the Number

**Symptom:** `SESSION_BANNED` or `MESSAGE_BLOCKED_CONTACT` for all users.

**Cause:** Sending too many messages too fast, or violating ToS.

**Fix:**
- Use `delayBetweenMessages` in bulk (5-10s minimum)
- Don't send marketing spam
- Warm up new numbers (start with low volume)
- Use a verified business number

---

## Quick Reference — Error Code Lookup

| Error Code                            | Category | HTTP | Quick Fix                          |
|---------------------------------------|----------|------|-------------------------------------|
| `UNAUTHORIZED`                        | Auth     | 401  | Check `X-API-Key` header            |
| `FORBIDDEN`                           | Auth     | 403  | Check role, IP whitelist            |
| `VALIDATION_ERROR`                    | General  | 400  | Check DTO field requirements        |
| `NOT_FOUND`                           | General  | 404  | Verify resource ID                  |
| `RATE_LIMITED`                        | General  | 429  | Backoff, respect `Retry-After`      |
| `SESSION_NOT_FOUND`                   | Session  | 404  | Verify session ID                   |
| `SESSION_NOT_READY`                   | Session  | 400  | Wait for `CONNECTED`                |
| `SESSION_ALREADY_EXISTS`              | Session  | 409  | Use a different name                |
| `SESSION_QR_EXPIRED`                  | Session  | 400  | Re-fetch QR                         |
| `SESSION_BANNED`                      | Session  | 403  | WhatsApp ban; manual recovery       |
| `MESSAGE_INVALID_CHAT_ID`             | Message  | 400  | Use `591...@c.us` format            |
| `MESSAGE_NUMBER_NOT_ON_WHATSAPP`      | Message  | 400  | Verify number with `contacts/check` |
| `MESSAGE_MEDIA_TOO_LARGE`             | Message  | 413  | Compress / resize                   |
| `MESSAGE_MEDIA_DOWNLOAD_FAILED`       | Message  | 400  | Verify media URL                    |
| `MESSAGE_MEDIA_INVALID_FORMAT`        | Message  | 400  | Use correct MIME                    |
| `MESSAGE_TEXT_TOO_LONG`               | Message  | 400  | Split into chunks                   |
| `MESSAGE_BLOCKED_CONTACT`             | Message  | 403  | Unblock or wait                     |
| `MESSAGE_RATE_LIMITED`                | Message  | 429  | Slow down                           |
| `WEBHOOK_URL_INVALID`                 | Webhook  | 400  | Use `https://` URL                  |
| `WEBHOOK_URL_UNREACHABLE`             | Webhook  | 400  | Test URL from OpenWA host           |
