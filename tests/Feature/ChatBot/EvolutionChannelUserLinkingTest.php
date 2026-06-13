<?php

use App\Models\User;
use Modules\ChatBot\Channels\Evolution\EvolutionChannel;
use Modules\ChatBot\Enums\MessageType;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

beforeEach(function (): void {
    $this->channel = Channel::factory()->evolution()->create([
        'enabled' => true,
    ]);
});

test('webhook entrante AUTO-CREA user si no existe y vincula la conversación con rol user', function (): void {
    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'TEST_MSG_001',
            ],
            'pushName' => 'Visitante Nuevo',
            'message' => [
                'conversation' => 'Hola',
            ],
            'messageType' => 'conversation',
        ],
    ];

    $channel = new EvolutionChannel;
    $message = $channel->processIncoming($payload, $this->channel);

    expect($message)->toBeInstanceOf(Message::class);
    expect($message->content)->toBe('Hola');
    expect($message->role)->toBe('user');
    expect($message->external_id)->toBe('TEST_MSG_001');

    $conv = Conversation::where('external_id', '59171146267@s.whatsapp.net')->first();
    expect($conv)->not->toBeNull();
    expect($conv->user_id)->not->toBeNull();

    $user = User::find($conv->user_id);
    expect($user->name)->toBe('Visitante Nuevo');
    expect($user->phone)->toBe('59171146267');
    expect($user->whatsapp_jid)->toBe('59171146267@s.whatsapp.net');
    expect($user->hasRole('user'))->toBeTrue();
});

test('webhook auto-crea user con email derivado del JID y password aleatorio', function (): void {
    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '59177777777@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'TEST_MSG_EMAIL',
            ],
            'pushName' => 'Test Email',
            'message' => ['conversation' => 'hola'],
            'messageType' => 'conversation',
        ],
    ];

    $channel = new EvolutionChannel;
    $channel->processIncoming($payload, $this->channel);

    $user = User::where('whatsapp_jid', '59177777777@s.whatsapp.net')->first();
    expect($user)->not->toBeNull();
    expect($user->email)->toBe('wa-59177777777@whatsapp.local');
    expect($user->password)->not->toBeEmpty();
});

test('webhook backfilea whatsapp_jid en user existente creado solo por phone', function (): void {
    $existing = User::factory()->create([
        'phone' => '59171146267',
        'email' => 'percy@admin.local',
        'name' => 'Percy',
    ]);

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'TEST_MSG_BACKFILL',
            ],
            'pushName' => 'Otro pushName',
            'message' => ['conversation' => 'hola'],
            'messageType' => 'conversation',
        ],
    ];

    $channel = new EvolutionChannel;
    $channel->processIncoming($payload, $this->channel);

    $existing->refresh();
    expect($existing->whatsapp_jid)->toBe('59171146267@s.whatsapp.net');
    expect($existing->name)->toBe('Percy');
    expect(User::where('phone', '59171146267')->count())->toBe(1);
});

test('webhook entrante vincula conversación con user existente por phone', function (): void {
    $existingUser = User::factory()->create([
        'phone' => '59171146267',
        'email' => 'percy@admin.local',
        'name' => 'Percy Alvarez',
    ]);

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'TEST_MSG_002',
            ],
            'pushName' => 'Otro Nombre Push',
            'message' => [
                'conversation' => 'Mensaje del cliente',
            ],
            'messageType' => 'conversation',
        ],
    ];

    $channel = new EvolutionChannel;
    $message = $channel->processIncoming($payload, $this->channel);

    expect($message)->toBeInstanceOf(Message::class);
    $conv = Conversation::where('external_id', '59171146267@s.whatsapp.net')->first();
    expect($conv->user_id)->toBe($existingUser->id);
});

test('webhook entrante vincula conversación con user existente por whatsapp_jid', function (): void {
    $existingUser = User::factory()->create([
        'phone' => '59100000000',
        'whatsapp_jid' => '59171146267@s.whatsapp.net',
        'email' => 'percy2@admin.local',
        'name' => 'Percy 2',
    ]);

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'TEST_MSG_003',
            ],
            'pushName' => 'Push',
            'message' => [
                'conversation' => 'Test',
            ],
            'messageType' => 'conversation',
        ],
    ];

    $channel = new EvolutionChannel;
    $channel->processIncoming($payload, $this->channel);

    $conv = Conversation::where('external_id', '59171146267@s.whatsapp.net')->first();
    expect($conv->user_id)->toBe($existingUser->id);
});

