<?php

use App\Models\User;
use Modules\ChatBot\Channels\OpenWa\OpenWaChannel;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Enums\MessageType;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

beforeEach(function (): void {
    $this->channel = Channel::factory()->openwa()->create(['enabled' => true]);
});

test('webhook entrante message.received AUTO-CREA user con email owa-{phone}@openwa.local', function (): void {
    $payload = [
        'event' => 'message.received',
        'timestamp' => '2026-06-12T10:00:00.000Z',
        'sessionId' => 'sess_test',
        'idempotencyKey' => 'msg_test_1',
        'data' => [
            'id' => 'false_59169387181@c.us_3EB0ABC',
            'from' => '59169387181@c.us',
            'to' => '59169387555@c.us',
            'body' => 'Hola desde OpenWA',
            'type' => 'chat',
            'waTimestamp' => 1718188800,
            'timestamp' => '2026-06-12T10:00:00.000Z',
            'isGroup' => false,
            'hasMedia' => false,
            'contact' => ['pushName' => 'Visitante OpenWA'],
        ],
    ];

    $channel = new OpenWaChannel;
    $message = $channel->processIncoming($payload, $this->channel);

    expect($message)->toBeInstanceOf(Message::class);
    expect($message->content)->toBe('Hola desde OpenWA');
    expect($message->role)->toBe('user');
    expect($message->type)->toBe(MessageType::Text);
    expect($message->external_id)->toBe('3EB0ABC');

    $conv = Conversation::where('external_id', '59169387181@c.us')->first();
    expect($conv)->not->toBeNull();
    expect($conv->user_id)->not->toBeNull();

    $user = User::find($conv->user_id);
    expect($user->name)->toBe('Visitante OpenWA');
    expect($user->phone)->toBe('59169387181');
    expect($user->whatsapp_jid)->toBe('59169387181@c.us');
    expect($user->email)->toBe('owa-59169387181@openwa.local');
    expect($user->hasRole('user'))->toBeTrue();
});

test('webhook con fromMe=true (message.sent) se guarda como admin (echo)', function (): void {
    $payload = [
        'event' => 'message.sent',
        'sessionId' => 'sess_test',
        'idempotencyKey' => 'msg_sent_1',
        'data' => [
            'id' => 'true_59169387181@c.us_3EB0SENT',
            'from' => '59169387555@c.us',
            'to' => '59169387181@c.us',
            'body' => 'Respuesta admin',
            'type' => 'chat',
            'waTimestamp' => 1718188900,
        ],
    ];

    $message = (new OpenWaChannel)->processIncoming($payload, $this->channel);

    expect($message)->toBeInstanceOf(Message::class);
    expect($message->role)->toBe('admin');
    expect($message->content)->toBe('Respuesta admin');
    expect($message->external_id)->toBe('3EB0SENT');
});

test('webhook duplicado por external_id es ignorado (idempotente)', function (): void {
    $payload = [
        'event' => 'message.received',
        'data' => [
            'id' => 'false_59111111111@c.us_3EB0DUP',
            'from' => '59111111111@c.us',
            'to' => '59169387555@c.us',
            'body' => 'No duplicar',
            'type' => 'chat',
        ],
    ];

    $first = (new OpenWaChannel)->processIncoming($payload, $this->channel);
    expect($first)->toBeInstanceOf(Message::class);

    $second = (new OpenWaChannel)->processIncoming($payload, $this->channel);
    expect($second)->toBeNull();
});

test('webhook de imagen guarda tipo Image y metadata con media_url', function (): void {
    $payload = [
        'event' => 'message.received',
        'data' => [
            'id' => 'false_59122222222@c.us_3EB0IMG',
            'from' => '59122222222@c.us',
            'to' => '59169387555@c.us',
            'body' => 'Mira esto',
            'type' => 'image',
            'mimetype' => 'image/jpeg',
            'mediaUrl' => 'https://mmg.whatsapp.net/v/t62/img',
            'fileLength' => 50000,
            'width' => 800,
            'height' => 600,
        ],
    ];

    $message = (new OpenWaChannel)->processIncoming($payload, $this->channel);

    expect($message->type)->toBe(MessageType::Image);
    expect($message->content)->toBe('Mira esto');
    expect($message->metadata['media_url'])->toBe('https://mmg.whatsapp.net/v/t62/img');
    expect($message->metadata['media_mimetype'])->toBe('image/jpeg');
    expect($message->metadata['media_size'])->toBe(50000);
    expect($message->metadata['media_kind'])->toBe('image');
});

