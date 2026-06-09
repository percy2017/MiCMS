<?php

use App\Models\User;
use Modules\ChatBot\Models\Conversation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

test('guest can start a login session and send a message', function () {
    $existing = User::factory()->create([
        'email' => 'visitor@x.com',
        'password' => bcrypt('password1234'),
    ]);

    $response = postJson('/api/chatbot/session', [
        'email' => 'visitor@x.com',
        'password' => 'password1234',
        'action' => 'login',
    ]);

    $response->assertOk();
    expect($response->json('user.email'))->toBe('visitor@x.com');
    expect($response->json('conversation.id'))->toBeInt();

    assertDatabaseHas('conversations', [
        'user_id' => $existing->id,
        'status' => 'open',
    ]);
});

test('guest can register and start chatting', function () {
    $response = postJson('/api/chatbot/session', [
        'email' => 'new@x.com',
        'password' => 'password1234',
        'name' => 'Nuevo',
        'action' => 'register',
    ]);

    $response->assertOk();
    expect($response->json('is_new'))->toBeTrue();

    $user = User::where('email', 'new@x.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('user'))->toBeTrue();
});

test('login with wrong password returns 422', function () {
    User::factory()->create(['email' => 'visitor@x.com', 'password' => bcrypt('password1234')]);

    postJson('/api/chatbot/session', [
        'email' => 'visitor@x.com',
        'password' => 'wrongpassword',
        'action' => 'login',
    ])->assertStatus(422);
});

test('registering with existing email returns 422', function () {
    User::factory()->create(['email' => 'exists@x.com']);

    postJson('/api/chatbot/session', [
        'email' => 'exists@x.com',
        'password' => 'password1234',
        'name' => 'Test',
        'action' => 'register',
    ])->assertStatus(422);
});

test('authenticated user can send a message', function () {
    $user = User::factory()->create();
    $conv = Conversation::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->postJson("/api/chatbot/conversations/{$conv->id}/messages", [
            'content' => 'Hola, ¿a qué hora abren?',
        ])
        ->assertOk();

    assertDatabaseHas('messages', [
        'conversation_id' => $conv->id,
        'role' => 'user',
        'content' => 'Hola, ¿a qué hora abren?',
    ]);

    expect($conv->fresh()->unread_by_admin)->toBe(1);
});

test('user cannot send to another users conversation', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $conv = Conversation::factory()->create(['user_id' => $owner->id]);

    actingAs($other)
        ->postJson("/api/chatbot/conversations/{$conv->id}/messages", [
            'content' => 'Hola',
        ])
        ->assertForbidden();
});

test('cannot send to closed conversation', function () {
    $user = User::factory()->create();
    $conv = Conversation::factory()->create([
        'user_id' => $user->id,
        'status' => 'closed',
    ]);

    actingAs($user)
        ->postJson("/api/chatbot/conversations/{$conv->id}/messages", [
            'content' => 'Hola',
        ])
        ->assertStatus(409);
});
