# Evolution API — Channel Architecture Reference

How the channel driver pattern works and how to extend it.

---

## Overview

The ChatBot module uses a **Strategy + Registry** pattern for channel abstraction:

```
┌─────────────────────────────────────────────────────────┐
│                    ChannelInterface                       │
│  type() · sendMessage() · processIncoming() · stats()   │
└──────────┬─────────────────────────┬────────────────────┘
           │                         │
    ┌──────▼──────┐          ┌───────▼───────┐
    │ Evolution   │          │  WebWidget    │
    │ Channel     │          │  Channel      │
    └──────┬──────┘          └───────┬───────┘
           │                         │
    ┌──────▼──────┐          ┌───────▼───────┐
    │ Evolution   │          │  (built-in)   │
    │ ApiClient   │          │               │
    └─────────────┘          └───────────────┘
```

---

## ChannelInterface Contract

Location: `Modules/ChatBot/Channels/ChannelInterface.php`

```php
interface ChannelInterface
{
    public function type(): ChannelType;
    public function name(): string;
    public function description(): string;
    public function icon(): string;
    public function accentColor(): string;
    public function configFields(): array;
    public function settingsFields(): array;
    public function boot(Channel $channel): void;
    public function shutdown(Channel $channel): void;
    public function sendMessage(Conversation $conversation, Message $message): array;
    public function processIncoming(array $payload, Channel $channel): ?Message;
    public function stats(Channel $channel): array;
}
```

### Method Responsibilities

| Method | Purpose |
|--------|---------|
| `type()` | Returns the `ChannelType` enum value |
| `name()` | Human-readable name for admin UI |
| `description()` | Short description for admin UI |
| `icon()` | Lucide icon name for admin UI |
| `accentColor()` | Hex color for UI theming |
| `configFields()` | Defines required config fields (server_url, api_key, etc.) |
| `settingsFields()` | Defines optional settings fields (display_name, auto_reply, etc.) |
| `boot()` | Called when channel is loaded (subscribe to events, etc.) |
| `shutdown()` | Called when channel is unloaded |
| `sendMessage()` | Sends a message via the channel's API |
| `processIncoming()` | Parses webhook payload and persists message |
| `stats()` | Returns connection stats for admin dashboard |

---

## ChannelRegistry

Location: `Modules/ChatBot/Channels/ChannelRegistry.php`

```php
class ChannelRegistry
{
    private array $drivers = [];

    public function register(ChannelInterface $driver): void
    {
        $this->drivers[$driver->type()->value] = $driver;
    }

    public function get(ChannelType $type): ?ChannelInterface
    {
        return $this->drivers[$type->value] ?? null;
    }

    public function all(): array
    {
        return $this->drivers;
    }
}
```

### Registration in Service Provider

```php
// Modules/ChatBot/Providers/ChatBotServiceProvider.php

protected function registerChannels(): void
{
    $registry = $this->app->make(ChannelRegistry::class);
    $registry->register(new WebWidgetChannel);
    $registry->register(new EvolutionChannel);
}
```

---

## Inbound Flow (Webhook → DB)

```
1. POST /api/webhooks/evolution/{channel}
       │
2. EvolutionWebhookController::handle()
       │  - Validates channel exists and is enabled
       │  - Extracts instance name from route or payload
       │
3. MessageIngestor::ingest($payload, $channel)
       │  - Resolves driver via ChannelRegistry::get($channel->type)
       │  - Calls $driver->processIncoming($payload, $channel)
       │  - Broadcasts ChatBotMessageReceived event
       │
4. EvolutionChannel::processIncoming($payload, $channel)
       │  - Filters events (only messages.upsert, messages.update, call)
       │  - Filters fromMe, broadcast, newsletter, duplicates
       │  - Extracts content from message type
       │  - Creates/updates User, Conversation, Message
       │  - Returns Message or null
       │
5. MessageIngestor broadcasts ChatBotMessageReceived
       │
6. Frontend receives via Reverb/WebSocket
```

### Code Path — processIncoming()

```php
public function processIncoming(array $payload, Channel $channel): ?Message
{
    $event = $payload['event'] ?? '';

    // 1. Filter events
    if (! in_array($event, ['messages.upsert', 'messages.update', 'call'])) {
        return null;
    }

    // 2. Extract data
    $data = $payload['data'] ?? [];
    $key = $data['key'] ?? [];
    $remoteJid = $key['remoteJid'] ?? null;
    $fromMe = $key['fromMe'] ?? false;

    // 3. Filter incoming only
    if ($fromMe || ! $remoteJid) {
        return null;
    }

    // 4. Extract content based on messageType
    $content = match (true) {
        ! empty($messageData['conversation']) => $messageData['conversation'],
        ! empty($messageData['extendedTextMessage']['text']) => $messageData['extendedTextMessage']['text'],
        ! empty($messageData['imageMessage']['caption']) => $messageData['imageMessage']['caption'],
        // ... more types
        default => '[Mensaje no soportado]',
    };

    // 5. Create/find User
    $user = User::firstOrCreate(
        ['email' => "{$remoteJid}@whatsapp"],
        ['name' => $pushName, 'password' => Hash::make(str()->random(32))]
    );

    // 6. Create/find Conversation
    $conversation = Conversation::firstOrCreate(
        ['channel_id' => $channel->id, 'external_id' => $remoteJid],
        ['user_id' => $user->id, 'status' => ConversationStatus::Open]
    );

    // 7. Create Message
    return Message::create([
        'conversation_id' => $conversation->id,
        'role' => Message::ROLE_USER,
        'type' => $type,
        'content' => $content,
        'external_id' => $key['id'] ?? null,
    ]);
}
```

