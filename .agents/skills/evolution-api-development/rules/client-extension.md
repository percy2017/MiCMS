# Evolution API v2 — Extending the HTTP Client

How to add new methods to `EvolutionApiClient` following existing patterns.

---

## Current Client Location

`Modules/ChatBot/Channels/EvolutionApiClient.php`

## Existing Methods

| Method | HTTP | Endpoint |
|--------|------|----------|
| `fetchInstances()` | GET | `/instance/fetchInstances` |
| `connectionState()` | GET | `/instance/connectionState/{instance}` |
| `sendText(array $params)` | POST | `/message/sendText/{instance}` |
| `sendMedia(array $params)` | POST | `/message/sendMedia/{instance}` |

---

## Pattern for Adding New Methods

### Read-only endpoints (GET)

```php
public function findContacts(?string $where = null): Response
{
    $url = "{$this->serverUrl}/chat/findContacts/{$this->instanceName}";
    if ($where) {
        $url .= '?' . http_build_query(['where' => $where]);
    }

    return Http::withHeaders($this->headers())
        ->get($url);
}
```

### Mutating endpoints (POST)

```php
/**
 * @param  array{number: string, text: string, delay?: int}  $params
 */
public function sendReaction(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->post("{$this->serverUrl}/message/sendReaction/{$this->instanceName}", $params);
}
```

### PUT endpoints

```php
public function markAsRead(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->put("{$this->serverUrl}/chat/markAsRead/{$this->instanceName}", $params);
}
```

### DELETE endpoints

```php
public function deleteMessage(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->delete("{$this->serverUrl}/chat/deleteMessageForEveryone/{$this->instanceName}", $params);
}
```

---

## Complete Extension Example — Adding All Missing Methods

```php
// --- Chat / Contacts ---

public function findContacts(?string $where = null): Response
{
    $query = $where ? '?' . http_build_query(['where' => $where]) : '';
    return Http::withHeaders($this->headers())
        ->get("{$this->serverUrl}/chat/findContacts/{$this->instanceName}{$query}");
}

public function findChats(): Response
{
    return Http::withHeaders($this->headers())
        ->get("{$this->serverUrl}/chat/findChats/{$this->instanceName}");
}

public function findAllMessages(?string $where = null, int $page = 1, int $limit = 50): Response
{
    $query = http_build_query(array_filter([
        'where' => $where,
        'page' => $page,
        'limit' => $limit,
    ]));
    return Http::withHeaders($this->headers())
        ->get("{$this->serverUrl}/chat/findAllMessages/{$this->instanceName}?{$query}");
}

public function markAsRead(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->put("{$this->serverUrl}/chat/markAsRead/{$this->instanceName}", $params);
}

public function markAsUnread(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->put("{$this->serverUrl}/chat/markAsUnread/{$this->instanceName}", $params);
}

public function archiveChat(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->put("{$this->serverUrl}/chat/archiveChat/{$this->instanceName}", $params);
}

public function updateBlockStatus(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->put("{$this->serverUrl}/chat/updateBlockStatus/{$this->instanceName}", $params);
}

public function checkIsWhatsApp(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->post("{$this->serverUrl}/chat/checkIsWhatsApp/{$this->instanceName}", $params);
}

public function fetchProfilePictureUrl(string $number): Response
{
    return Http::withHeaders($this->headers())
        ->get("{$this->serverUrl}/chat/fetchProfilePictureUrl/{$this->instanceName}?number={$number}");
}

// --- Messages ---

public function sendAudio(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->post("{$this->serverUrl}/message/sendAudio/{$this->instanceName}", $params);
}

public function sendSticker(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->post("{$this->serverUrl}/message/sendSticker/{$this->instanceName}", $params);
}

public function sendContact(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->post("{$this->serverUrl}/message/sendContact/{$this->instanceName}", $params);
}

public function sendLocation(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->post("{$this->serverUrl}/message/sendLocation/{$this->instanceName}", $params);
}

public function sendButton(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->post("{$this->serverUrl}/message/sendButton/{$this->instanceName}", $params);
}

public function sendList(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->post("{$this->serverUrl}/message/sendList/{$this->instanceName}", $params);
}

public function sendPoll(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->post("{$this->serverUrl}/message/sendPoll/{$this->instanceName}", $params);
}

public function sendReaction(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->post("{$this->serverUrl}/message/sendReaction/{$this->instanceName}", $params);
}

public function sendStatus(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->post("{$this->serverUrl}/message/sendStatus/{$this->instanceName}", $params);
}

// --- Instance Management ---

public function createInstance(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->post("{$this->serverUrl}/instance/create", $params);
}

public function deleteInstance(): Response
{
    return Http::withHeaders($this->headers())
        ->delete("{$this->serverUrl}/instance/delete/{$this->instanceName}");
}

public function logout(): Response
{
    return Http::withHeaders($this->headers())
        ->put("{$this->serverUrl}/instance/logout/{$this->instanceName}");
}

public function restart(): Response
{
    return Http::withHeaders($this->headers())
        ->put("{$this->serverUrl}/instance/restart/{$this->instanceName}");
}

public function setPresence(string $presence): Response
{
    return Http::withHeaders($this->headers())
        ->put("{$this->serverUrl}/instance/setPresence/{$this->instanceName}", [
            'presence' => $presence,
        ]);
}

// --- Groups ---

public function fetchAllGroups(): Response
{
    return Http::withHeaders($this->headers())
        ->get("{$this->serverUrl}/group/fetchAllGroups/{$this->instanceName}");
}

public function createGroup(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->post("{$this->serverUrl}/group/create/{$this->instanceName}", $params);
}

public function updateParticipant(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->put("{$this->serverUrl}/group/updateParticipant/{$this->instanceName}", $params);
}

// --- Profile ---

public function fetchProfile(string $number): Response
{
    return Http::withHeaders($this->headers())
        ->get("{$this->serverUrl}/profile/fetchProfile/{$this->instanceName}?number={$number}");
}

public function updateProfileName(string $name): Response
{
    return Http::withHeaders($this->headers())
        ->put("{$this->serverUrl}/profile/updateProfileName/{$this->instanceName}", [
            'name' => $name,
        ]);
}

public function updateProfileStatus(string $status): Response
{
    return Http::withHeaders($this->headers())
        ->put("{$this->serverUrl}/profile/updateProfileStatus/{$this->instanceName}", [
            'status' => $status,
        ]);
}

// --- Webhooks ---

public function findWebhook(): Response
{
    return Http::withHeaders($this->headers())
        ->get("{$this->serverUrl}/webhook/findWebhook/{$this->instanceName}");
}

public function setWebhook(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->put("{$this->serverUrl}/webhook/setWebhook/{$this->instanceName}", $params);
}

// --- Settings ---

public function findSettings(): Response
{
    return Http::withHeaders($this->headers())
        ->get("{$this->serverUrl}/settings/findSettings/{$this->instanceName}");
}

public function setSettings(array $params): Response
{
    return Http::withHeaders($this->headers())
        ->put("{$this->serverUrl}/settings/setSettings/{$this->instanceName}", $params);
}
```

