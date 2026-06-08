<?php

use App\Models\ChatWidgetMessage;
use App\Models\Package;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can view messages page', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create(['slug' => 'chat-widget', 'name' => 'Chat']);
    ChatWidgetMessage::factory()->count(3)->incoming()->create(['session_id' => 's1']);

    $this->actingAs($user)
        ->get(route('admin.paquetes.messages.index', ['package' => $package->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/paquetes/messages')
            ->has('sessions', 1)
            ->where('sessions.0.session_id', 's1'),
        );
});

test('admin can view a specific conversation', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create(['slug' => 'chat-widget']);
    ChatWidgetMessage::factory()->create(['session_id' => 's1', 'message' => 'Hola']);
    ChatWidgetMessage::factory()->create(['session_id' => 's1', 'message' => 'Respuesta', 'direction' => ChatWidgetMessage::DIRECTION_OUTGOING]);
    ChatWidgetMessage::factory()->create(['session_id' => 's2', 'message' => 'Otro']);

    $this->actingAs($user)
        ->get(route('admin.paquetes.messages.show', ['package' => $package->id, 'sessionId' => 's1']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeSessionId', 's1')
            ->has('messages', 2)
            ->where('messages.0.message', 'Hola')
            ->where('messages.1.direction', 'outgoing'),
        );
});

test('admin can post a reply', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create(['slug' => 'chat-widget']);

    $this->actingAs($user)
        ->from(route('admin.paquetes.messages.index', ['package' => $package->id]))
        ->post(route('admin.paquetes.messages.store', ['package' => $package->id]), [
            'session_id' => 's1',
            'message' => 'Hola, te respondo',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('chat_widget_messages', [
        'session_id' => 's1',
        'message' => 'Hola, te respondo',
        'direction' => 'outgoing',
    ]);
});

test('messages routes only work for chat-widget package', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create(['slug' => 'crm']);

    $this->actingAs($user)
        ->get(route('admin.paquetes.messages.index', ['package' => $package->id]))
        ->assertNotFound();
});
