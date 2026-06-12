<?php

use Modules\ChatBot\Channels\EvolutionChannel;
use Modules\ChatBot\Enums\MessageType;
use Modules\ChatBot\Models\Channel;

beforeEach(function (): void {
    $this->channel = Channel::factory()->evolution()->create(['enabled' => true]);
});

function convPayload(array $messageData, string $id = 'MSG_001', string $remoteJid = '59171146267@s.whatsapp.net', bool $fromMe = false): array
{
    return [
        'event' => 'messages.upsert',
        'data' => [
            'key' => [
                'remoteJid' => $remoteJid,
                'fromMe' => $fromMe,
                'id' => $id,
            ],
            'pushName' => 'Tester',
            'message' => $messageData,
            'messageType' => array_key_first($messageData) ?: 'unknown',
        ],
    ];
}

test('processIncoming desenvuelve ephemeralMessage y extrae text', function (): void {
    $payload = convPayload([
        'ephemeralMessage' => [
            'message' => [
                'conversation' => 'Hola efímero',
            ],
        ],
    ], 'EPHEMERAL_001');

    $message = (new EvolutionChannel)->processIncoming($payload, $this->channel);

    expect($message->content)->toBe('Hola efímero');
    expect($message->type)->toBe(MessageType::Text);
});

test('processIncoming desenvuelve viewOnceMessage y extrae imagen', function (): void {
    $payload = convPayload([
        'viewOnceMessage' => [
            'message' => [
                'imageMessage' => [
                    'mimetype' => 'image/jpeg',
                    'url' => 'https://example.com/x.jpg',
                ],
            ],
        ],
    ], 'VIEWONCE_001');

    $message = (new EvolutionChannel)->processIncoming($payload, $this->channel);

    expect($message->content)->toBe('[Imagen]');
    expect($message->type)->toBe(MessageType::Image);
    expect($message->metadata['media_url'])->toBe('https://example.com/x.jpg');
    expect($message->metadata['media_mimetype'])->toBe('image/jpeg');
});

test('processIncoming reconoce ptvMessage como video', function (): void {
    $payload = convPayload([
        'ptvMessage' => [
            'mimetype' => 'video/mp4',
            'url' => 'https://example.com/ptv.mp4',
            'caption' => 'Video corto',
        ],
    ], 'PTV_001');

    $message = (new EvolutionChannel)->processIncoming($payload, $this->channel);

    expect($message->type)->toBe(MessageType::Video);
    expect($message->content)->toBe('Video corto');
});

test('processIncoming reconoce documentWithCaptionMessage como file', function (): void {
    $payload = convPayload([
        'documentWithCaptionMessage' => [
            'message' => [
                'documentMessage' => [
                    'mimetype' => 'application/pdf',
                    'fileName' => 'factura.pdf',
                    'caption' => 'Factura adjunta',
                ],
            ],
        ],
    ], 'DOC_001');

    $message = (new EvolutionChannel)->processIncoming($payload, $this->channel);

    expect($message->type)->toBe(MessageType::File);
    expect($message->content)->toBe('Factura adjunta');
});

test('processIncoming reconoce lottieStickerMessage como sticker', function (): void {
    $payload = convPayload([
        'lottieStickerMessage' => [
            'mimetype' => 'application/x-lottie',
        ],
    ], 'LOTTIE_001');

    $message = (new EvolutionChannel)->processIncoming($payload, $this->channel);

    expect($message->type)->toBe(MessageType::Sticker);
    expect($message->content)->toBe('[Sticker]');
});

test('processIncoming reconoce locationMessage', function (): void {
    $payload = convPayload([
        'locationMessage' => [
            'degreesLatitude' => -16.5,
            'degreesLongitude' => -68.15,
        ],
    ], 'LOC_001');

    $message = (new EvolutionChannel)->processIncoming($payload, $this->channel);

    expect($message->type)->toBe(MessageType::Location);
    expect($message->content)->toBe('[Ubicación]');
});

test('processIncoming reconoce contactMessage', function (): void {
    $payload = convPayload([
        'contactMessage' => [
            'displayName' => 'Juan Pérez',
        ],
    ], 'CONTACT_001');

    $message = (new EvolutionChannel)->processIncoming($payload, $this->channel);

    expect($message->type)->toBe(MessageType::Contact);
    expect($message->content)->toBe('[Contacto] Juan Pérez');
});

test('processIncoming reconoce pollCreationMessage', function (): void {
    $payload = convPayload([
        'pollCreationMessage' => [
            'name' => '¿Cuál es tu color favorito?',
        ],
    ], 'POLL_001');

    $message = (new EvolutionChannel)->processIncoming($payload, $this->channel);

    expect($message->content)->toBe('[Encuesta: ¿Cuál es tu color favorito?]');
});

test('processIncoming desenvuelve viewOnceMessageV2 y extrae text', function (): void {
    $payload = convPayload([
        'viewOnceMessageV2' => [
            'message' => [
                'conversation' => 'Mensaje viewonce v2',
            ],
        ],
    ], 'VIEWONCEV2_001');

    $message = (new EvolutionChannel)->processIncoming($payload, $this->channel);

    expect($message->content)->toBe('Mensaje viewonce v2');
});

test('processIncoming reconoce reactionMessage con texto como reaccion', function (): void {
    $payload = convPayload([
        'reactionMessage' => [
            'text' => '❤️',
        ],
    ], 'REACT_001');

    $message = (new EvolutionChannel)->processIncoming($payload, $this->channel);

    expect($message->content)->toBe('[Reacción: ❤️]');
});
