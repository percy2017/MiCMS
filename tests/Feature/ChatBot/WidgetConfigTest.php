<?php

use Modules\ChatBot\Models\Channel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Channel::factory()->webWidget()->create();
});

test('admin can view the widget config page', function () {
    actingAs(adminUser())
        ->get(route('chatbot.admin.widget'))
        ->assertOk();
});

test('admin can update the widget config', function () {
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

    $channel = Channel::where('type', 'web_widget')->first();
    expect($channel->settings['title'])->toBe('Soporte');
    expect($channel->settings['position'])->toBe('left');
});

test('a user without permission is forbidden', function () {
    actingAs(basicUser())
        ->get(route('chatbot.admin.widget'))
        ->assertForbidden();
});

test('public widget endpoint returns config', function () {
    $channel = Channel::where('type', 'web_widget')->first();
    $settings = $channel->settings;
    $settings['title'] = 'Ayuda';
    $channel->update(['settings' => $settings]);

    $response = get('/api/chatbot/widget')->assertOk();

    expect($response->json('title'))->toBe('Ayuda');
    expect($response->json('enabled'))->toBeTrue();
});
