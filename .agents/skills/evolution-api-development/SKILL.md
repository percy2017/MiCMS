---
name: evolution-api-development
description: "Develops and extends Evolution API v2 integrations in this Laravel ChatBot module. Activates when working with WhatsApp webhooks, EvolutionApiClient, EvolutionChannel, message sending, media handling, group management, instance lifecycle, connection updates, call events, or any code under Modules/ChatBot/Channels/Evolution*. Also triggers when debugging webhook payloads, adding new message types, extending the HTTP client, or referencing Evolution API endpoints. Do not use for non-WhatsApp channels (web_widget), Blade views, or unrelated Laravel features."
license: MIT
metadata:
  author: laravel
---

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
| `Modules/ChatBot/Channels/EvolutionApiClient.php` | HTTP client wrapper for Evolution REST API |
| `Modules/ChatBot/Http/Controllers/Api/EvolutionWebhookController.php` | Receives webhook POSTs |
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
| Profile picture | GET | `/chat/fetchProfilePictureUrl/{instance}` |
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

## Common Pitfalls

1. **Missing `apikey` header** — Every request MUST include `apikey` header. Without it, you get 401.
2. **Confusing `remoteJid` with phone number** — `remoteJid` can be `59168964000@s.whatsapp.net` (user) or `59168964000@g.us` (group). Extract the number before `@`.
3. **Not filtering `fromMe`** — Webhooks include your own outgoing messages. Always check `$data['key']['fromMe'] === false` to avoid loops.
4. **Ignoring duplicate messages** — Evolution API may re-send webhooks. Check `external_id` before creating a Message.
5. **`send.message` only fires for outgoing** — Use `messages.upsert` with `fromMe: true` if you need outgoing message tracking, or handle `send.message` separately.
6. **`connecting` appears in two events** — Both `connection.update` and `APPLICATION_STARTUP` can emit `connecting` state.
7. **Not all `messageType` values have content** — Types like `albumMessage`, `protocolMessage`, `groupStatusMentionMessage` should be skipped explicitly.
8. **Instance must exist before sending** — Call `fetchInstances` to verify the instance name matches an active instance.
9. **Media URLs are temporary** — WhatsApp media URLs (`mmg.whatsapp.net`) expire. Download and store media promptly.
10. **Status values differ between events** — `messages.update` uses `SERVER_ACK`/`DELIVERY_ACK`/`READ`, while your DB uses `sent`/`delivered`/`read`. Always map them.
