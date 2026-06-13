# Writing an OpenWA Client (Laravel/PHP Example)

This document shows how to write a robust HTTP client for OpenWA from Laravel, with HMAC verification of incoming webhooks, idempotency handling, and all 92 endpoints supported.

---

## Architecture Overview

```
┌─────────────────┐  HTTP (Laravel → OpenWA)  ┌─────────────────┐
│ Laravel app     │ ◀────────────────────────▶│ OpenWA API      │
│ (your code)     │                           │ (Node.js)       │
│                 │  HTTP POST (webhook)      │                 │
│                 │ ◀────────────────────────│                 │
└─────────────────┘                           └─────────────────┘
        │                                              │
        │ WebSocket optional (for real-time)           │
        ▼                                              │
  useChatSync hook                                    ▼
                                                    WhatsApp
                                                    (engine)
```

The Laravel side is responsible for:
1. **Outbound**: Send messages, manage sessions, configure webhooks.
2. **Inbound**: Receive webhooks, verify HMAC, dedupe, persist messages.
3. **Optional WebSocket**: Real-time updates without polling.

---

## 1. Outbound Client

### Service Class

```php
<?php
// app/Services/OpenWa/OpenWaClient.php

namespace App\Services\OpenWa;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenWaClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeout = 15,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: rtrim(config('services.openwa.base_url'), '/'),
            apiKey: config('services.openwa.api_key'),
            timeout: config('services.openwa.timeout', 15),
        );
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'X-Request-ID' => 'req_' . now()->getTimestampMs(),
            'Accept' => 'application/json',
        ])
        ->timeout($this->timeout)
        ->retry(3, function (int $attempt, \Exception $e) {
            // Retry only on connection errors, not 4xx/5xx
            return $e instanceof \Illuminate\Http\Client\ConnectionException
                && $attempt < 3;
        }, throw: false);
    }

    // ============== SESSIONS ==============

    public function listSessions(): array
    {
        return $this->request()->get($this->baseUrl . '/sessions')->json('data') ?? [];
    }

    public function createSession(string $name, ?array $webhook = null): array
    {
        $payload = ['name' => $name];
        if ($webhook) $payload['webhook'] = $webhook;

        $response = $this->request()->post($this->baseUrl . '/sessions', $payload);
        $this->throwIfError($response);
        return $response->json('data');
    }

    public function getSession(string $sessionId): array
    {
        return $this->request()->get($this->baseUrl . "/sessions/{$sessionId}")->json('data');
    }

    public function startSession(string $sessionId): array
    {
        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/start");
        $this->throwIfError($response);
        return $response->json('data');
    }

    public function stopSession(string $sessionId): array
    {
        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/stop");
        $this->throwIfError($response);
        return $response->json('data');
    }

    public function getQrCode(string $sessionId): array
    {
        $response = $this->request()->get($this->baseUrl . "/sessions/{$sessionId}/qr");
        $this->throwIfError($response);
        return $response->json('data'); // ['code' => '...', 'image' => 'data:image/png;base64,...']
    }

    public function deleteSession(string $sessionId): void
    {
        $response = $this->request()->delete($this->baseUrl . "/sessions/{$sessionId}");
        $this->throwIfError($response);
    }

    // ============== MESSAGES ==============

    public function sendText(string $sessionId, string $chatId, string $text, ?array $options = null): array
    {
        $payload = ['chatId' => $chatId, 'text' => $text];
        if ($options) $payload['options'] = $options;

        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/messages/send-text", $payload);
        $this->throwIfError($response);
        return $response->json('data'); // ['messageId' => 'true_..._3EB0...', 'timestamp' => 1718...]
    }

    public function sendImage(string $sessionId, string $chatId, string $urlOrBase64, ?string $caption = null, ?string $mimetype = null): array
    {
        $isBase64 = str_starts_with($urlOrBase64, 'data:');
        $image = $isBase64 ? ['base64' => $urlOrBase64] : ['url' => $urlOrBase64];

        $payload = ['chatId' => $chatId, 'image' => $image, 'caption' => $caption];
        if ($isBase64 && $mimetype) $payload['mimetype'] = $mimetype;

        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/messages/send-image", $payload);
        $this->throwIfError($response);
        return $response->json('data');
    }

    public function sendVideo(string $sessionId, string $chatId, string $url, ?string $caption = null): array
    {
        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/messages/send-video", [
            'chatId' => $chatId,
            'video' => ['url' => $url],
            'caption' => $caption,
        ]);
        $this->throwIfError($response);
        return $response->json('data');
    }

    public function sendAudio(string $sessionId, string $chatId, string $url, bool $ptt = false, ?string $mimetype = null): array
    {
        $payload = [
            'chatId' => $chatId,
            'audio' => ['url' => $url],
            'ptt' => $ptt,
        ];
        if ($mimetype) $payload['mimetype'] = $mimetype;

        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/messages/send-audio", $payload);
        $this->throwIfError($response);
        return $response->json('data');
    }

    public function sendDocument(string $sessionId, string $chatId, string $url, string $filename, ?string $caption = null): array
    {
        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/messages/send-document", [
            'chatId' => $chatId,
            'document' => ['url' => $url],
            'filename' => $filename,
            'caption' => $caption,
        ]);
        $this->throwIfError($response);
        return $response->json('data');
    }

    public function sendSticker(string $sessionId, string $chatId, string $url): array
    {
        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/messages/send-sticker", [
            'chatId' => $chatId,
            'sticker' => ['url' => $url],
            'mimetype' => 'image/webp',
        ]);
        $this->throwIfError($response);
        return $response->json('data');
    }

    public function sendLocation(string $sessionId, string $chatId, float $lat, float $lng, ?string $description = null): array
    {
        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/messages/send-location", [
            'chatId' => $chatId,
            'latitude' => $lat,
            'longitude' => $lng,
            'description' => $description,
        ]);
        $this->throwIfError($response);
        return $response->json('data');
    }

    public function sendContact(string $sessionId, string $chatId, string $name, string $phone): array
    {
        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/messages/send-contact", [
            'chatId' => $chatId,
            'contact' => ['name' => $name, 'phone' => $phone],
        ]);
        $this->throwIfError($response);
        return $response->json('data');
    }

    public function reply(string $sessionId, string $chatId, string $quotedMessageId, string $text): array
    {
        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/messages/reply", [
            'chatId' => $chatId,
            'quotedMessageId' => $quotedMessageId,
            'text' => $text,
        ]);
        $this->throwIfError($response);
        return $response->json('data');
    }

    public function forward(string $sessionId, string $fromChatId, string $toChatId, string $messageId): array
    {
        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/messages/forward", [
            'fromChatId' => $fromChatId,
            'toChatId' => $toChatId,
            'messageId' => $messageId,
        ]);
        $this->throwIfError($response);
        return $response->json('data');
    }

    public function react(string $sessionId, string $chatId, string $messageId, string $emoji): void
    {
        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/messages/react", [
            'chatId' => $chatId,
            'messageId' => $messageId,
            'emoji' => $emoji,   // empty string removes reaction
        ]);
        $this->throwIfError($response);
    }

    public function deleteMessage(string $sessionId, string $chatId, string $messageId, bool $forEveryone = true): void
    {
        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/messages/delete", [
            'chatId' => $chatId,
            'messageId' => $messageId,
            'forEveryone' => $forEveryone,
        ]);
        $this->throwIfError($response);
    }

    public function getMessages(string $sessionId, ?string $chatId = null, int $limit = 50, int $offset = 0): array
    {
        $response = $this->request()->get($this->baseUrl . "/sessions/{$sessionId}/messages", [
            'chatId' => $chatId,
            'limit' => $limit,
            'offset' => $offset,
        ]);
        $this->throwIfError($response);
        return $response->json('data.messages') ?? [];
    }

    public function sendBulk(string $sessionId, array $messages, ?array $options = null): array
    {
        $payload = ['messages' => $messages];
        if ($options) $payload['options'] = $options;

        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/messages/send-bulk", $payload);
        $this->throwIfError($response);
        return $response->json('data');
    }

    // ============== WEBHOOKS ==============

    public function createWebhook(string $sessionId, string $url, array $events, ?string $secret = null, ?array $headers = null, int $retryCount = 3): array
    {
        $payload = [
            'url' => $url,
            'events' => $events,
            'retryCount' => $retryCount,
        ];
        if ($secret) $payload['secret'] = $secret;
        if ($headers) $payload['headers'] = $headers;

        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/webhooks", $payload);
        $this->throwIfError($response);
        return $response->json('data');
    }

    public function listWebhooks(string $sessionId): array
    {
        return $this->request()->get($this->baseUrl . "/sessions/{$sessionId}/webhooks")->json('data') ?? [];
    }

    public function deleteWebhook(string $sessionId, string $webhookId): void
    {
        $response = $this->request()->delete($this->baseUrl . "/sessions/{$sessionId}/webhooks/{$webhookId}");
        $this->throwIfError($response);
    }

    public function testWebhook(string $sessionId, string $webhookId): array
    {
        $response = $this->request()->post($this->baseUrl . "/sessions/{$sessionId}/webhooks/{$webhookId}/test");
        $this->throwIfError($response);
        return $response->json('data');
    }

    // ============== ERROR HANDLING ==============

    private function throwIfError($response): void
    {
        if ($response->successful()) return;

        $error = $response->json('error') ?? [];
        $code = $error['code'] ?? 'UNKNOWN';
        $message = $error['message'] ?? $response->body();

        match ($response->status()) {
            401 => throw new OpenWaAuthException($code, $message),
            403 => throw new OpenWaForbiddenException($code, $message),
            404 => throw new OpenWaNotFoundException($code, $message),
            413 => throw new OpenWaMediaTooLargeException($code, $message),
            429 => throw new OpenWaRateLimitedException($code, $message, (int) $response->header('Retry-After')),
            default => throw new OpenWaException($code, $message, $response->status()),
        };
    }
}
```

