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

test('openwaAvailableSessions devuelve configured=false si no hay credenciales en .env', function (): void {
    Config::set('chatbot.openwa.base_url', '');
    Config::set('chatbot.openwa.api_key', '');

    actingAs(adminUser())
        ->get('/admin/canales/openwa/available')
        ->assertOk()
        ->assertJson(['configured' => false, 'sessions' => []]);
});

test('openwaAvailableSessions lista sesiones de OpenWA y marca las ya vinculadas', function (): void {
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
            'sessions' => [
                ['name' => 'tigo1', 'already_linked' => true],
                ['name' => 'entel2', 'already_linked' => false],
            ],
        ]);
});

test('openwaAvailableSessions maneja errores de HTTP gracefully', function (): void {
    Http::fake([
        'openwa.example.com/*' => Http::response('Server Error', 500),
    ]);

    actingAs(adminUser())
        ->get('/admin/canales/openwa/available')
        ->assertOk()
        ->assertJson(['configured' => true, 'sessions' => []])
        ->assertJsonStructure(['error']);
});

test('storeOpenWa crea un canal con session_name y redirige a edit', function (): void {
    $response = actingAs(adminUser())
        ->post('/admin/canales/openwa', [
            'session_name' => 'tigo1',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $channel = Channel::where('type', ChannelType::OpenWa)
        ->get()
        ->first(fn ($c) => is_array($c->config) && ($c->config['session_name'] ?? null) === 'tigo1');

    expect($channel)->not->toBeNull();
    expect($channel->enabled)->toBeTrue();
    expect($channel->name)->toBe('tigo1');
    expect($channel->config)->toBe(['session_name' => 'tigo1']);
});

test('storeOpenWa rechaza session_name duplicada', function (): void {
    Channel::factory()->openwa()->create([
        'name' => 'tigo1',
        'config' => ['session_name' => 'tigo1'],
        'enabled' => true,
    ]);

    $response = actingAs(adminUser())
        ->post('/admin/canales/openwa', [
            'session_name' => 'tigo1',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('session_name');
});

test('storeOpenWa requiere session_name', function (): void {
    actingAs(adminUser())
        ->post('/admin/canales/openwa', [])
        ->assertSessionHasErrors('session_name');
});

test('storeOpenWa permite vincular la misma session_name si el canal previo fue deshabilitado', function (): void {
    Channel::factory()->openwa()->create([
        'name' => 'tigo1',
        'config' => ['session_name' => 'tigo1'],
        'enabled' => false,
    ]);

    $response = actingAs(adminUser())
        ->post('/admin/canales/openwa', [
            'session_name' => 'tigo1',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $matches = Channel::where('type', ChannelType::OpenWa)
        ->get()
        ->filter(fn ($c) => is_array($c->config) && ($c->config['session_name'] ?? null) === 'tigo1');
    expect($matches->count())->toBe(2);
});

test('editOpenWa renderiza la página de edición con los datos del canal', function (): void {
    $channel = Channel::factory()->openwa()->create([
        'name' => 'tigo1',
        'config' => ['session_name' => 'tigo1'],
    ]);

    actingAs(adminUser())
        ->get("/admin/canales/openwa/{$channel->id}")
        ->assertOk();
});

test('editOpenWa rechaza canales que no son openwa', function (): void {
    $channel = Channel::factory()->create(['type' => 'web_widget']);

    actingAs(adminUser())
        ->get("/admin/canales/openwa/{$channel->id}")
        ->assertNotFound();
});

test('updateOpenWa actualiza el session_name y settings', function (): void {
    $channel = Channel::factory()->openwa()->create([
        'name' => 'tigo1',
        'config' => ['session_name' => 'tigo1'],
    ]);

    actingAs(adminUser())
        ->patch("/admin/canales/openwa/{$channel->id}", [
            'enabled' => true,
            'config' => ['session_name' => 'entel1'],
            'settings' => ['display_name' => 'Entel 1', 'auto_reply' => 'Hola'],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $channel->refresh();
    expect($channel->enabled)->toBeTrue();
    expect($channel->config)->toBe(['session_name' => 'entel1']);
    expect($channel->settings)->toMatchArray(['display_name' => 'Entel 1', 'auto_reply' => 'Hola']);
});
