<?php

use App\Models\User;
use Modules\ChatBot\Enums\ConversationStatus;
use Modules\ChatBot\Enums\MessageType;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->user->givePermissionTo('view chats');

    $this->channel = Channel::factory()->evolution()->create(['enabled' => true]);

    $this->conv = Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59171146267@s.whatsapp.net',
        'visitor_name' => 'Tester',
        'user_id' => $this->user->id,
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);

    for ($i = 1; $i <= 25; $i++) {
        Message::create([
            'conversation_id' => $this->conv->id,
            'role' => 'user',
            'type' => MessageType::Text,
            'content' => "Message #{$i}",
            'created_at' => now()->subMinutes(25 - $i),
            'updated_at' => now()->subMinutes(25 - $i),
        ]);
    }
});

test('show endpoint devuelve los últimos 10 mensajes por defecto', function (): void {
    $response = $this->getJson("/admin/chats/{$this->conv->id}/messages");

    $response->assertOk();
    $body = $response->json();

    expect($body['messages'])->toHaveCount(10);
    expect($body['has_more'])->toBeTrue();
    expect($body['oldest_loaded_id'])->toBe(16);
    expect($body['newest_loaded_id'])->toBe(25);
});

test('show endpoint devuelve mensajes en orden ascendente por id', function (): void {
    $response = $this->getJson("/admin/chats/{$this->conv->id}/messages");

    $ids = collect($response->json('messages'))->pluck('id')->all();
    expect($ids)->toBe(range(16, 25));
});

test('show endpoint con before_id devuelve los 10 anteriores', function (): void {
    $response = $this->getJson("/admin/chats/{$this->conv->id}/messages?before_id=16");

    $response->assertOk();
    $body = $response->json();

    expect($body['messages'])->toHaveCount(10);
    expect($body['has_more'])->toBeTrue();
    expect($body['oldest_loaded_id'])->toBe(6);
    $ids = collect($body['messages'])->pluck('id')->all();
    expect($ids)->toBe(range(6, 15));
});

test('show endpoint en la última página devuelve has_more=false', function (): void {
    $response = $this->getJson("/admin/chats/{$this->conv->id}/messages?before_id=6");

    $response->assertOk();
    $body = $response->json();

    expect($body['messages'])->toHaveCount(5);
    expect($body['has_more'])->toBeFalse();
    $ids = collect($body['messages'])->pluck('id')->all();
    expect($ids)->toBe(range(1, 5));
});

test('show endpoint respeta per_page custom', function (): void {
    $response = $this->getJson("/admin/chats/{$this->conv->id}/messages?per_page=5");

    $response->assertOk();
    $body = $response->json();

    expect($body['messages'])->toHaveCount(5);
    expect($body['has_more'])->toBeTrue();
    expect($body['oldest_loaded_id'])->toBe(21);
    expect($body['newest_loaded_id'])->toBe(25);
});

test('index endpoint carga solo los últimos 10 mensajes del active', function (): void {
    $response = $this->get("/admin/chats?active={$this->conv->id}");

    $response->assertOk();
    $active = $response->viewData('page')['props']['active'];

    expect($active['messages'])->toHaveCount(10);
    expect($active['messages_count'])->toBe(25);
    expect($active['has_more_messages'])->toBeTrue();
    expect($active['oldest_loaded_id'])->toBe(16);
});

test('index endpoint sin paginación posible: has_more_messages=false', function (): void {
    $mini = Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59199999999@s.whatsapp.net',
        'visitor_name' => 'Mini',
        'user_id' => null,
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);
    for ($i = 1; $i <= 3; $i++) {
        Message::create([
            'conversation_id' => $mini->id,
            'role' => 'user',
            'type' => MessageType::Text,
            'content' => "Msg {$i}",
        ]);
    }

    $response = $this->get("/admin/chats?active={$mini->id}");

    $response->assertOk();
    $active = $response->viewData('page')['props']['active'];

    expect($active['messages'])->toHaveCount(3);
    expect($active['has_more_messages'])->toBeFalse();
});

test('show endpoint requiere permiso view chats', function (): void {
    $noPerm = User::factory()->create();

    $response = $this->actingAs($noPerm)->getJson("/admin/chats/{$this->conv->id}/messages");

    $response->assertForbidden();
});
