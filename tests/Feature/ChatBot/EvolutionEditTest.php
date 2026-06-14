<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\ChatBot\Http\Controllers\Admin\Evolution\EvolutionInboxController;
use Modules\ChatBot\Models\Channel;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->user->givePermissionTo('view chatbot');
    $this->channel = Channel::factory()->evolution()->create(['enabled' => true]);
});

test('edit muestra la página con channel + liveSettings + liveWebhook', function (): void {
    Http::fake([
        '*/settings/find/*' => Http::response([
            'rejectCall' => true,
            'msgCall' => 'No acepto llamadas',
            'groupsIgnore' => true,
            'alwaysOnline' => false,
            'readMessages' => false,
            'readStatus' => false,
            'syncFullHistory' => false,
            'wavoipToken' => '',
        ], 200),
        '*/webhook/find/*' => Http::response([
            'enabled' => true,
            'url' => 'https://example.com/webhook',
            'events' => ['MESSAGES_UPSERT'],
        ], 200),
    ]);

    $response = $this->get("/admin/canales/evolution/{$this->channel->id}/edit");
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    expect($props['channel']['id'])->toBe($this->channel->id);
    expect($props['liveSettings']['groupsIgnore'])->toBeTrue();
    expect($props['liveSettings']['rejectCall'])->toBeTrue();
    expect($props['liveSettings']['msgCall'])->toBe('No acepto llamadas');
    expect($props['liveWebhook']['events'])->toBe(['MESSAGES_UPSERT']);
});

test('edit requiere permission view chatbot', function (): void {
    $noPerm = User::factory()->create();
    $this->actingAs($noPerm);

    $response = $this->get("/admin/canales/evolution/{$this->channel->id}/edit");
    $response->assertForbidden();
});

test('edit retorna 404 para channel no-Evolution', function (): void {
    $widget = Channel::factory()->create(['type' => 'web_widget', 'enabled' => true]);

    $response = $this->get("/admin/canales/evolution/{$widget->id}/edit");
    $response->assertNotFound();
});

test('update persiste enabled y llama a Evolution setSettings', function (): void {
    Http::fake([
        '*/settings/set/*' => Http::response(['settings' => ['groupsIgnore' => true]], 200),
    ]);

    $response = $this->patch("/admin/canales/evolution/{$this->channel->id}", [
        'enabled' => false,
        'groups_ignore' => true,
        'reject_call' => false,
        'always_online' => false,
        'read_messages' => false,
        'read_status' => false,
        'sync_full_history' => false,
        'msg_call' => '',
    ]);

    $response->assertRedirect(route('chatbot.admin.canales'));
    $this->assertDatabaseHas('channels', [
        'id' => $this->channel->id,
        'enabled' => false,
    ]);
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/settings/set/') &&
            $request['groupsIgnore'] === true;
    });
});

test('update persiste msg_call y todos los settings', function (): void {
    Http::fake([
        '*/settings/set/*' => Http::response(['settings' => []], 200),
    ]);

    $this->patch("/admin/canales/evolution/{$this->channel->id}", [
        'enabled' => true,
        'groups_ignore' => false,
        'reject_call' => true,
        'always_online' => true,
        'read_messages' => true,
        'read_status' => true,
        'sync_full_history' => true,
        'msg_call' => 'Solo WhatsApp texto',
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/settings/set/') &&
            $request['rejectCall'] === true &&
            $request['alwaysOnline'] === true &&
            $request['readMessages'] === true &&
            $request['readStatus'] === true &&
            $request['syncFullHistory'] === true &&
            $request['msgCall'] === 'Solo WhatsApp texto';
    });
});

test('update retorna error si Evolution rechaza los cambios', function (): void {
    Http::fake([
        '*/settings/set/*' => Http::response('Bad Request', 400),
    ]);

    $response = $this->patch("/admin/canales/evolution/{$this->channel->id}", [
        'enabled' => true,
        'groups_ignore' => true,
    ]);

    $response->assertRedirect(route('chatbot.admin.canales'));
    $response->assertSessionHas('error');
});
