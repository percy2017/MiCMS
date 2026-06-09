<?php

use Modules\ChatBot\Models\ChatBotWidget;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('admin can view the widget config page', function () {
    actingAs(adminUser())
        ->get(route('chatbot.admin.widget'))
        ->assertOk();
});

test('admin can update the widget config', function () {
    $widget = ChatBotWidget::current();

    actingAs(adminUser())
        ->patch(route('chatbot.admin.widget.update'), [
            'enabled' => true,
            'title' => 'Soporte',
            'subtitle' => 'Estamos aquí',
            'greeting' => 'Hola!',
            'position' => 'left',
            'avatar_media_id' => null,
            'require_auth' => false,
            'show_typing' => false,
            'offline_message' => null,
        ])
        ->assertRedirect();

    $widget->refresh();
    expect($widget->title)->toBe('Soporte');
    expect($widget->position)->toBe('left');
});

test('a user without permission is forbidden', function () {
    actingAs(basicUser())
        ->get(route('chatbot.admin.widget'))
        ->assertForbidden();
});

test('public widget endpoint returns config', function () {
    ChatBotWidget::current()->update(['title' => 'Ayuda']);

    $response = get('/api/chatbot/widget')->assertOk();

    expect($response->json('title'))->toBe('Ayuda');
    expect($response->json('enabled'))->toBeTrue();
});