### Exception Classes

```php
<?php
namespace App\Services\OpenWa\Exceptions;

class OpenWaException extends \RuntimeException
{
    public function __construct(public readonly string $code, string $message, public readonly int $httpStatus = 500)
    {
        parent::__construct("[{$code}] {$message}", $httpStatus);
    }
}

class OpenWaAuthException extends OpenWaException { /* 401 */ }
class OpenWaForbiddenException extends OpenWaException { /* 403 */ }
class OpenWaNotFoundException extends OpenWaException { /* 404 */ }
class OpenWaMediaTooLargeException extends OpenWaException { /* 413 */ }
class OpenWaRateLimitedException extends OpenWaException
{
    public function __construct(string $code, string $message, public readonly int $retryAfter)
    {
        parent::__construct($code, $message, 429);
    }
}
```

### Service Provider (DI binding)

```php
<?php
// app/Providers/OpenWaServiceProvider.php

namespace App\Providers;

use App\Services\OpenWa\OpenWaClient;
use Illuminate\Support\ServiceProvider;

class OpenWaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenWaClient::class, fn () => OpenWaClient::fromConfig());
    }
}
```

Register in `config/app.php`:
```php
'providers' => [
    // ...
    App\Providers\OpenWaServiceProvider::class,
],
```

### Config

