<?php

use Modules\ChatBot\Channels\EvolutionChannel;
use Modules\ChatBot\Enums\ConversationStatus;
use Modules\ChatBot\Enums\MessageType;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;
use Modules\ChatBot\Models\MessageReaction;

beforeEach(function (): void {
    $this->channel = Channel::factory()->evolution()->create([
        'enabled' => true,
    ]);

    $this->conversation = Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59171146267@s.whatsapp.net',
        'visitor_name' => 'Percy',
        'visitor_email' => '59171146267@whatsapp',
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);

    $this->message = Message::create([
        'conversation_id' => $this->conversation->id,
        'role' => Message::ROLE_USER,
        'type' => MessageType::Text,
        'content' => 'Hola',
        'external_id' => 'MSG_ID_001',
    ]);
});

test('processReaction crea MessageReaction al recibir un emoji', function (): void {
    $payload = [
        'event' => 'messages.reaction',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG_ID_001',
            ],
            'reaction' => [
                'text' => '👍',
                'key' => [
                    'remoteJid' => '59171146267@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => 'REACTION_ID_001',
                ],
                'senderTimestamp' => 1234567890,
            ],
        ],
    ];

    $driver = new EvolutionChannel;
    $result = $driver->processReaction($payload, $this->channel);

    expect($result['action'])->toBe('added');
    expect($result['message']->id)->toBe($this->message->id);
    expect($result['reaction'])->toBeInstanceOf(MessageReaction::class);

    $this->assertDatabaseHas('message_reactions', [
        'message_id' => $this->message->id,
        'user_jid' => '59171146267@s.whatsapp.net',
        'emoji' => '👍',
        'external_id' => 'REACTION_ID_001',
    ]);
});

test('processReaction marca fromMe como admin-self', function (): void {
    $payload = [
        'event' => 'messages.reaction',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => true,
                'id' => 'MSG_ID_001',
            ],
            'reaction' => [
                'text' => '❤️',
                'key' => [
                    'remoteJid' => '59171146267@s.whatsapp.net',
                    'fromMe' => true,
                    'id' => 'REACTION_ID_ADMIN_001',
                ],
            ],
        ],
    ];

    $driver = new EvolutionChannel;
    $result = $driver->processReaction($payload, $this->channel);

    expect($result['action'])->toBe('added');

    $this->assertDatabaseHas('message_reactions', [
        'message_id' => $this->message->id,
        'user_jid' => 'admin-self',
        'emoji' => '❤️',
    ]);
});

test('processReaction devuelve exists si la reacción ya existe', function (): void {
    MessageReaction::create([
        'message_id' => $this->message->id,
        'user_jid' => '59171146267@s.whatsapp.net',
        'emoji' => '👍',
        'external_id' => 'REACTION_ID_001',
    ]);

    $payload = [
        'event' => 'messages.reaction',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG_ID_001',
            ],
            'reaction' => [
                'text' => '👍',
                'key' => [
                    'remoteJid' => '59171146267@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => 'REACTION_ID_002',
                ],
            ],
        ],
    ];

    $driver = new EvolutionChannel;
    $result = $driver->processReaction($payload, $this->channel);

    expect($result['action'])->toBe('exists');
    expect(MessageReaction::where('message_id', $this->message->id)->count())->toBe(1);
});

test('processReaction elimina la reacción cuando el texto está vacío', function (): void {
    MessageReaction::create([
        'message_id' => $this->message->id,
        'user_jid' => '59171146267@s.whatsapp.net',
        'emoji' => '👍',
        'external_id' => 'REACTION_ID_001',
    ]);

    $payload = [
        'event' => 'messages.reaction',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG_ID_001',
            ],
            'reaction' => [
                'text' => '',
                'key' => [
                    'remoteJid' => '59171146267@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => 'REACTION_ID_001',
                ],
            ],
        ],
    ];

    $driver = new EvolutionChannel;
    $result = $driver->processReaction($payload, $this->channel);

    expect($result['action'])->toBe('removed');
    expect(MessageReaction::where('message_id', $this->message->id)->count())->toBe(0);
});

test('processReaction ignora si el mensaje original no existe', function (): void {
    $payload = [
        'event' => 'messages.reaction',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'NONEXISTENT_MSG_ID',
            ],
            'reaction' => [
                'text' => '👍',
                'key' => [
                    'remoteJid' => '59171146267@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => 'REACTION_ID_003',
                ],
            ],
        ],
    ];

    $driver = new EvolutionChannel;
    $result = $driver->processReaction($payload, $this->channel);

    expect($result['action'])->toBe('skipped');
    expect(MessageReaction::count())->toBe(0);
});

test('processIncoming con evento messages.reaction no crea Message', function (): void {
    $payload = [
        'event' => 'messages.reaction',
        'data' => [
            'key' => [
                'remoteJid' => '59171146267@s.whatsapp.net',
                'fromMe' => false,
                'id' => 'MSG_ID_001',
            ],
            'reaction' => [
                'text' => '👍',
                'key' => [
                    'remoteJid' => '59171146267@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => 'REACTION_ID_004',
                ],
            ],
        ],
    ];

    $driver = new EvolutionChannel;
    $message = $driver->processIncoming($payload, $this->channel);

    expect($message)->toBeNull();
    expect(MessageReaction::where('message_id', $this->message->id)->count())->toBe(1);
});

test('processReaction ignora si falta messageId o remoteJid', function (): void {
    $payloads = [
        [
            'event' => 'messages.reaction',
            'data' => [
                'key' => ['fromMe' => false],
                'reaction' => ['text' => '👍', 'key' => ['id' => 'X']],
            ],
        ],
        [
            'event' => 'messages.reaction',
            'data' => [
                'key' => ['id' => 'X', 'fromMe' => false],
                'reaction' => ['text' => '👍', 'key' => ['id' => 'X']],
            ],
        ],
    ];

    $driver = new EvolutionChannel;
    foreach ($payloads as $p) {
        $result = $driver->processReaction($p, $this->channel);
        expect($result['action'])->toBe('skipped');
    }

    expect(MessageReaction::count())->toBe(0);
});