---

## Integrating New Methods into EvolutionChannel

After adding methods to the client, use them in the channel driver:

```php
// In EvolutionChannel::sendMessage(), extend the match:
public function sendMessage(Conversation $conversation, Message $message): array
{
    $config = $conversation->channel->config ?? [];
    $client = $this->buildClient($config);
    $number = $conversation->external_id;

    $response = match ($message->type) {
        MessageType::Text => $client->sendText([
            'number' => $number,
            'text' => $message->content,
        ]),
        MessageType::Image => $client->sendMedia([
            'number' => $number,
            'mediatype' => 'image',
            'mimetype' => 'image/jpeg',
            'caption' => $message->content,
        ]),
        MessageType::Video => $client->sendMedia([
            'number' => $number,
            'mediatype' => 'video',
            'mimetype' => 'video/mp4',
            'caption' => $message->content,
        ]),
        MessageType::Audio => $client->sendAudio([
            'number' => $number,
            'audio' => $message->media_url,
            'asPTT' => true,
        ]),
        MessageType::Sticker => $client->sendSticker([
            'number' => $number,
            'sticker' => $message->media_url,
        ]),
        default => $client->sendText([
            'number' => $number,
            'text' => $message->content,
        ]),
    };

    // ... handle response
}
```

---

## Error Handling

Always check the response before accessing the body:

```php
$response = $client->sendText([...]);

if ($response->successful()) {
    $body = $response->json();
    // Process success
} elseif ($response->clientError()) {
    // 4xx — auth issues, bad request, instance not found
    Log::warning('Evolution API client error', [
        'status' => $response->status(),
        'body' => $response->body(),
    ]);
} elseif ($response->serverError()) {
    // 5xx — Evolution API server issue
    Log::error('Evolution API server error', [
        'status' => $response->status(),
        'body' => $response->body(),
    ]);
}
```

---

## Common HTTP Status Codes

| Code | Meaning | Common Cause |
|------|---------|--------------|
| 200 | Success | Request processed |
| 201 | Created | Instance/resource created |
| 400 | Bad Request | Invalid params, missing fields |
| 401 | Unauthorized | Missing or invalid `apikey` header |
| 403 | Forbidden | API key doesn't have permission |
| 404 | Not Found | Instance doesn't exist |
| 500 | Server Error | Evolution API internal error |

---

## Testing

Use Laravel's `Http::fake()` to test client methods without hitting the real API:

```php
use Illuminate\Support\Facades\Http;

it('sends text message successfully', function () {
    Http::fake([
        'https://evolution.example.com/message/sendText/*' => Http::response([
            'key' => ['id' => 'test-123', 'remoteJid' => '59168964000@s.whatsapp.net', 'fromMe' => true],
            'messageTimestamp' => now()->timestamp,
        ], 200),
    ]);

    $client = new EvolutionApiClient(
        serverUrl: 'https://evolution.example.com',
        apiKey: 'test-key',
        instanceName: 'test-instance',
    );

    $response = $client->sendText([
        'number' => '59168964000',
        'text' => 'Hello!',
    ]);

    expect($response->successful())->toBeTrue();
    expect($response->json('key.id'))->toBe('test-123');
});
```