```php
<?php
// config/services.php

return [
    'openwa' => [
        'api_key' => env('OPENWA_API_KEY'),
        'base_url' => env('OPENWA_BASE_URL', 'http://localhost:2785/api'),
        'timeout' => env('OPENWA_TIMEOUT', 15),
    ],
];
```

```env
OPENWA_API_KEY=openwa_sk_...
OPENWA_BASE_URL=https://openwa.hostbol.lat/api
```

---

## 2. Inbound Webhook Controller

```php
<?php
// app/Http/Controllers/Webhooks/OpenWaWebhookController.php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\OpenWaMessage;
use App\Models\OpenWaSession;
use App\Services\OpenWa\OpenWaWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OpenWaWebhookController extends Controller
{
    public function __construct(
        private readonly OpenWaWebhookVerifier $verifier,
    ) {}

    public function handle(Request $request, string $channel): JsonResponse
    {
        // 1. Verify HMAC signature (constant-time)
        $secret = config("services.openwa.channels.{$channel}.secret");
        if (!$secret) {
            Log::warning('OpenWA webhook: unknown channel', ['channel' => $channel]);
            return response()->json(['error' => 'unknown_channel'], 404);
        }

        if (!$this->verifier->verify($request, $secret)) {
            Log::warning('OpenWA webhook: invalid signature', [
                'channel' => $channel,
                'signature' => $request->header('X-OpenWA-Signature'),
            ]);
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        // 2. Idempotency check (dedupe on idempotencyKey)
        $idempotencyKey = $request->header('X-OpenWA-Idempotency-Key');
        if ($idempotencyKey && Cache::has("openwa:webhook:{$idempotencyKey}")) {
            Log::info('OpenWA webhook: duplicate ignored', [
                'idempotencyKey' => $idempotencyKey,
                'retryCount' => $request->header('X-OpenWA-Retry-Count', 0),
            ]);
            return response()->json(['status' => 'duplicate_ignored']);
        }

        // 3. Lock + process
        $lock = Cache::lock("openwa:webhook:lock:{$idempotencyKey}", 60);
        if (!$lock->get()) {
            return response()->json(['status' => 'in_progress'], 423);
        }

        try {
            $this->dispatch($request);
            if ($idempotencyKey) {
                Cache::put("openwa:webhook:{$idempotencyKey}", true, now()->addDay());
            }
            return response()->json(['status' => 'processed']);
        } catch (\Throwable $e) {
            Log::error('OpenWA webhook processing failed', [
                'error' => $e->getMessage(),
                'event' => $request->input('event'),
                'idempotencyKey' => $idempotencyKey,
            ]);
            // Return 5xx to trigger retry
            return response()->json(['error' => 'processing_failed'], 500);
        } finally {
            $lock->release();
        }
    }

    private function dispatch(Request $request): void
    {
        $event = $request->input('event');
        $data = $request->input('data');
        $sessionId = $request->input('sessionId');

        match ($event) {
            'message.received' => $this->onMessageReceived($sessionId, $data),
            'message.sent' => $this->onMessageSent($sessionId, $data),
            'message.ack' => $this->onMessageAck($sessionId, $data),
            'message.revoked' => $this->onMessageRevoked($sessionId, $data),
            'session.status' => $this->onSessionStatus($sessionId, $data),
            'session.qr' => $this->onSessionQr($sessionId, $data),
            'session.authenticated' => $this->onSessionAuthenticated($sessionId, $data),
            'session.disconnected' => $this->onSessionDisconnected($sessionId, $data),
            'group.join', 'group.leave', 'group.update' => $this->onGroupEvent($event, $sessionId, $data),
            default => Log::info('OpenWA webhook: unknown event', ['event' => $event]),
        };
    }

    private function onMessageReceived(string $sessionId, array $data): void
    {
        $session = OpenWaSession::where('openwa_id', $sessionId)->first();
        if (!$session) {
            Log::warning('OpenWA: message for unknown session', ['sessionId' => $sessionId]);
            return;
        }

        $conversation = $this->getOrCreateConversation($session, $data['from']);

        OpenWaMessage::updateOrCreate(
            ['wa_message_id' => $data['id']],
            [
                'session_id' => $session->id,
                'conversation_id' => $conversation->id,
                'direction' => 'incoming',
                'type' => $this->mapType($data['type'] ?? 'chat'),
                'body' => $data['body'] ?? null,
                'from' => $data['from'] ?? null,
                'to' => $data['to'] ?? null,
                'is_group' => $data['isGroup'] ?? false,
                'has_media' => $data['hasMedia'] ?? false,
                'media_url' => $data['mediaUrl'] ?? null,
                'media_mimetype' => $data['mimetype'] ?? null,
                'wa_timestamp' => $data['waTimestamp'] ?? null,
                'metadata' => array_diff_key($data, array_flip(['id'])),
            ]
        );
    }

    private function onMessageAck(string $sessionId, array $data): void
    {
        $message = OpenWaMessage::where('wa_message_id', $data['messageId'])->first();
        if (!$message) {
            // ACK for a message we don't have (probably from another client)
            return;
        }

        $status = match ($data['ack'] ?? 0) {
            2 => 'sent',
            3 => 'delivered',
            4 => 'read',
            5 => 'read',    // played (audio)
            default => $message->status,
        };

        $message->update(['status' => $status]);
    }

    private function mapType(string $type): string
    {
        return match ($type) {
            'ptt' => 'audio',
            'vcard' => 'contact',
            default => $type,
        };
    }

    // Other handlers: onMessageSent, onMessageRevoked, onSessionStatus, etc.
}
```