test('webhook NUNCA sobrescribe el name de un user existente', function (): void {
    $existingUser = User::factory()->create([
        'phone' => '59171146267',
        'email' => 'admin@percy.local',
        'name' => 'Percy Real',
    ]);

    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'TEST_MSG_004',
            ],
            'pushName' => 'rioblanco',
            'message' => [
                'conversation' => 'Test',
            ],
            'messageType' => 'conversation',
        ],
    ];

    $channel = new EvolutionChannel;
    $channel->processIncoming($payload, $this->channel);

    $existingUser->refresh();
    expect($existingUser->name)->toBe('Percy Real');
    expect(User::where('phone', '59171146267')->count())->toBe(1);
});

test('webhook con messageId duplicado NO crea mensaje duplicado', function (): void {
    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'TEST_MSG_DUP',
            ],
            'pushName' => 'X',
            'message' => [
                'conversation' => 'primero',
            ],
            'messageType' => 'conversation',
        ],
    ];

    $channel = new EvolutionChannel;
    $first = $channel->processIncoming($payload, $this->channel);
    $second = $channel->processIncoming($payload, $this->channel);

    expect($first)->toBeInstanceOf(Message::class);
    expect($second)->toBeNull();
    expect(Message::where('external_id', 'TEST_MSG_DUP')->count())->toBe(1);
});

test('webhook con imagen crea mensaje de tipo image', function (): void {
    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'TEST_IMG_001',
            ],
            'pushName' => 'X',
            'message' => [
                'imageMessage' => [
                    'caption' => 'miren esto',
                ],
            ],
            'messageType' => 'image',
        ],
    ];

    $channel = new EvolutionChannel;
    $message = $channel->processIncoming($payload, $this->channel);

    expect($message->type)->toBe(MessageType::Image);
    expect($message->content)->toBe('miren esto');
});

test('webhook con caption de video', function (): void {
    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'TEST_VID_001',
            ],
            'pushName' => 'X',
            'message' => [
                'videoMessage' => [
                    'caption' => 'video test',
                ],
            ],
            'messageType' => 'video',
        ],
    ];

    $channel = new EvolutionChannel;
    $message = $channel->processIncoming($payload, $this->channel);

    expect($message->type)->toBe(MessageType::Video);
    expect($message->content)->toBe('video test');
});

test('webhook con fromMe=true se guarda como admin (echo de WhatsApp, no fue creado por UI)', function (): void {
    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => true,
                'id' => 'TEST_FROMME_001',
            ],
            'pushName' => 'Me',
            'message' => [
                'conversation' => 'yo mismo',
            ],
            'messageType' => 'conversation',
        ],
    ];

    $channel = new EvolutionChannel;
    $message = $channel->processIncoming($payload, $this->channel);

    expect($message)->not->toBeNull();
    expect($message->role)->toBe('admin');
    expect($message->external_id)->toBe('TEST_FROMME_001');
    expect($message->content)->toBe('yo mismo');
});

test('webhook con fromMe=true duplicado retorna el mismo mensaje (idempotente)', function (): void {
    $payload = [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => true,
                'id' => 'TEST_FROMME_DUP_001',
            ],
            'message' => ['conversation' => 'dup'],
            'messageType' => 'conversation',
        ],
    ];

    $channel = new EvolutionChannel;
    $first = $channel->processIncoming($payload, $this->channel);
    $second = $channel->processIncoming($payload, $this->channel);

    expect($first->id)->toBe($second->id);
    expect(Message::where('external_id', 'TEST_FROMME_DUP_001')->count())->toBe(1);
});

test('webhook con payload inválido retorna null sin throw', function (): void {
    $payloads = [
        [],
        ['event' => 'unknown.event'],
        ['event' => 'messages.upsert'],
        ['event' => 'messages.upsert', 'data' => []],
    ];

    $channel = new EvolutionChannel;
    foreach ($payloads as $p) {
        expect($channel->processIncoming($p, $this->channel))->toBeNull();
    }
});
