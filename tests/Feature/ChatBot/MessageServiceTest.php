<?php

use App\Models\User;
use Modules\ChatBot\Events\ChatBotMessageReceived;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;
use Modules\ChatBot\Services\ChatBotMessageService;

test('sending a user message dispatches the broadcast event', function () {
    Event::fake([ChatBotMessageReceived::class]);

    $user = User::factory()->create();
    $conv = Conversation::factory()->create(['user_id' => $user->id]);

    app(ChatBotMessageService::class)->sendUserMessage($conv, 'Hola');

    Event::assertDispatched(ChatBotMessageReceived::class, function ($event) use ($conv) {
        return $event->message->conversation_id === $conv->id
            && $event->message->role === 'user'
            && $event->message->content === 'Hola';
    });
});

test('sending an admin message dispatches the broadcast event', function () {
    Event::fake([ChatBotMessageReceived::class]);

    $conv = Conversation::factory()->create();

    app(ChatBotMessageService::class)->sendAdminMessage($conv, 'Bienvenido');

    Event::assertDispatched(ChatBotMessageReceived::class, function ($event) {
        return $event->message->role === 'admin'
            && $event->message->content === 'Bienvenido';
    });
});

test('marking as read resets unread counter and marks user messages as read', function () {
    $user = User::factory()->create();
    $conv = Conversation::factory()->create([
        'user_id' => $user->id,
        'unread_by_admin' => 2,
    ]);

    $msg1 = Message::create([
        'conversation_id' => $conv->id,
        'role' => Message::ROLE_USER,
        'content' => 'uno',
    ]);
    $msg2 = Message::create([
        'conversation_id' => $conv->id,
        'role' => Message::ROLE_USER,
        'content' => 'dos',
    ]);

    app(ChatBotMessageService::class)->markAsRead($conv, $user->id);

    expect($msg1->fresh()->read_at)->not->toBeNull();
    expect($msg2->fresh()->read_at)->not->toBeNull();
    expect($conv->fresh()->unread_by_admin)->toBe(0);
});
