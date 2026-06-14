<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Models\Channel;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Config::set('chatbot.openwa.base_url', 'https://openwa.example.com/api');
    Config::set('chatbot.openwa.api_key', 'test-key');
});

test('available devuelve configured=false si no hay credenciales en .env', function (): void {
    Config::set('chatbot.openwa.base_url', '');
    Config::set('chatbot.openwa.api_key', '');

    actingAs(adminUser())
        ->get('/admin/canales/openwa/available')
        ->assertOk()
        ->assertJson(['configured' => false, 'items' => []]);
});

test('available lista sesiones de OpenWA y marca las ya vinculadas', function (): void {
    Http::fake([
        'openwa.example.com/*' => Http::response([
            ['id' => 's1', 'name' => 'tigo1', 'status' => 'CONNECTED', 'phone' => '59111111'],
            ['id' => 's2', 'name' => 'entel2', 'status' => 'created', 'phone' => null],
        ], 200),
    ]);

    Channel::factory()->openwa()->create([
        'name' => 'tigo1',
        'config' => ['session_name' => 'tigo1'],
        'enabled' => true,
    ]);

    actingAs(adminUser())
        ->get('/admin/canales/openwa/available')
        ->assertOk()
        ->assertJson([
            'configured' => true,
            'items' => [
                ['external_key' => 'tigo1', 'taken' => true],
                ['external_key' => 'entel2', 'taken' => false],
            ],
        ]);
});

test('available maneja errores de HTTP gracefully', function (): void {
    Http::fake([
        'openwa.example.com/*' => Http::response('Server Error', 500),
    ]);

    actingAs(adminUser())
        ->get('/admin/canales/openwa/available')
        ->assertOk()
        ->assertJson(['configured' => true, 'items' => []])
        ->assertJsonStructure(['error']);
});

test('create page renderiza con lista + form', function (): void {
    Http::fake([
        'openwa.example.com/*' => Http::response([
            ['id' => 's1', 'name' => 'tigo1', 'status' => 'CONNECTED'],
        ], 200),
    ]);

    actingAs(adminUser())
        ->get('/admin/canales/openwa')
        ->assertOk();
});

test('store crea un canal con session_name y redirige al listado', function (): void {
    actingAs(adminUser())
        ->post('/admin/canales/openwa', [
            'enabled' => true,
            'config' => ['session_name' => 'tigo1'],
            'settings' => ['display_name' => 'Tigo 1'],
        ])
        ->assertRedirect(route('chatbot.admin.canales'))
        ->assertSessionHas('success');

    $channel = Channel::where('type', ChannelType::OpenWa)
        ->get()
        ->first(fn ($c) => is_array($c->config) && ($c->config['session_name'] ?? null) === 'tigo1');

    expect($channel)->not->toBeNull();
    expect($channel->enabled)->toBeTrue();
    expect($channel->name)->toBe('tigo1');
    expect($channel->config)->toBe(['session_name' => 'tigo1']);
});

test('store rechaza session_name duplicada', function (): void {
    Channel::factory()->openwa()->create([
        'name' => 'tigo1',
        'config' => ['session_name' => 'tigo1'],
        'enabled' => true,
    ]);

    actingAs(adminUser())
        ->post('/admin/canales/openwa', [
            'enabled' => true,
            'config' => ['session_name' => 'tigo1'],
            'settings' => ['display_name' => 'Tigo 1'],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('config.session_name');
});

test('store requiere session_name', function (): void {
    actingAs(adminUser())
        ->post('/admin/canales/openwa', [])
        ->assertSessionHasErrors('config.session_name');
});

test('store permite vincular la misma session_name si el canal previo fue deshabilitado', function (): void {
    Channel::factory()->openwa()->create([
        'name' => 'tigo1',
        'config' => ['session_name' => 'tigo1'],
        'enabled' => false,
    ]);

    actingAs(adminUser())
        ->post('/admin/canales/openwa', [
            'enabled' => true,
            'config' => ['session_name' => 'tigo1'],
            'settings' => ['display_name' => 'Tigo 1'],
        ])
        ->assertRedirect(route('chatbot.admin.canales'))
        ->assertSessionHas('success');

    $matches = Channel::where('type', ChannelType::OpenWa)
        ->get()
        ->filter(fn ($c) => is_array($c->config) && ($c->config['session_name'] ?? null) === 'tigo1');
    expect($matches->count())->toBe(2);
});
