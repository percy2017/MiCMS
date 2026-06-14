# Evolution API v2 — Endpoint Reference

Complete endpoint reference for Evolution API v2. Base URL: `https://{server-url}`

---

## 1. Get Information

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/` | Get Evolution API info (version, swagger URL, manager URL, docs URL) |

---

## 2. Instance Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/instance/create` | Create a new instance |
| GET | `/instance/fetchInstances` | Fetch all instances or a specific one |
| DELETE | `/instance/delete/{instance}` | Delete an instance |
| GET | `/instance/connectionState/{instance}` | Get connection state |
| PUT | `/instance/connect/{instance}` | Generate QR code for WhatsApp connection |
| PUT | `/instance/logout/{instance}` | Logout instance (disconnects but keeps config) |
| PUT | `/instance/restart/{instance}` | Restart instance |
| PUT | `/instance/setPresence/{instance}` | Set presence status (typing, recording, etc.) |

### Create Instance — Request Body

```json
{
  "instanceName": "my-instance",
  "integration": "WHATSAPP-BAILEYS",
  "qrcode": true,
  "reject_call": false,
  "groups_ignore": false,
  "always_online": false,
  "webhook": {
    "url": "https://your-server.com/api/webhooks/evolution/my-instance",
    "by_events": false,
    "base64": false,
    "events": [
      "messages.upsert",
      "messages.update",
      "send.message",
      "connection.update",
      "call"
    ]
  },
  "config": {
    "webhook": {
      "url": "https://your-server.com/api/webhooks/evolution/my-instance",
      "by_events": false,
      "base64": false,
      "events": [
        "messages.upsert",
        "messages.update",
        "send.message",
        "connection.update",
        "call"
      ]
    },
    "chatwoot": {
      "enabled": false
    }
  }
}
```

### Connection State — Response

```json
{
  "instance": "my-instance",
  "state": "open",
  "statusReason": 200
}
```

States: `open`, `connecting`, `close`

### Set Presence — Request Body

```json
{
  "presence": "typing"
}
```

Presence values: `typing`, `recording`, `available`, `unavailable`, `composing`

---

## 3. Chat / Contact Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/chat/findAllMessages/{instance}` | Find all messages (query: `where`, `page`, `limit`) |
| GET | `/chat/findContacts/{instance}` | List all contacts or filter by `where` |
| GET | `/chat/findChats/{instance}` | Find all chats |
| GET | `/chat/findStatusMessage/{instance}` | Find status messages (stories) |
| GET | `/chat/fetchProfilePictureUrl/{instance}` | Fetch profile picture URL |
| GET | `/chat/getBase64/{instance}` | Get base64 from media message |
| PUT | `/chat/markAsRead/{instance}` | Mark message as read |
| PUT | `/chat/markAsUnread/{instance}` | Mark message as unread |
| PUT | `/chat/archiveChat/{instance}` | Archive/unarchive chat |
| PUT | `/chat/updateBlockStatus/{instance}` | Block/unblock contacts |
| PUT | `/chat/updateMessage/{instance}` | Update message content |
| DELETE | `/chat/deleteMessageForEveryone/{instance}` | Delete message for everyone |
| POST | `/chat/sendPresence/{instance}` | Send presence (typing indicator) |
| POST | `/chat/checkIsWhatsApp/{instance}` | Check if numbers are registered on WhatsApp |

### Find Contacts — Query Parameters

```
GET /chat/findContacts/{instance}?where={"type":"private"}
```

### Check WhatsApp — Request Body

```json
{
  "numbers": ["59168964000", "59170000000"]
}
```

### Mark as Read — Request Body

```json
{
  "key": {
    "remoteJid": "59168964000@s.whatsapp.net",
    "fromMe": false,
    "id": "AC4BA5E114F79EAFD4112F7E48DD6145"
  }
}
```

### Send Presence — Request Body

```json
{
  "number": "59168964000",
  "presence": "composing",
  "delay": 3000
}
```

---

