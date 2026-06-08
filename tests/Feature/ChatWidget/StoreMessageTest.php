<?php

use App\Models\Package;

test('store accepts a valid message', function () {
    Package::factory()->create(['slug' => 'chat-widget', 'enabled' => true, 'installed' => true]);

    $response = $this->postJson(route('chat-widget.store'), [
        'session_id' => 'test-session',
        'name' => 'Juan',
        'email' => 'juan@example.com',
        'message' => 'Hola, ¿tienen precios?',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['session_id', 'message' => ['id', 'message', 'direction']]);
    $this->assertDatabaseCount('chat_widget_messages', 1);
    $this->assertDatabaseHas('chat_widget_messages', ['message' => 'Hola, ¿tienen precios?']);
});

test('store generates a session_id if not provided', function () {
    Package::factory()->create(['slug' => 'chat-widget', 'enabled' => true, 'installed' => true]);

    $response = $this->postJson(route('chat-widget.store'), [
        'message' => 'Sin session',
    ]);

    $response->assertOk();
    $payload = $response->json();
    expect($payload['session_id'])->not->toBeEmpty();
});

test('store rejects empty message', function () {
    Package::factory()->create(['slug' => 'chat-widget', 'enabled' => true, 'installed' => true]);

    $response = $this->postJson(route('chat-widget.store'), [
        'message' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['message']);
});

test('store rejects messages when package is disabled', function () {
    Package::factory()->create(['slug' => 'chat-widget', 'enabled' => false, 'installed' => true]);

    $this->postJson(route('chat-widget.store'), [
        'message' => 'Hola',
    ])->assertStatus(403);
});
