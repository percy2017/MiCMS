<?php

use Modules\ChatBot\Models\ChatBotConversation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

test('admin can view conversations list', function () {
    ChatBotConversation::factory()->count(3)->create();

    actingAs(adminUser())
        ->get(route('chatbot.admin.chats'))
        ->assertOk();
});

test('admin can view a single conversation', function () {
    $conv = ChatBotConversation::factory()->create();

    actingAs(adminUser())
        ->get(route('chatbot.admin.chats', ['active' => $conv->id]))
        ->assertOk();
});

test('admin can reply to a conversation', function () {
    $conv = ChatBotConversation::factory()->create();

    actingAs(adminUser())
        ->post(route('chatbot.admin.chats.reply', $conv), [
            'content' => 'Hola, ¿en qué te ayudo?',
        ])
        ->assertRedirect();

    assertDatabaseHas('chatbot_messages', [
        'conversation_id' => $conv->id,
        'role' => 'admin',
        'content' => 'Hola, ¿en qué te ayudo?',
    ]);
});

test('admin can close a conversation', function () {
    $conv = ChatBotConversation::factory()->create();

    actingAs(adminUser())
        ->post(route('chatbot.admin.chats.close', $conv))
        ->assertRedirect();

    expect($conv->fresh()->status)->toBe('closed');
});

test('admin can delete a conversation', function () {
    $conv = ChatBotConversation::factory()->create();

    actingAs(adminUser())
        ->delete(route('chatbot.admin.chats.destroy', $conv))
        ->assertRedirect(route('chatbot.admin.chats'));

    assertDatabaseCount('chatbot_conversations', 0);
});

test('editor can view but not delete', function () {
    $conv = ChatBotConversation::factory()->create();

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