---

## Outbound Flow (Admin → WhatsApp)

```
1. Admin clicks "Reply" in chat UI
       │
2. ChannelManager::dispatch($conversation, $message)
       │  - Resolves driver via ChannelRegistry::get($conversation->channel->type)
       │  - Calls $driver->sendMessage($conversation, $message)
       │
3. EvolutionChannel::sendMessage($conversation, $message)
       │  - Builds EvolutionApiClient from channel config
       │  - Selects sendText or sendMedia based on MessageType
       │  - Calls Evolution API endpoint
       │  - Returns ['ok' => true/false, 'provider_id' => ..., 'raw' => ...]
       │
4. ChannelManager updates Message status
       │
5. Evolution API sends message to WhatsApp
       │
6. WhatsApp delivers → evolution sends messages.update webhook
```

### Code Path — sendMessage()

```php
public function sendMessage(Conversation $conversation, Message $message): array
{
    $config = $conversation->channel->config ?? [];
    $client = $this->buildClient($config);
    $number = $conversation->external_id;

    if (! $number) {
        return ['ok' => false, 'error' => 'No destination number.'];
    }

    if ($message->type === MessageType::Text) {
        $response = $client->sendText([
            'number' => $number,
            'text' => $message->content,
        ]);
    } else {
        $response = $client->sendMedia([
            'number' => $number,
            'mediatype' => $this->mapMediaType($message->type),
            'mimetype' => $this->guessMime($message->type),
            'caption' => $message->content,
        ]);
    }

    if ($response->successful()) {
        $body = $response->json();
        return ['ok' => true, 'provider_id' => $body['key']['id'] ?? null, 'raw' => $body];
    }

    return ['ok' => false, 'error' => $response->body()];
}
```

---

## Extending the Architecture

### Adding a New Channel Type

1. **Add enum value** in `Modules/ChatBot/Enums/ChannelType.php`:
   ```php
   case Telegram = 'telegram';
   ```

2. **Create channel driver** implementing `ChannelInterface`:
   ```php
   // Modules/ChatBot/Channels/TelegramChannel.php
   class TelegramChannel implements ChannelInterface
   {
       public function type(): ChannelType
       {
           return ChannelType::Telegram;
       }
       // ... implement all methods
   }
   ```

3. **Register in service provider**:
   ```php
   $registry->register(new TelegramChannel);
   ```

4. **Add migration** if the channel type needs new config fields

### Adding Features to EvolutionChannel

The most common extensions:

| Feature | What to modify |
|---------|---------------|
| New message type | `processIncoming()` content extraction + `sendMessage()` match |
| New webhook event | `processIncoming()` event filter + new handler block |
| New API call | `EvolutionApiClient` new method + `EvolutionChannel` new public method |
| New admin setting | `settingsFields()` array + usage in relevant methods |
| New webhook event storage | Controller filter + `processIncoming()` handler |

---

## Database Schema Reference

### channels table

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `type` | enum | `web_widget` or `evolution` |
| `name` | string | Display name |
| `config` | encrypted JSON | `server_url`, `api_key`, `instance_name` |
| `settings` | JSON | `display_name`, `auto_reply`, etc. |
| `is_active` | boolean | Whether channel is enabled |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### conversations table

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `channel_id` | uuid | FK → channels |
| `external_id` | string | WhatsApp JID (`59168964000@s.whatsapp.net`) |
| `user_id` | uuid | FK → users |
| `visitor_name` | string | Display name from pushName |
| `visitor_email` | string | Generated `{jid}@whatsapp` |
| `status` | enum | `open`, `closed`, `archived` |
| `last_message_at` | timestamp | |
| `unread_by_admin` | integer | Unread message count |

### messages table

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `conversation_id` | uuid | FK → conversations |
| `role` | enum | `user` or `agent` |
| `type` | enum | `text`, `image`, `video`, `audio`, `file`, `sticker`, `location`, `contact` |
| `content` | text | Message text or description |
| `external_id` | string | WhatsApp message ID |
| `status` | enum | `pending`, `sent`, `delivered`, `read`, `failed` |
| `metadata` | JSON | Additional data (pushName, remoteJid, etc.) |
| `created_at` | timestamp | |
