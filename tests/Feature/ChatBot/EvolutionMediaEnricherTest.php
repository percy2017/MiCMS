<?php

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Modules\ChatBot\Channels\ChannelRegistry;
use Modules\ChatBot\Channels\Evolution\EvolutionMediaEnricher;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Enums\MessageType;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

uses(RefreshDatabase::class);

function makeEvolutionChannelForMedia(): Channel
{
    return Channel::factory()->evolution()->create([
        'name' => 'Test Evolution',
        'config' => [
            'server_url' => 'https://evolution.example.com',
            'api_key' => 'test-key',
            'instance_name' => 'entel2',
        ],
    ]);
}

function makeConversationWithImageMessage(Channel $channel, string $externalId = '59112345@s.whatsapp.net', string $messageId = 'AC12345'): Message
{
    $conversation = Conversation::create([
        'channel_id' => $channel->id,
        'external_id' => $externalId,
        'visitor_name' => 'Test',
        'visitor_email' => "{$externalId}@whatsapp",
        'status' => 'open',
        'last_message_at' => now(),
        'unread_by_admin' => 0,
    ]);

    return Message::create([
        'conversation_id' => $conversation->id,
        'role' => Message::ROLE_ADMIN,
        'type' => MessageType::Image,
        'content' => '[Imagen]',
        'external_id' => $messageId,
        'metadata' => [
            'remoteJid' => $externalId,
            'media_kind' => 'image',
            'media_url' => 'https://mmg.whatsapp.net/temp/abc',
            'media_mimetype' => 'image/jpeg',
        ],
    ]);
}

it('guarda el media en tabla media y setea attachment_media_id', function (): void {
    Http::fake([
        'evolution.example.com/*' => Http::response([
            'base64' => base64_encode('fake-image-binary-content'),
            'mimetype' => 'image/jpeg',
            'fileName' => 'photo.jpg',
        ], 200),
    ]);

    $channel = makeEvolutionChannelForMedia();
    $message = makeConversationWithImageMessage($channel);

    $enricher = app(EvolutionMediaEnricher::class);
    $enricher->enrich($message->fresh(), $channel, $message->external_id);

    $message->refresh();
    expect($message->attachment_media_id)->not->toBeNull();

    $media = Media::find($message->attachment_media_id);
    expect($media)->not->toBeNull();
    expect($media->mime_type)->toBe('image/jpeg');
    expect($media->name)->toBe('photo.jpg');
    expect($media->size)->toBe(strlen('fake-image-binary-content'));
});

it('deja la media_url original si la descarga falla', function (): void {
    Http::fake([
        'evolution.example.com/*' => Http::response('Server Error', 500),
    ]);

    $channel = makeEvolutionChannelForMedia();
    $message = makeConversationWithImageMessage($channel);

    $enricher = app(EvolutionMediaEnricher::class);
    $enricher->enrich($message->fresh(), $channel, $message->external_id);

    $message->refresh();
    expect($message->attachment_media_id)->toBeNull();
    expect($message->metadata['media_url'])->toBe('https://mmg.whatsapp.net/temp/abc');
    expect($message->metadata['media_enrichment_failed_at'])->not->toBeNull();
});

it('no hace nada si la respuesta no trae base64', function (): void {
    Http::fake([
        'evolution.example.com/*' => Http::response(['error' => 'no media'], 200),
    ]);

    $channel = makeEvolutionChannelForMedia();
    $message = makeConversationWithImageMessage($channel);

    $enricher = app(EvolutionMediaEnricher::class);
    $enricher->enrich($message->fresh(), $channel, $message->external_id);

    $message->refresh();
    expect($message->attachment_media_id)->toBeNull();
    expect(Media::count())->toBe(0);
});

it('se llama al enricher en rama fromMe=true con un mensaje de imagen', function (): void {
    Http::fake([
        'evolution.example.com/*' => Http::response([
            'base64' => base64_encode('admin-image'),
            'mimetype' => 'image/png',
            'fileName' => 'sent.png',
        ], 200),
    ]);

    $channel = makeEvolutionChannelForMedia();
    $payload = [
        'event' => 'messages.upsert',
        'instance' => 'entel2',
        'data' => [
            'key' => [
                'remoteJid' => '59112345@s.whatsapp.net',
                'fromMe' => true,
                'id' => 'ADMIN_IMG_001',
            ],
            'pushName' => 'percy',
            'message' => [
                'imageMessage' => [
                    'mimetype' => 'image/png',
                    'caption' => '',
                ],
            ],
            'messageType' => 'imageMessage',
            'source' => 'android',
        ],
    ];

    $channelDriver = app(ChannelRegistry::class)
        ->get(ChannelType::Evolution);

    $message = $channelDriver->processIncoming($payload, $channel);

    expect($message)->not->toBeNull();
    expect($message->external_id)->toBe('ADMIN_IMG_001');
    expect($message->role)->toBe(Message::ROLE_ADMIN);

    Http::assertSent(function (HttpRequest $request): bool {
        return str_contains($request->url(), '/chat/getBase64FromMediaMessage/entel2');
    });

    $message->refresh();
    expect($message->attachment_media_id)->not->toBeNull();
});
