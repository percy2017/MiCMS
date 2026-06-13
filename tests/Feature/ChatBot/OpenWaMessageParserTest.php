<?php

use Modules\ChatBot\Channels\OpenWa\OpenWaMessageParser;
use Modules\ChatBot\Enums\MessageType;

test('extractChatId devuelve el from del payload', function (): void {
    $data = ['from' => '59169387181@c.us', 'body' => 'Hola'];
    expect(OpenWaMessageParser::extractChatId($data))->toBe('59169387181@c.us');
});

test('extractChatId usa chatId como fallback', function (): void {
    $data = ['chatId' => '120363012345678@g.us'];
    expect(OpenWaMessageParser::extractChatId($data))->toBe('120363012345678@g.us');
});

test('extractChatId devuelve null si no hay identificador', function (): void {
    expect(OpenWaMessageParser::extractChatId([]))->toBeNull();
});

test('extractPhone quita el sufijo @c.us', function (): void {
    expect(OpenWaMessageParser::extractPhone('59169387181@c.us'))->toBe('59169387181');
    expect(OpenWaMessageParser::extractPhone('120363012345678@g.us'))->toBe('120363012345678');
    expect(OpenWaMessageParser::extractPhone(null))->toBeNull();
    expect(OpenWaMessageParser::extractPhone(''))->toBeNull();
});

test('extractWaMessageId separa el id de WhatsApp del fullId', function (): void {
    expect(OpenWaMessageParser::extractWaMessageId('false_59169387181@c.us_3EB0ABC123'))->toBe('3EB0ABC123');
    expect(OpenWaMessageParser::extractWaMessageId('true_59169387181@c.us_3EB0XYZ'))->toBe('3EB0XYZ');
    expect(OpenWaMessageParser::extractWaMessageId('3EB0ABC123'))->toBe('3EB0ABC123');
    expect(OpenWaMessageParser::extractWaMessageId(null))->toBeNull();
});

test('isFromMe detecta outgoing por el prefijo true_', function (): void {
    expect(OpenWaMessageParser::isFromMe('true_591@c.us_ABC'))->toBeTrue();
    expect(OpenWaMessageParser::isFromMe('false_591@c.us_ABC'))->toBeFalse();
    expect(OpenWaMessageParser::isFromMe('591@c.us'))->toBeFalse();
    expect(OpenWaMessageParser::isFromMe(null))->toBeFalse();
});

test('extractPushName usa pushName del contacto o name como fallback', function (): void {
    expect(OpenWaMessageParser::extractPushName(['contact' => ['pushName' => 'Juan']]))->toBe('Juan');
    expect(OpenWaMessageParser::extractPushName(['contact' => ['name' => 'Pedro']]))->toBe('Pedro');
    expect(OpenWaMessageParser::extractPushName(['contact' => []]))->toBeNull();
    expect(OpenWaMessageParser::extractPushName([]))->toBeNull();
});

test('extractContent devuelve body si existe, sino placeholder por tipo', function (): void {
    expect(OpenWaMessageParser::extractContent(['body' => 'Hola', 'type' => 'chat']))->toBe('Hola');
    expect(OpenWaMessageParser::extractContent(['type' => 'image']))->toBe('[Imagen]');
    expect(OpenWaMessageParser::extractContent(['type' => 'ptt']))->toBe('[Audio]');
    expect(OpenWaMessageParser::extractContent(['type' => 'audio']))->toBe('[Audio]');
    expect(OpenWaMessageParser::extractContent(['type' => 'video']))->toBe('[Video]');
    expect(OpenWaMessageParser::extractContent(['type' => 'document']))->toBe('[Documento]');
    expect(OpenWaMessageParser::extractContent(['type' => 'sticker']))->toBe('[Sticker]');
    expect(OpenWaMessageParser::extractContent(['type' => 'vcard']))->toBe('[Contacto]');
    expect(OpenWaMessageParser::extractContent(['type' => 'location']))->toBe('[Ubicación]');
    expect(OpenWaMessageParser::extractContent(['type' => 'revoked']))->toBe('[Mensaje eliminado]');
    expect(OpenWaMessageParser::extractContent(['type' => 'unknown']))->toBe('[Mensaje no soportado]');
});

