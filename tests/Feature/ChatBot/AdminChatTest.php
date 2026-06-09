<?php

use Modules\ChatBot\Models\Conversation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

test('admin can view conversations list', function () {
    Conversation::factory()->count(3)->create();

    actingAs(adminUser())
        ->get(route('chatbot.admin.chats'))
        ->assertOk();
});

test('admin can view a single conversation', function () {
    $conv = Conversation::factory()->create();

    actingAs(adminUser())
        ->get(route('chatbot.admin.chats', ['active' => $conv->id]))
        ->assertOk();
});

test('admin can reply to a conversation', function () {
    $conv = Conversation::factory()->create();

    actingAs(adminUser())
        ->post(route('chatbot.admin.chats.reply', $conv), [
            'content' => 'Hola, ¿en qué te ayudo?',
        ])
        ->assertRedirect();

    assertDatabaseHas('messages', [
        'conversation_id' => $conv->id,
        'role' => 'admin',
        'content' => 'Hola, ¿en qué te ayudo?',
    ]);
});

test('admin can close a conversation', function () {
    $conv = Conversation::factory()->create();

    actingAs(adminUser())
        ->post(route('chatbot.admin.chats.close', $conv))
        ->assertRedirect();

    expect($conv->fresh()->status->value)->toBe('closed');
});

test('admin can delete a conversation', function () {
    $conv = Conversation::factory()->create();

    actingAs(adminUser())
        ->delete(route('chatbot.admin.chats.destroy', $conv))
        ->assertRedirect(route('chatbot.admin.chats'));

    assertDatabaseCount('conversations', 1);
});

test('editor can view but not delete', function () {
    $conv = Conversation::factory()->create();

    actingAs(editorUser())
        ->get(route('chatbot.admin.chats', ['active' => $conv->id]))
        ->assertOk();

    actingAs(editorUser())
        ->delete(route('chatbot.admin.chats.destroy', $conv))
        ->assertForbidden();
});

test('user without view chats permission is forbidden', function () {
    actingAs(basicUser())
        ->get(route('chatbot.admin.chats'))
        ->assertForbidden();
});
