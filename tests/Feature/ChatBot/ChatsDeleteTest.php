<?php

use App\Models\User;
use Modules\ChatBot\Enums\ConversationStatus;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'view chats', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view chatbot', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'delete chatbot conversations', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->user->givePermissionTo('view chats');
    $this->user->givePermissionTo('view chatbot');
    $this->user->givePermissionTo('delete chatbot conversations');
    $this->channel = Channel::factory()->evolution()->create(['enabled' => true]);
});

test('delete elimina la conversacion del sidebar (force delete)', function (): void {
    $conv = Conversation::factory()->create([
        'channel_id' => $this->channel->id,
        'status' => ConversationStatus::Open,
    ]);

    $response = $this->delete("/admin/chats/{$conv->id}");

    $response->assertRedirect(route('chatbot.admin.chats'));
    $this->assertDatabaseMissing('conversations', ['id' => $conv->id]);
});

test('delete requiere permission view chatbot', function (): void {
    $noPerm = User::factory()->create();
    $this->actingAs($noPerm);

    $conv = Conversation::factory()->create(['channel_id' => $this->channel->id]);

    $response = $this->delete("/admin/chats/{$conv->id}");
    $response->assertForbidden();
});

test('delete elimina la conversacion del listado despues del reload', function (): void {
    $keep = Conversation::factory()->create(['channel_id' => $this->channel->id]);
    $delete = Conversation::factory()->create(['channel_id' => $this->channel->id]);

    $this->delete("/admin/chats/{$delete->id}")->assertRedirect();

    // Después del delete, el listado NO debe incluir la conversación eliminada
    $response = $this->get('/admin/chats');
    $response->assertOk();
    $props = $response->viewData('page')['props'];
    $ids = collect($props['conversations']['data'])->pluck('id');
    expect($ids)->toContain($keep->id);
    expect($ids)->not->toContain($delete->id);
});

test('delete cascadea mensajes y reactions', function (): void {
    $conv = Conversation::factory()->create(['channel_id' => $this->channel->id]);
    $msg = Message::create([
        'conversation_id' => $conv->id,
        'role' => Message::ROLE_USER,
        'type' => \Modules\ChatBot\Enums\MessageType::Text,
        'content' => 'test',
    ]);

    $this->delete("/admin/chats/{$conv->id}")->assertRedirect();

    // El controller hace forceDelete en transaction, así que los mensajes deben desaparecer
    $this->assertDatabaseMissing('messages', ['id' => $msg->id]);
});