### HMAC Verifier

```php
<?php
// app/Services/OpenWa/OpenWaWebhookVerifier.php

namespace App\Services\OpenWa;

use Illuminate\Http\Request;

class OpenWaWebhookVerifier
{
    public function verify(Request $request, string $secret): bool
    {
        $signature = $request->header('X-OpenWA-Signature');
        if (!$signature) return false;

        $rawBody = $request->getContent();   // RAW body, not parsed
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }
}
```

### Routes

```php
// routes/web.php (or routes/api.php)

use App\Http\Controllers\Webhooks\OpenWaWebhookController;

Route::post('/webhooks/openwa/{channel}', [OpenWaWebhookController::class, 'handle'])
    ->name('webhooks.openwa')
    ->withoutMiddleware(['web', 'auth', 'csrf']);
```

### Exempt from CSRF

In `app/Http/Middleware/VerifyCsrfToken.php`:
```php
protected $except = [
    'webhooks/openwa/*',
];
```

### Multi-Channel Config

```php
// config/services.php
'openwa' => [
    'api_key' => env('OPENWA_API_KEY'),
    'base_url' => env('OPENWA_BASE_URL'),
    'channels' => [
        'tigo1' => [
            'secret' => env('OPENWA_TIGO1_SECRET'),
            'session_name' => 'tigo1',
        ],
        'entel2' => [
            'secret' => env('OPENWA_ENTEL2_SECRET'),
            'session_name' => 'entel2',
        ],
    ],
],
```

