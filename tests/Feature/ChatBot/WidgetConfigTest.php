<?php

use Modules\ChatBot\Models\Channel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
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

test('admin can create a new widget with allowed domains', function () {
    actingAs(adminUser())
        ->post(route('chatbot.admin.widget.store'), [
            'name' => 'Tienda Principal',
            'enabled' => true,
            'allowed_domains' => ['mitienda.com', 'www.mitienda.com', 'localhost:3000'],
        ])
        ->assertRedirect();

    $widget = Channel::where('type', 'web_widget')
        ->where('name', 'Tienda Principal')
        ->first();

    expect($widget)->not->toBeNull();
    expect($widget->public_key)->not->toBeNull();
    expect($widget->public_key)->toHaveLength(16);
    expect($widget->allowed_domains)->toBe(['mitienda.com', 'www.mitienda.com', 'localhost:3000']);
});

test('admin can update widget config including domains', function () {
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
            'allowed_domains' => ['example.com', '*.example.com'],
        ])
        ->assertRedirect();

    $fresh = $channel->fresh();
    expect($fresh->name)->toBe('Mi Widget');
    expect($fresh->settings['title'])->toBe('Soporte');
    expect($fresh->settings['position'])->toBe('left');
    expect($fresh->allowed_domains)->toBe(['example.com', '*.example.com']);
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
    $channel->update([
        'public_key' => 'abc12345def67890',
        'allowed_domains' => ['mitienda.com'],
    ]);

    get('/api/chatbot/widget?key=abc12345def67890', [
        'Origin' => 'https://evil.com',
    ])->assertStatus(403);
});

test('public widget endpoint allows matching origin', function () {
    $channel = Channel::where('type', 'web_widget')->first();
    $channel->update([
        'public_key' => 'abc12345def67890',
        'allowed_domains' => ['mitienda.com', 'www.mitienda.com'],
    ]);

    get('/api/chatbot/widget?key=abc12345def67890', [
        'Origin' => 'https://www.mitienda.com',
    ])->assertOk();
});

test('public widget endpoint allows wildcard subdomain', function () {
    $channel = Channel::where('type', 'web_widget')->first();
    $channel->update([
        'public_key' => 'abc12345def67890',
        'allowed_domains' => ['*.mitienda.com'],
    ]);

    get('/api/chatbot/widget?key=abc12345def67890', [
        'Origin' => 'https://shop.mitienda.com',
    ])->assertOk();
});

test('public widget endpoint returns disabled when channel is disabled', function () {
    $channel = Channel::where('type', 'web_widget')->first();
    $channel->update(['public_key' => 'abc12345def67890', 'enabled' => false]);

    $response = get('/api/chatbot/widget?key=abc12345def67890')->assertOk();

    expect($response->json('enabled'))->toBeFalse();
    expect($response->json('reason'))->toBe('disabled');
});

test('public widget endpoint allows all origins when allowed_domains is empty', function () {
    $channel = Channel::where('type', 'web_widget')->first();
    $channel->update([
        'public_key' => 'abc12345def67890',
        'allowed_domains' => [],
    ]);

    get('/api/chatbot/widget?key=abc12345def67890', [
        'Origin' => 'https://anywhere.com',
    ])->assertOk();
});

test('each new widget gets a unique public_key', function () {
    $a = Channel::create([
        'type' => 'web_widget',
        'name' => 'Widget A',
        'enabled' => true,
        'config' => [],
        'settings' => [],
        'allowed_domains' => [],
    ]);
    $b = Channel::create([
        'type' => 'web_widget',
        'name' => 'Widget B',
        'enabled' => true,
        'config' => [],
        'settings' => [],
        'allowed_domains' => [],
    ]);

    expect($a->public_key)->not->toBeNull();
    expect($b->public_key)->not->toBeNull();
    expect($a->public_key)->not->toBe($b->public_key);
});
