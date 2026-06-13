<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Modules\ChatBot\Events\ChatBotMessageReceived;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Event::fake([ChatBotMessageReceived::class]);
    Channel::factory()->webWidget()->create();
});

test('admin can view the widget list page', function () {
    actingAs(adminUser())
        ->get(route('chatbot.admin.widget'))
        ->assertOk();
});

test('admin can view the new widget form', function () {
    actingAs(adminUser())
        ->get(route('chatbot.admin.widget.create'))
        ->assertOk();
});

test('admin can edit a widget', function () {
    $channel = Channel::where('type', 'web_widget')->first();

    actingAs(adminUser())
        ->get(route('chatbot.admin.widget.edit', $channel))
        ->assertOk();
});

test('admin can create a new widget with a domain', function () {
    // the beforeEach factory already created a widget for mitienda.com
    actingAs(adminUser())
        ->post(route('chatbot.admin.widget.store'), [
            'name' => 'Tienda Principal',
            'enabled' => true,
            'allowed_domain' => 'otra-tienda.com',
        ])
        ->assertRedirect();

    $widget = Channel::where('type', 'web_widget')
        ->where('name', 'Tienda Principal')
        ->first();

    expect($widget)->not->toBeNull();
    expect($widget->public_key)->toHaveLength(16);
    expect($widget->webhook_token)->toHaveLength(32);
    expect($widget->allowed_domain)->toBe('otra-tienda.com');
    expect($widget->webhookUrl())->toContain("/api/webhooks/widget/{$widget->id}/");
    expect($widget->webhookUrl())->toContain($widget->webhook_token);
});

test('admin cannot create a widget without a domain', function () {
    actingAs(adminUser())
        ->post(route('chatbot.admin.widget.store'), [
            'name' => 'Sin Dominio',
            'enabled' => true,
        ])
        ->assertSessionHasErrors(['allowed_domain']);
});

test('admin cannot create two widgets for the same domain', function () {
    actingAs(adminUser())
        ->post(route('chatbot.admin.widget.store'), [
            'name' => 'Duplicado',
            'enabled' => true,
            'allowed_domain' => 'mitienda.com',
        ])
        ->assertSessionHasErrors(['allowed_domain']);
});

test('admin can update widget config including domain', function () {
    $channel = Channel::where('type', 'web_widget')->first();

    actingAs(adminUser())
        ->patch(route('chatbot.admin.widget.update', $channel), [
            'enabled' => true,
            'name' => 'Mi Widget',
            'title' => 'Soporte',
            'subtitle' => 'Estamos aquí',
            'greeting' => 'Hola!',
            'position' => 'left',
            'avatar_media_id' => null,
            'require_auth' => false,
            'show_typing' => false,
            'offline_message' => null,
            'allowed_domain' => 'nuevo.com',
        ])
        ->assertRedirect();

    $fresh = $channel->fresh();
    expect($fresh->name)->toBe('Mi Widget');
    expect($fresh->settings['title'])->toBe('Soporte');
    expect($fresh->allowed_domain)->toBe('nuevo.com');
});

test('domain is normalized on save', function () {
    $channel = Channel::where('type', 'web_widget')->first();
    $channel->update(['allowed_domain' => 'old.com']);

    actingAs(adminUser())
        ->patch(route('chatbot.admin.widget.update', $channel), [
            'enabled' => true,
            'name' => 'X',
            'title' => 'X',
            'position' => 'right',
            'require_auth' => false,
            'show_typing' => true,
            'allowed_domain' => '  https://WWW.Nuevo.com/some/path  ',
        ])
        ->assertRedirect();

    expect($channel->fresh()->allowed_domain)->toBe('www.nuevo.com');
});

test('admin can delete a widget', function () {
    $channel = Channel::where('type', 'web_widget')->first();

    actingAs(adminUser())
        ->delete(route('chatbot.admin.widget.destroy', $channel))
        ->assertRedirect();

    expect(Channel::find($channel->id))->toBeNull();
});

test('a user without permission is forbidden', function () {
    actingAs(basicUser())
        ->get(route('chatbot.admin.widget'))
        ->assertForbidden();
});

test('public widget endpoint requires key', function () {
    get('/api/chatbot/widget')->assertStatus(400);
});

test('public widget endpoint returns config for valid key', function () {
    $channel = Channel::where('type', 'web_widget')->first();
    $channel->update(['public_key' => 'abc12345def67890']);

    $response = get('/api/chatbot/widget?key=abc12345def67890')->assertOk();

    expect($response->json('key'))->toBe('abc12345def67890');
    expect($response->json('enabled'))->toBeTrue();
});

test('public widget endpoint rejects invalid key', function () {
    get('/api/chatbot/widget?key=nonexistent')->assertStatus(404);
});

test('public widget endpoint blocks unauthorized origin', function () {
    $channel = Channel::where('type', 'web_widget')->first();
    $channel->update(['public_key' => 'abc12345def67890', 'allowed_domain' => 'mitienda.com']);

    get('/api/chatbot/widget?key=abc12345def67890', [
        'Origin' => 'https://evil.com',
    ])->assertStatus(403);
});

test('public widget endpoint allows matching origin', function () {
    $channel = Channel::where('type', 'web_widget')->first();
    $channel->update(['public_key' => 'abc12345def67890', 'allowed_domain' => 'mitienda.com']);

    get('/api/chatbot/widget?key=abc12345def67890', [
        'Origin' => 'https://mitienda.com',
    ])->assertOk();
});