test('detectType mapea tipos de OpenWA a MessageType del chatbot', function (): void {
    expect(OpenWaMessageParser::detectType(['type' => 'chat']))->toBe(MessageType::Text);
    expect(OpenWaMessageParser::detectType(['type' => 'image']))->toBe(MessageType::Image);
    expect(OpenWaMessageParser::detectType(['type' => 'video']))->toBe(MessageType::Video);
    expect(OpenWaMessageParser::detectType(['type' => 'ptt']))->toBe(MessageType::Audio);
    expect(OpenWaMessageParser::detectType(['type' => 'audio']))->toBe(MessageType::Audio);
    expect(OpenWaMessageParser::detectType(['type' => 'document']))->toBe(MessageType::File);
    expect(OpenWaMessageParser::detectType(['type' => 'sticker']))->toBe(MessageType::Sticker);
    expect(OpenWaMessageParser::detectType(['type' => 'location']))->toBe(MessageType::Location);
    expect(OpenWaMessageParser::detectType(['type' => 'vcard']))->toBe(MessageType::Contact);
    expect(OpenWaMessageParser::detectType([]))->toBe(MessageType::Text);
});

test('extractMediaMeta extrae mime, url, size, dimensiones y duración', function (): void {
    $data = [
        'type' => 'image',
        'mimetype' => 'image/jpeg',
        'mediaUrl' => 'https://mmg.whatsapp.net/v/t62/abc',
        'fileLength' => 123456,
        'width' => 1024,
        'height' => 768,
    ];

    $meta = OpenWaMessageParser::extractMediaMeta($data);

    expect($meta['media_mimetype'])->toBe('image/jpeg');
    expect($meta['media_url'])->toBe('https://mmg.whatsapp.net/v/t62/abc');
    expect($meta['media_size'])->toBe(123456);
    expect($meta['media_width'])->toBe(1024);
    expect($meta['media_height'])->toBe(768);
    expect($meta['media_kind'])->toBe('image');
});

test('extractMediaMeta maneja ptt y gifPlayback para video/audio', function (): void {
    $meta = OpenWaMessageParser::extractMediaMeta([
        'type' => 'ptt',
        'mimetype' => 'audio/ogg; codecs=opus',
        'ptt' => true,
        'duration' => 12,
    ]);
    expect($meta['media_ptt'])->toBeTrue();
    expect($meta['media_duration'])->toBe(12);

    $meta2 = OpenWaMessageParser::extractMediaMeta([
        'type' => 'video',
        'gifPlayback' => true,
    ]);
    expect($meta2['media_gif_playback'])->toBeTrue();
});

test('extractQuotedMsg devuelve null si no hay quote', function (): void {
    expect(OpenWaMessageParser::extractQuotedMsg([]))->toBeNull();
    expect(OpenWaMessageParser::extractQuotedMsg(['quotedMsg' => []]))->toBeNull();
});

test('extractQuotedMsg extrae id, body, type y from del quote', function (): void {
    $data = [
        'quotedMsg' => [
            'id' => 'true_591@c.us_3EB0ABC',
            'body' => 'Mensaje original',
            'type' => 'chat',
            'from' => '59169387181@c.us',
        ],
    ];
    $quote = OpenWaMessageParser::extractQuotedMsg($data);
    expect($quote)->toBe([
        'id' => 'true_591@c.us_3EB0ABC',
        'body' => 'Mensaje original',
        'type' => 'chat',
        'from' => '59169387181@c.us',
    ]);
});

test('isMessageEvent solo procesa message.received y message.sent', function (): void {
    expect(OpenWaMessageParser::isMessageEvent(['event' => 'message.received']))->toBeTrue();
    expect(OpenWaMessageParser::isMessageEvent(['event' => 'message.sent']))->toBeTrue();
    expect(OpenWaMessageParser::isMessageEvent(['event' => 'message.ack']))->toBeFalse();
    expect(OpenWaMessageParser::isMessageEvent(['event' => 'session.status']))->toBeFalse();
    expect(OpenWaMessageParser::isMessageEvent([]))->toBeFalse();
});
