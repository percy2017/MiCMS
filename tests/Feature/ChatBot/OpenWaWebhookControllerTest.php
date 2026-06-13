<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

beforeEach(function (): void {
    Cache::flush();
    Config::set('chatbot.openwa.webhook_secret', 'super-secret-32chars-1234abcd');
    Config::set('chatbot.openwa.base_url', 'https://openwa.example.com/api');
    Config::set('chatbot.openwa.api_key', 'test-key');
    $this->channel = Channel::factory()->openwa()->create([
        'enabled' => true,
        'type' => ChannelType::OpenWa,
        'config' => ['session_name' => 'test-session'],
    ]);
});

/**
 * Helper: firma HMAC + headers de OpenWA para enviar via call().
 */
function openwaSignedJson(array $payload, ?string $idempotencyKey = null): array
{
    $body = json_encode($payload);
    $secret = (string) config('chatbot.openwa.webhook_secret');
    $signature = $secret ? 'sha256='.hash_hmac('sha256', $body, $secret) : null;

    $headers = [];
    if ($signature) {
        $headers['X-OpenWA-Signature'] = $signature;
    }
    if ($idempotencyKey) {
        $headers['X-OpenWA-Idempotency-Key'] = $idempotencyKey;
        $headers['X-OpenWA-Delivery-Id'] = 'dlv_'.uniqid();
        $headers['X-OpenWA-Retry-Count'] = '0';
    }

    return [
        'body' => $body,
        'headers' => $headers,
    ];
}

test('webhook con firma HMAC válida es aceptado (200)', function (): void {
    $req = openwaSignedJson(
        [
            'event' => 'message.received',
            'data' => [
                'id' => 'false_59166666666@c.us_3EB0WC',
                'from' => '59166666666@c.us',
                'body' => 'Test webhook',
                'type' => 'chat',
            ],
        ],
        'msg_wc_1'
    );

    $this->call('POST', route('webhooks.openwa', ['channel' => $this->channel->id]), [], [], [], serverHeaders($req['headers']), $req['body'])
        ->assertOk();

    expect(Message::where('external_id', '3EB0WC')->exists())->toBeTrue();
});

test('webhook con firma HMAC inválida es rechazado (401)', function (): void {
    $body = json_encode(['event' => 'message.received']);

    $this->call(
        'POST',
        route('webhooks.openwa', ['channel' => $this->channel->id]),
        [], [], [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-OPENWA-SIGNATURE' => 'sha256=invalid',
            'HTTP_X-OPENWA-IDEMPOTENCY-KEY' => 'msg_bad_sig',
        ],
        $body
    )->assertStatus(401);
});

test('webhook sin header de firma es rechazado (401)', function (): void {
    $this->call(
        'POST',
        route('webhooks.openwa', ['channel' => $this->channel->id]),
        [], [], [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-OPENWA-IDEMPOTENCY-KEY' => 'msg_no_sig',
        ],
        json_encode(['event' => 'message.received'])
    )->assertStatus(401);
});

test('webhook sin secret configurado NO verifica firma (skip)', function (): void {
    Config::set('chatbot.openwa.webhook_secret', '');

    $req = openwaSignedJson(
        [
            'event' => 'message.received',
            'data' => [
                'id' => 'false_59177777777@c.us_3EB0NS',
                'from' => '59177777777@c.us',
                'body' => 'Test sin secret',
                'type' => 'chat',
            ],
        ],
        'msg_nosecret'
    );

    $this->call('POST', route('webhooks.openwa', ['channel' => $this->channel->id]), [], [], [], serverHeaders($req['headers']), $req['body'])
        ->assertOk();

    expect(Message::where('external_id', '3EB0NS')->exists())->toBeTrue();
});

test('webhook con mismo idempotencyKey es ignorado (duplicate_ignored)', function (): void {
    $req = openwaSignedJson(
        [
            'event' => 'message.received',
            'data' => [
                'id' => 'false_59188888888@c.us_3EB0DUPWC',
                'from' => '59188888888@c.us',
                'body' => 'Dedupe test',
                'type' => 'chat',
            ],
        ],
        'msg_dup_wc'
    );

    $this->call('POST', route('webhooks.openwa', ['channel' => $this->channel->id]), [], [], [], serverHeaders($req['headers']), $req['body'])
        ->assertOk();

    $this->call('POST', route('webhooks.openwa', ['channel' => $this->channel->id]), [], [], [], serverHeaders($req['headers']), $req['body'])
        ->assertOk()
        ->assertJson(['ok' => true, 'status' => 'duplicate_ignored']);

    expect(Message::where('external_id', '3EB0DUPWC')->count())->toBe(1);
});

test('webhook a canal deshabilitado es rechazado (404)', function (): void {
    $this->channel->update(['enabled' => false]);
    $this->channel->refresh();

    $req = openwaSignedJson(['event' => 'message.received'], 'msg_disabled');

    $this->call('POST', route('webhooks.openwa', ['channel' => $this->channel->id]), [], [], [], serverHeaders($req['headers']), $req['body'])
        ->assertStatus(404);
});

test('webhook revocado elimina el message local', function (): void {
    $conv = Conversation::factory()->create([
        'channel_id' => $this->channel->id,
        'external_id' => '59199999999@c.us',
    ]);
    $message = Message::create([
        'conversation_id' => $conv->id,
        'role' => 'user',
        'type' => 'text',
        'content' => 'Será eliminado',
        'external_id' => '3EB0TODELETE',
    ]);

    $req = openwaSignedJson(
        [
            'event' => 'message.revoked',
            'data' => [
                'messageId' => '3EB0TODELETE',
                'from' => '59199999999@c.us',
            ],
        ],
        'rev_1'
    );

    $this->call('POST', route('webhooks.openwa', ['channel' => $this->channel->id]), [], [], [], serverHeaders($req['headers']), $req['body'])
        ->assertOk();

    expect(Message::withTrashed()->where('id', $message->id)->count())->toBe(0);
});

test('webhook de session.status se loguea pero no crea message', function (): void {
    $req = openwaSignedJson(
        [
            'event' => 'session.status',
            'sessionId' => 'sess_test',
            'data' => ['status' => 'CONNECTED', 'phoneNumber' => '59169387555'],
        ],
        'sess_1'
    );

    $this->call('POST', route('webhooks.openwa', ['channel' => $this->channel->id]), [], [], [], serverHeaders($req['headers']), $req['body'])
        ->assertOk()
        ->assertJson(['ok' => true, 'status' => 'session_event_logged']);

    expect(Message::count())->toBe(0);
});

/**
 * Convierte ["X-Foo" => "bar"] a ["HTTP_X_FOO" => "bar"] para $this->call().
 */
function serverHeaders(array $headers): array
{
    $result = ['CONTENT_TYPE' => 'application/json'];
    foreach ($headers as $name => $value) {
        $key = 'HTTP_'.str_replace('-', '_', strtoupper($name));
        $result[$key] = $value;
    }

    return $result;
}