test('public widget endpoint allows wildcard subdomain', function () {
    $channel = Channel::where('type', 'web_widget')->first();
    $channel->update(['public_key' => 'abc12345def67890', 'allowed_domain' => '*.mitienda.com']);

    get('/api/chatbot/widget?key=abc12345def67890', [
        'Origin' => 'https://shop.mitienda.com',
    ])->assertOk();
});

test('public widget endpoint rejects when no domain configured', function () {
    $channel = Channel::where('type', 'web_widget')->first();
    $channel->update(['public_key' => 'abc12345def67890', 'allowed_domain' => null]);

    get('/api/chatbot/widget?key=abc12345def67890', [
        'Origin' => 'https://anywhere.com',
    ])->assertStatus(403);
});

test('public widget endpoint returns disabled when channel is disabled', function () {
    $channel = Channel::where('type', 'web_widget')->first();
    $channel->update(['public_key' => 'abc12345def67890', 'enabled' => false]);

    $response = get('/api/chatbot/widget?key=abc12345def67890')->assertOk();

    expect($response->json('enabled'))->toBeFalse();
    expect($response->json('reason'))->toBe('disabled');
});

test('each new widget gets a unique public_key and webhook_token', function () {
    $a = Channel::create([
        'type' => 'web_widget',
        'name' => 'Widget A',
        'enabled' => true,
        'config' => [],
        'settings' => [],
        'allowed_domain' => 'a.com',
    ]);
    $b = Channel::create([
        'type' => 'web_widget',
        'name' => 'Widget B',
        'enabled' => true,
        'config' => [],
        'settings' => [],
        'allowed_domain' => 'b.com',
    ]);

    expect($a->public_key)->toHaveLength(16);
    expect($b->public_key)->toHaveLength(16);
    expect($a->public_key)->not->toBe($b->public_key);
    expect($a->webhook_token)->toHaveLength(32);
    expect($a->webhook_token)->not->toBe($b->webhook_token);
});

test('widget webhook creates user, conversation and message from visitor data', function () {
    $channel = Channel::where('type', 'web_widget')->first();
    $channel->update([
        'public_key' => 'abc12345def67890',
        'allowed_domain' => 'mitienda.com',
        'webhook_token' => 'tok1234567890abcdef1234567890ab',
    ]);

    $url = "/api/webhooks/widget/{$channel->id}/{$channel->webhook_token}";

    $response = postJson($url, [
        'visitor' => [
            'name' => 'Visitante X',
            'email' => 'visitante@example.com',
            'phone' => '+59172811368',
        ],
        'message' => [
            'content' => 'Hola, necesito ayuda',
        ],
    ], [
        'Origin' => 'https://mitienda.com',
    ])->assertOk();

    expect($response->json('ok'))->toBeTrue();
    expect($response->json('message_id'))->toBeInt();
    expect($response->json('conversation_id'))->toBeInt();

    $this->assertDatabaseHas('users', [
        'email' => 'visitante@example.com',
        'name' => 'Visitante X',
    ]);

    $this->assertDatabaseHas('conversations', [
        'channel_id' => $channel->id,
        'visitor_email' => 'visitante@example.com',
    ]);

    $this->assertDatabaseHas('messages', [
        'role' => 'user',
        'content' => 'Hola, necesito ayuda',
    ]);
});

test('widget webhook reuses existing user and conversation', function () {
    $channel = Channel::where('type', 'web_widget')->first();
    $existingUser = User::factory()->create(['email' => 'existente@example.com']);
    $existingConv = Conversation::factory()->create([
        'channel_id' => $channel->id,
        'user_id' => $existingUser->id,
    ]);

    $url = "/api/webhooks/widget/{$channel->id}/{$channel->webhook_token}";

    postJson($url, [
        'visitor' => [
            'name' => 'X',
            'email' => 'existente@example.com',
        ],
        'message' => [
            'content' => 'Otra consulta',
        ],
    ], [
        'Origin' => 'https://mitienda.com',
    ])->assertOk();

    expect(Conversation::where('channel_id', $channel->id)
        ->where('user_id', $existingUser->id)
        ->count()
    )->toBe(1);
});

test('widget webhook rejects invalid token', function () {
    $channel = Channel::where('type', 'web_widget')->first();

    postJson("/api/webhooks/widget/{$channel->id}/WRONG-TOKEN", [
        'visitor' => ['name' => 'X', 'email' => 'x@x.com'],
        'message' => ['content' => 'hola'],
    ], [
        'Origin' => 'https://mitienda.com',
    ])->assertStatus(401);
});

test('widget webhook blocks unauthorized origin', function () {
    $channel = Channel::where('type', 'web_widget')->first();

    postJson("/api/webhooks/widget/{$channel->id}/{$channel->webhook_token}", [
        'visitor' => ['name' => 'X', 'email' => 'x@x.com'],
        'message' => ['content' => 'hola'],
    ], [
        'Origin' => 'https://evil.com',
    ])->assertStatus(403);
});

test('widget webhook requires visitor data', function () {
    $channel = Channel::where('type', 'web_widget')->first();

    postJson("/api/webhooks/widget/{$channel->id}/{$channel->webhook_token}", [
        'message' => ['content' => 'hola'],
    ], [
        'Origin' => 'https://mitienda.com',
    ])->assertStatus(422);
});