## 4. Message Sending

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/message/sendText/{instance}` | Send plain text message |
| POST | `/message/sendMedia/{instance}` | Send media (image, video, document, audio) |
| POST | `/message/sendAudio/{instance}` | Send audio (PTT/voice note) |
| POST | `/message/sendSticker/{instance}` | Send sticker |
| POST | `/message/sendContact/{instance}` | Send contact card |
| POST | `/message/sendLocation/{instance}` | Send location |
| POST | `/message/sendButton/{instance}` | Send buttons (up to 10) |
| POST | `/message/sendList/{instance}` | Send list/interactive menu |
| POST | `/message/sendPoll/{instance}` | Send poll/vote |
| POST | `/message/sendReaction/{instance}` | Send reaction to a message |
| POST | `/message/sendStatus/{instance}` | Post WhatsApp status (stories) |

### Send Text — Request Body

```json
{
  "number": "59168964000",
  "text": "Hello World!",
  "delay": 1200,
  "linkPreview": true,
  "mentionsEveryOne": false,
  "mentioned": ["59168964000"]
}
```

### Send Media — Request Body

```json
{
  "number": "59168964000",
  "mediatype": "image",
  "mimetype": "image/jpeg",
  "media": "https://example.com/image.jpg",
  "caption": "Optional caption",
  "fileName": "photo.jpg"
}
```

`mediatype` values: `image`, `video`, `document`, `audio`

Media can be a URL or base64-encoded string (when `media` field is used).

### Send Audio — Request Body

```json
{
  "number": "59168964000",
  "audio": "https://example.com/audio.mp3",
  "duration": 10,
  "asPTT": true
}
```

`asPTT: true` sends as voice note, `false` sends as audio file.

### Send Sticker — Request Body

```json
{
  "number": "59168964000",
  "sticker": "https://example.com/sticker.webp"
}
```

### Send Contact — Request Body

```json
{
  "number": "59168964000",
  "contactName": "John Doe",
  "contactOrg": "Acme Inc",
  "contactTitle": "Developer",
  "contactPhone": "59170000000",
  "contactEmail": "john@example.com",
  "contactUrl": "https://example.com",
  "contactAddress": "La Paz, Bolivia"
}
```

### Send Location — Request Body

```json
{
  "number": "59168964000",
  "name": "Office",
  "address": "Av. 6 de Octubre, La Paz",
  "latitude": -16.5000,
  "longitude": -68.1500
}
```

### Send Button — Request Body

```json
{
  "number": "59168964000",
  "title": "Choose an option",
  "description": "Select one of the following",
  "buttons": [
    {
      "buttonId": "btn1",
      "buttonText": { "displayText": "Option 1" },
      "type": 1
    },
    {
      "buttonId": "btn2",
      "buttonText": { "displayText": "Option 2" },
      "type": 1
    }
  ],
  "footerText": "Powered by Evolution API"
}
```

Button types: `1` = reply button, `2` = URL button, `3` = call button.

### Send List — Request Body

```json
{
  "number": "59168964000",
  "title": "Menu",
  "description": "Select an option",
  "buttonText": "Open Menu",
  "footerText": "Choose wisely",
  "sections": [
    {
      "title": "Section 1",
      "rows": [
        {
          "rowId": "row1",
          "title": "Option 1",
          "description": "Description for option 1"
        },
        {
          "rowId": "row2",
          "title": "Option 2",
          "description": "Description for option 2"
        }
      ]
    }
  ]
}
```

### Send Poll — Request Body

```json
{
  "number": "59168964000",
  "name": "What's your favorite color?",
  "selectableCount": 1,
  "values": ["Red", "Blue", "Green"]
}
```

`selectableCount: 1` = single choice, `>1` = multiple choice.

### Send Reaction — Request Body

```json
{
  "key": {
    "remoteJid": "59168964000@s.whatsapp.net",
    "fromMe": false,
    "id": "AC4BA5E114F79EAFD4112F7E48DD6145"
  },
  "reaction": "👍"
}
```

### Send Status — Request Body

```json
{
  "mediatype": "image",
  "media": "https://example.com/status.jpg",
  "caption": "My status update"
}
```

---

## 5. Group Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/group/fetchAllGroups/{instance}` | Fetch all groups |
| GET | `/group/findGroupByInviteCode/{instance}` | Find group by invite code |
| GET | `/group/findGroupByJid/{instance}` | Find group by remote JID |
| GET | `/group/findParticipants/{instance}` | Fetch all group members |
| GET | `/group/fetchInviteCode/{instance}` | Fetch group invite code |
| POST | `/group/create/{instance}` | Create group |
| POST | `/group/sendInvite/{instance}` | Send group invite |
| PUT | `/group/updateGroupPicture/{instance}` | Update group picture |
| PUT | `/group/updateGroupSubject/{instance}` | Update group name |
| PUT | `/group/updateGroupDescription/{instance}` | Update group description |
| PUT | `/group/updateGroupSetting/{instance}` | Update group settings |
| PUT | `/group/updateParticipant/{instance}` | Add/remove/promote/demote members |
| PUT | `/group/toggleEphemeral/{instance}` | Toggle temporary messages |
| PUT | `/group/revokeInviteCode/{instance}` | Revoke group invite code |
| DELETE | `/group/leaveGroup/{instance}` | Leave group |