### Setup Artisan Command

```php
<?php
// app/Console/Commands/OpenWaSetupWebhook.php

namespace App\Console\Commands;

use App\Services\OpenWa\OpenWaClient;
use Illuminate\Console\Command;

class OpenWaSetupWebhook extends Command
{
    protected $signature = 'openwa:setup-webhook {session} {--url=}';
    protected $description = 'Register/re-register the OpenWA webhook for a session';

    public function handle(OpenWaClient $client): int
    {
        $sessionName = $this->argument('session');
        $url = $this->option('url') ?? route('webhooks.openwa', ['channel' => $sessionName]);
        $secret = config("services.openwa.channels.{$sessionName}.secret");

        if (!$secret) {
            $this->error("No secret configured for channel '{$sessionName}'");
            return self::FAILURE;
        }

        // Delete existing webhooks
        $sessionId = $this->resolveSessionId($client, $sessionName);
        foreach ($client->listWebhooks($sessionId) as $webhook) {
            if ($webhook['url'] === $url) {
                $client->deleteWebhook($sessionId, $webhook['id']);
                $this->info("Deleted old webhook: {$webhook['id']}");
            }
        }

        // Create fresh
        $webhook = $client->createWebhook(
            sessionId: $sessionId,
            url: $url,
            events: ['message.received', 'message.ack', 'message.sent', 'session.status', 'session.qr'],
            secret: $secret,
            retryCount: 3,
        );

        $this->info("Webhook registered: {$webhook['id']}");
        $this->info("Events: " . implode(', ', $webhook['events']));
        return self::SUCCESS;
    }

    private function resolveSessionId(OpenWaClient $client, string $name): string
    {
        foreach ($client->listSessions() as $s) {
            if ($s['name'] === $name) return $s['id'];
        }

        $this->info("Session '{$name}' not found, creating...");
        return $client->createSession($name)['id'];
    }
}
```