test('webhook de audio ptt (voice note) se guarda como Audio', function (): void {
    $payload = [
        'event' => 'message.received',
        'data' => [
            'id' => 'false_59133333333@c.us_3EB0PTT',
            'from' => '59133333333@c.us',
            'to' => '59169387555@c.us',
            'type' => 'ptt',
            'mimetype' => 'audio/ogg; codecs=opus',
            'ptt' => true,
            'duration' => 15,
        ],
    ];

    $message = (new OpenWaChannel)->processIncoming($payload, $this->channel);

    expect($message->type)->toBe(MessageType::Audio);
    expect($message->content)->toBe('[Audio]');
    expect($message->metadata['media_ptt'])->toBeTrue();
});

test('webhook ignora eventos que no son message.received/message.sent', function (): void {
    $payload = [
        'event' => 'message.ack',
        'data' => ['id' => 'true_591@c.us_3EB0', 'ack' => 3],
    ];

    $result = (new OpenWaChannel)->processIncoming($payload, $this->channel);
    expect($result)->toBeNull();
});

test('webhook con quotedMsg guarda la referencia en metadata', function (): void {
    $payload = [
        'event' => 'message.received',
        'data' => [
            'id' => 'false_59144444444@c.us_3EB0Q',
            'from' => '59144444444@c.us',
            'to' => '59169387555@c.us',
            'body' => 'Sí, estoy de acuerdo',
            'type' => 'chat',
            'quotedMsg' => [
                'id' => 'true_59169387555@c.us_3EB0QPARENT',
                'body' => '¿Estás de acuerdo?',
                'type' => 'chat',
                'from' => '59169387555@c.us',
            ],
        ],
    ];

    $message = (new OpenWaChannel)->processIncoming($payload, $this->channel);

    expect($message->metadata['quotedMsg']['id'])->toBe('true_59169387555@c.us_3EB0QPARENT');
    expect($message->metadata['quotedMsg']['body'])->toBe('¿Estás de acuerdo?');
});

test('webhook entrante desde grupo usa el @g.us como external_id de conversación', function (): void {
    $payload = [
        'event' => 'message.received',
        'data' => [
            'id' => 'false_120363012345678@g.us_3EB0GRP',
            'from' => '120363012345678@g.us',
            'to' => '59169387555@c.us',
            'body' => 'Hola grupo',
            'type' => 'chat',
            'isGroup' => true,
        ],
    ];

    $message = (new OpenWaChannel)->processIncoming($payload, $this->channel);
    expect($message)->toBeInstanceOf(Message::class);

    $conv = Conversation::where('external_id', '120363012345678@g.us')->first();
    expect($conv)->not->toBeNull();
});

test('configFields solo expone session_name (credenciales desde .env)', function (): void {
    $channel = new OpenWaChannel;
    $fields = $channel->configFields();
    $keys = array_column($fields, 'key');
    expect($keys)->toContain('session_name');
    expect($keys)->not->toContain('base_url');
    expect($keys)->not->toContain('api_key');
    expect($keys)->not->toContain('webhook_secret');
});

test('type() devuelve ChannelType::OpenWa', function (): void {
    expect((new OpenWaChannel)->type())->toBe(ChannelType::OpenWa);
});

test('name() y description() retornan textos legibles', function (): void {
    $channel = new OpenWaChannel;
    expect($channel->name())->toBe('WhatsApp (OpenWA)');
    expect($channel->description())->toContain('OpenWA');
});

test('processIncoming no crea user si la conversación ya tiene user_id', function (): void {
    $existingUser = User::factory()->create();
    $conv = Conversation::factory()->create([
        'channel_id' => $this->channel->id,
        'external_id' => '59155555555@c.us',
        'user_id' => $existingUser->id,
    ]);

    $payload = [
        'event' => 'message.received',
        'data' => [
            'id' => 'false_59155555555@c.us_3EB0PRE',
            'from' => '59155555555@c.us',
            'to' => '59169387555@c.us',
            'body' => 'Mensaje a user existente',
            'type' => 'chat',
        ],
    ];

    (new OpenWaChannel)->processIncoming($payload, $this->channel);

    expect($conv->fresh()->user_id)->toBe($existingUser->id);
});