### Create Group — Request Body

```json
{
  "subject": "My Group",
  "description": "Group description",
  "participants": [
    "59168964000@s.whatsapp.net",
    "59170000000@s.whatsapp.net"
  ]
}
```

### Update Participant — Request Body

```json
{
  "groupJid": "59168964000@g.us",
  "action": "add",
  "participants": ["59170000000@s.whatsapp.net"]
}
```

Actions: `add`, `remove`, `promote`, `demote`.

### Send Invite — Request Body

```json
{
  "groupJid": "59168964000@g.us",
  "description": "Join our group!",
  "numbers": ["59170000000"]
}
```

---

## 6. Profile Settings

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/profile/fetchProfile/{instance}` | Fetch profile from phone number |
| GET | `/profile/fetchBusinessProfile/{instance}` | Fetch business profile |
| GET | `/profile/fetchPrivacySettings/{instance}` | Fetch privacy settings |
| PUT | `/profile/updateProfileName/{instance}` | Update profile name |
| PUT | `/profile/updateProfilePicture/{instance}` | Update profile picture |
| PUT | `/profile/updateProfileStatus/{instance}` | Update profile status/about |
| PUT | `/profile/updatePrivacySettings/{instance}` | Update privacy settings |
| PUT | `/profile/removeProfilePicture/{instance}` | Remove profile picture |

### Update Profile Name — Request Body

```json
{
  "name": "My Business Name"
}
```

### Update Privacy Settings — Request Body

```json
{
  "privacySettings": {
    "readReceipts": "all",
    "lastSeen": "all",
    "profilePhoto": "all",
    "status": "contacts",
    "online": "all",
    "lastSeenVisibility": "all"
  }
}
```

---

## 7. Webhooks

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/webhook/find/{instance}` | Fetch current webhook config |
| POST | `/webhook/set/{instance}` | Set/update webhook for instance |

> **NOTE:** The endpoints `/webhook/findWebhook/{instance}` (GET) and `/webhook/setWebhook/{instance}` (PUT) shown in some docs are **deprecated or wrong**. The actual working endpoints are:
> - `GET /webhook/find/{instance}` to fetch
> - `POST /webhook/set/{instance}` to set/update
>
> The `PUT /webhook/setWebhook/{instance}` endpoint returns `Cannot PUT /webhook/setWebhook/{instance}` (HTTP 404).

### Set Webhook — Request Body

**Endpoint:** `POST /webhook/set/{instance}`

**Body shape:** The webhook config MUST be wrapped in a `webhook` object:

```json
{
  "webhook": {
    "enabled": true,
    "url": "https://your-server.com/api/webhooks/evolution/my-instance",
    "webhook_by_events": false,
    "webhook_base64": true,
    "events": [
      "MESSAGES_UPSERT"
    ]
  }
}
```

**Required fields:** `enabled` (boolean), `url` (string), `events` (array of uppercase event names).

**Note:** Event names in the request body use UPPERCASE (`MESSAGES_UPSERT`, `MESSAGES_UPDATE`, etc.), not lowercase.

### Reconfiguring Webhooks from CLI

This project provides an artisan command to reconfigure the webhook for all Evolution channels:

```bash
php artisan evolution:setup-webhook
# or for a specific channel:
php artisan evolution:setup-webhook --channel=1
```

By default this configures `MESSAGES_UPSERT` only (sufficient for simple chat apps).

---

## 8. Settings

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/settings/find/{instance}` | Fetch instance settings (reject_call, groups_ignore, always_online, read_messages, read_status, sync_full_history) |
| PUT | `/settings/set/{instance}` | Update instance settings |

---

## 9. Integrations

### Chatwoot

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/chatwoot/findChatwoot/{instance}` | Find Chatwoot config |
| PUT | `/chatwoot/setChatwoot/{instance}` | Set Chatwoot config |