Usage:
```bash
php artisan openwa:setup-webhook tigo1
php artisan openwa:setup-webhook entel2
```

---

## 3. WebSocket Client (Optional)

For real-time updates without polling, OpenWA exposes a WebSocket at `wss://hostbol.lat/ws?apiKey=...`.

**Browser:** Use the `WebSocket` API directly.

**Node.js:** Use `ws` or `socket.io-client`.

**Laravel (server-side):** Use a daemon with `react/socket` or `textalk/websocket`. For most cases, webhooks + occasional polling is sufficient.

Example (React):
```typescript
const ws = new WebSocket(`wss://openwa.hostbol.lat/ws?apiKey=${API_KEY}`);

ws.onopen = () => {
  ws.send(JSON.stringify({
    type: 'subscribe',
    payload: {
      sessionId: 'sess_abc',
      events: ['message.received', 'session.status'],
    },
  }));
};

ws.onmessage = (e) => {
  const msg = JSON.parse(e.data);
  if (msg.type === 'event') {
    console.log('event:', msg.payload.event, msg.payload.data);
  }
};

// Keep alive
setInterval(() => ws.send(JSON.stringify({ type: 'ping' })), 30000);
```

---

## 4. Error Mapping Table

| OpenWA Code                          | HTTP | Recommended Action                       |
|--------------------------------------|------|------------------------------------------|
| `UNAUTHORIZED`                       | 401  | Check API key, refresh if needed         |
| `FORBIDDEN`                          | 403  | Check role, IP whitelist                 |
| `SESSION_NOT_FOUND`                  | 404  | Verify session ID                        |
| `SESSION_NOT_READY`                  | 400  | Wait for `status: CONNECTED`             |
| `SESSION_BANNED`                     | 403  | WhatsApp banned; manual intervention     |
| `MESSAGE_NUMBER_NOT_ON_WHATSAPP`     | 400  | Verify phone number                      |
| `MESSAGE_MEDIA_TOO_LARGE`            | 413  | Compress / resize before sending         |
| `MESSAGE_MEDIA_DOWNLOAD_FAILED`      | 400  | Verify media URL is reachable            |
| `MESSAGE_MEDIA_INVALID_FORMAT`       | 400  | Use correct MIME (e.g., WebP for stickers) |
| `MESSAGE_TEXT_TOO_LONG`              | 400  | Split into multiple messages             |
| `MESSAGE_BLOCKED_CONTACT`            | 403  | Unblock contact first                    |
| `MESSAGE_RATE_LIMITED`               | 429  | Respect `Retry-After` header             |
| `RATE_LIMITED`                       | 429  | Reduce request frequency                 |

---

## 5. Testing the Integration

```php
<?php
// tests/Feature/OpenWaWebhookTest.php

use App\Models\OpenWaMessage;
use Illuminate\Support\Facades\Cache;

