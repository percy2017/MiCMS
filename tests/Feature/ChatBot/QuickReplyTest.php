<?php

use Modules\ChatBot\Models\QuickReply;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    QuickReply::query()->delete();
});

test('admin can view the quick replies list', function () {
    actingAs(adminUser())
        ->get(route('chatbot.admin.quick-replies.index'))
        ->assertOk();
});

test('admin can view the new quick reply form', function () {
    actingAs(adminUser())
        ->get(route('chatbot.admin.quick-replies.create'))
        ->assertOk();
});

test('admin can create a quick reply with text', function () {
    actingAs(adminUser())
        ->post(route('chatbot.admin.quick-replies.store'), [
            'shortcut' => 'saludo',
            'title' => 'Saludo inicial',
            'content' => 'Hola, bienvenido a nuestro servicio.',
            'category' => 'saludos',
            'enabled' => true,
        ])
        ->assertRedirect();

    $reply = QuickReply::where('shortcut', 'saludo')->first();
    expect($reply)->not->toBeNull();
    expect($reply->title)->toBe('Saludo inicial');
    expect($reply->content)->toContain('Hola');
    expect($reply->category)->toBe('saludos');
});

test('admin can create a quick reply with leading slash in shortcut', function () {
    actingAs(adminUser())
        ->post(route('chatbot.admin.quick-replies.store'), [
            'shortcut' => '/precio',
            'title' => 'Lista de precios',
            'content' => 'Aquí están los precios...',
        ])
        ->assertRedirect();

    $reply = QuickReply::where('shortcut', 'precio')->first();
    expect($reply)->not->toBeNull();
});

test('shortcut must be unique', function () {
    QuickReply::create([
        'shortcut' => 'saludo',
        'title' => 'Test',
        'content' => 'test',
    ]);

    actingAs(adminUser())
        ->post(route('chatbot.admin.quick-replies.store'), [
            'shortcut' => 'saludo',
            'title' => 'Otro',
            'content' => 'otro',
        ])
        ->assertSessionHasErrors(['shortcut']);
});

test('shortcut cannot contain special chars', function () {
    actingAs(adminUser())
        ->post(route('chatbot.admin.quick-replies.store'), [
            'shortcut' => 'con espacio',
            'title' => 'Test',
            'content' => 'test',
        ])
        ->assertSessionHasErrors(['shortcut']);
});

test('quick reply must have at least content or media', function () {
    actingAs(adminUser())
        ->post(route('chatbot.admin.quick-replies.store'), [
            'shortcut' => 'vacio',
            'title' => 'Sin contenido',
            'content' => null,
            'media_id' => null,
        ])
        ->assertSessionHasErrors(['content']);
});

test('admin can edit a quick reply', function () {
    $reply = QuickReply::create([
        'shortcut' => 'saludo',
        'title' => 'Original',
        'content' => 'original',
    ]);

    actingAs(adminUser())
        ->get(route('chatbot.admin.quick-replies.edit', $reply))
        ->assertOk();
});

test('admin can update a quick reply', function () {
    $reply = QuickReply::create([
        'shortcut' => 'saludo',
        'title' => 'Original',
        'content' => 'original',
    ]);

    actingAs(adminUser())
        ->patch(route('chatbot.admin.quick-replies.update', $reply), [
            'shortcut' => 'saludo-nuevo',
            'title' => 'Actualizado',
            'content' => 'nuevo contenido',
            'enabled' => false,
        ])
        ->assertRedirect();

    $fresh = $reply->fresh();
    expect($fresh->shortcut)->toBe('saludo-nuevo');
    expect($fresh->title)->toBe('Actualizado');
    expect($fresh->enabled)->toBeFalse();
});

test('admin can delete a quick reply', function () {
    $reply = QuickReply::create([
        'shortcut' => 'saludo',
        'title' => 'X',
        'content' => 'x',
    ]);

    actingAs(adminUser())
        ->delete(route('chatbot.admin.quick-replies.destroy', $reply))
        ->assertRedirect();

    expect(QuickReply::find($reply->id))->toBeNull();
});

test('a user without permission is forbidden', function () {
    actingAs(basicUser())
        ->get(route('chatbot.admin.quick-replies.index'))
        ->assertForbidden();
});

test('api endpoint returns only enabled quick replies', function () {
    QuickReply::create(['shortcut' => 'on', 'title' => 'On', 'content' => 'on', 'enabled' => true]);
    QuickReply::create(['shortcut' => 'off', 'title' => 'Off', 'content' => 'off', 'enabled' => false]);

    $response = actingAs(adminUser())
        ->getJson('/api/chatbot/quick-replies')
        ->assertOk();

    $replies = $response->json('replies');
    expect($replies)->toHaveCount(1);
    expect($replies[0]['shortcut'])->toBe('on');
});

test('api endpoint requires authentication', function () {
    getJson('/api/chatbot/quick-replies')->assertStatus(401);
});

test('api endpoint forbids users without permission', function () {
    actingAs(basicUser())
        ->getJson('/api/chatbot/quick-replies')
        ->assertForbidden();
});

test('list page supports search filter', function () {
    QuickReply::create(['shortcut' => 'saludo', 'title' => 'Saludo', 'content' => 'hola']);
    QuickReply::create(['shortcut' => 'precio', 'title' => 'Precio', 'content' => 'cuesta']);

    actingAs(adminUser())
        ->get(route('chatbot.admin.quick-replies.index', ['search' => 'hola']))
        ->assertOk();
});

test('soft deleted quick replies are not in api', function () {
    $r = QuickReply::create(['shortcut' => 'gone', 'title' => 'X', 'content' => 'x', 'enabled' => true]);
    $r->delete();

    $response = actingAs(adminUser())
        ->getJson('/api/chatbot/quick-replies')
        ->assertOk();

    expect($response->json('replies'))->toHaveCount(0);
});