### Dify

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/dify/create` | Create Dify bot |
| GET | `/dify/find` | Fetch all Dify bots |
| GET | `/dify/findBot` | Find Dify bot by ID |
| GET | `/dify/findSettings` | Find Dify settings |
| GET | `/dify/findStatus` | Get active sessions |
| PUT | `/dify/update` | Update Dify bot |
| PUT | `/dify/changeStatus` | Change bot status (opened/paused/closed) |
| PUT | `/dify/setSettings` | Update Dify settings |

### EvoAI

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/evoai/create` | Create EvoAI bot |
| GET | `/evoai/find` | Fetch all EvoAI bots |
| GET | `/evoai/findSettings` | Find EvoAI settings |
| GET | `/evoai/findStatus` | Get active sessions |
| PUT | `/evoai/update` | Update EvoAI bot |
| PUT | `/evoai/changeStatus` | Change bot status |
| PUT | `/evoai/setSettings` | Update EvoAI settings |

### Evolution Bot

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/evolutionBot/create` | Create Evolution bot |
| GET | `/evolutionBot/find` | Find all bots |
| GET | `/evolutionBot/fetchBot` | Fetch bot by ID |
| GET | `/evolutionBot/fetchSession` | Fetch bot session |
| GET | `/evolutionBot/findSettings` | Find bot settings |
| PUT | `/evolutionBot/update` | Update bot |
| PUT | `/evolutionBot/changeStatusSession` | Change session status |
| PUT | `/evolutionBot/setSettings` | Set bot settings |
| DELETE | `/evolutionBot/delete` | Delete bot |

### Flowise

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/flowise/createBot` | Create Flowise bot |
| GET | `/flowise/find` | Fetch all Flowise bots |
| GET | `/flowise/findBot` | Find bot by ID |
| GET | `/flowise/findSessions` | Fetch sessions |
| GET | `/flowise/findSettings` | Find settings |
| PUT | `/flowise/update` | Update bot |
| PUT | `/flowise/changeStatusSession` | Change session status |
| PUT | `/flowise/setSettings` | Set settings |
| DELETE | `/flowise/delete` | Delete bot |

### n8n

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/n8n/create` | Create n8n bot |
| GET | `/n8n/find` | Fetch all n8n bots |
| GET | `/n8n/findSettings` | Find settings |
| GET | `/n8n/findStatus` | Get active sessions |
| PUT | `/n8n/update` | Update bot |
| PUT | `/n8n/changeStatus` | Change bot status |
| PUT | `/n8n/setSettings` | Update settings |

### OpenAI

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/openai/createBot` | Create OpenAI bot |
| GET | `/openai/findBot` | Get bot config |
| GET | `/openai/findBots` | Get all bots |
| GET | `/openai/findCreds` | Get credentials |
| GET | `/openai/findSessions` | Get sessions |
| GET | `/openai/findSettings` | Find settings |
| PUT | `/openai/updateBot` | Update bot |
| PUT | `/openai/changeStatus` | Change status |
| PUT | `/openai/setCreds` | Set credentials |
| PUT | `/openai/setSettings` | Update settings |
| DELETE | `/openai/deleteBot` | Delete bot |
| DELETE | `/openai/deleteCreds` | Delete credentials |

### Typebot

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/typebot/create` | Create Typebot |
| GET | `/typebot/find` | Find all Typebots |
| GET | `/typebot/fetchTypebot` | Fetch Typebot by ID |
| GET | `/typebot/fetchSession` | Fetch session |
| GET | `/typebot/findSettings` | Find settings |
| PUT | `/typebot/update` | Update Typebot |
| PUT | `/typebot/start` | Start Typebot session |
| PUT | `/typebot/changeStatusSession` | Change session status |
| PUT | `/typebot/setSettings` | Set settings |
| DELETE | `/typebot/delete` | Delete Typebot |

### RabbitMQ

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/rabbitmq/findRabbitmq` | Find RabbitMQ config |
| PUT | `/rabbitmq/setRabbitmq` | Set RabbitMQ config |

### SQS

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/sqs/findSqs` | Find SQS config |
| PUT | `/sqs/setSqs` | Set SQS config |

### Websocket

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/websocket/findWebsocket` | Find Websocket config |
| PUT | `/websocket/setWebsocket` | Set Websocket config |

---

## OpenAPI Spec

Download the complete OpenAPI v2 spec for exact request/response schemas:
- **JSON**: `https://doc.evolution-api.com/openapi/openapi-v2.json`