it('processes incoming message webhook', function () {
    Cache::flush();

    $payload = [
        'event' => 'message.received',
        'sessionId' => 'sess_test',
        'timestamp' => now()->toIso8601String(),
        'idempotencyKey' => 'msg_test_1',
        'deliveryId' => 'dlv_test_1',
        'data' => [
            'id' => 'false_59169387181@c.us_3EB0ABC',
            'from' => '59169387181@c.us',
            'to' => '59169387555@c.us',
            'body' => 'Hello!',
            'type' => 'chat',
            'waTimestamp' => now()->timestamp,
            'timestamp' => now()->toIso8601String(),
            'isGroup' => false,
            'hasMedia' => false,
        ],
    ];

    $rawBody = json_encode($payload);
    $secret = config('services.openwa.channels.tigo1.secret');
    $signature = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

    $this->call(
        'POST',
        route('webhooks.openwa', ['channel' => 'tigo1']),
        [], [], [],
        ['HTTP_X-OPENWA-SIGNATURE' => $signature, 'HTTP_X-OPENWA-IDEMPOTENCY-KEY' => 'msg_test_1'],
        $rawBody
    )->assertOk();

    expect(OpenWaMessage::where('wa_message_id', 'false_59169387181@c.us_3EB0ABC')->exists())->toBeTrue();
});

it('rejects invalid signature', function () {
    $this->call(
        'POST',
        route('webhooks.openwa', ['channel' => 'tigo1']),
        [], [], [],
        ['HTTP_X-OPENWA-SIGNATURE' => 'sha256=invalid'],
        '{"event":"message.received"}'
    )->assertStatus(401);
});

it('dedupes on idempotency key', function () {
    Cache::flush();
    $payload = [...];
    $rawBody = json_encode($payload);
    $secret = config('services.openwa.channels.tigo1.secret');
    $signature = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

    $headers = [
        'HTTP_X-OPENWA-SIGNATURE' => $signature,
        'HTTP_X-OPENWA-IDEMPOTENCY-KEY' => 'msg_dedupe_test',
    ];

    // First call: processed
    $this->call('POST', route('webhooks.openwa', ['channel' => 'tigo1']), [], [], [], $headers, $rawBody)
        ->assertOk()
        ->assertJson(['status' => 'processed']);

    // Second call: duplicate_ignored
    $this->call('POST', route('webhooks.openwa', ['channel' => 'tigo1']), [], [], [], $headers, $rawBody)
        ->assertOk()
        ->assertJson(['status' => 'duplicate_ignored']);
});
```

---

## 6. Channel Driver Pattern (if integrating with existing ChatBot module)

If you have an existing `ChannelInterface` (e.g., for Evolution), you can wrap OpenWA the same way:

```php
// Modules/ChatBot/Channels/OpenWa/OpenWaChannel.php

namespace Modules\ChatBot\Channels\OpenWa;

use App\Services\OpenWa\OpenWaClient;
use Modules\ChatBot\Channels\ChannelInterface;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Message;

class OpenWaChannel implements ChannelInterface
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly array $config,
    ) {}

    public function sendText(Channel $channel, string $chatId, string $text, ?array $options = null): array
    {
        return $this->client->sendText($channel->external_id, $chatId, $text, $options);
    }

    public function sendImage(Channel $channel, string $chatId, string $url, ?string $caption = null): array
    {
        return $this->client->sendImage($channel->external_id, $chatId, $url, $caption);
    }

    // ... other send methods
}
```

Register in `ChannelRegistry`:
```php
ChannelType::OpenWa => new OpenWaChannel($client, $config),
```

This lets you reuse the rest of your existing chat logic with the new provider.

---

## 7. Multi-Session Best Practices

1. **One webhook per session** — OpenWA's webhooks are session-scoped, so you need to register one per session.
2. **Identify sessions by `name` in your app** — use the OpenWA session `name` (e.g., `tigo1`) as your local channel identifier, not the UUID.
3. **One Laravel route, multiple channels** — use the `{channel}` URL param to route to the right session in the controller.
4. **Map `sessionId` to your local model** — store the OpenWA `sessionId` (UUID) on your `Channel` model for direct lookups.
5. **Subscribe to all critical events per session** — don't try to consolidate webhooks across sessions.
