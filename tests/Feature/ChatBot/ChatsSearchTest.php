<?php

use App\Models\User;
use Modules\ChatBot\Enums\ConversationStatus;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->user->givePermissionTo('view chats');

    $this->channel = Channel::factory()->evolution()->create(['enabled' => true]);
});

test('search endpoint filtra por nombre de usuario', function (): void {
    $percy = User::factory()->create(['name' => 'Percy Alvarez', 'phone' => '59171146267']);
    User::factory()->create(['name' => 'Other User', 'phone' => '59199999999']);

    Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59171146267@s.whatsapp.net',
        'visitor_name' => 'Percy',
        'user_id' => $percy->id,
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);
    Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59199999999@s.whatsapp.net',
        'visitor_name' => 'Other',
        'user_id' => null,
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);

    $response = $this->getJson('/admin/chats/search?search=Percy');

    $response->assertOk();
    $names = collect($response->json('conversations'))->pluck('name');
    expect($names)->toContain('Percy Alvarez');
    expect($names)->not->toContain('Other User');
});

test('search endpoint filtra por teléfono', function (): void {
    $percy = User::factory()->create(['name' => 'Percy', 'phone' => '59171146267']);
    User::factory()->create(['name' => 'Other', 'phone' => '59199999999']);

    Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59171146267@s.whatsapp.net',
        'user_id' => $percy->id,
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);
    Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59199999999@s.whatsapp.net',
        'user_id' => null,
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);

    $response = $this->getJson('/admin/chats/search?search=59171146267');

    $response->assertOk();
    $names = collect($response->json('conversations'))->pluck('name');
    expect($names)->toContain('Percy');
    expect($names)->not->toContain('Other');
});

test('search endpoint filtra por nombre de visitante sin user', function (): void {
    Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59171111111@s.whatsapp.net',
        'visitor_name' => 'Visitante Especial',
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);
    Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59172222222@s.whatsapp.net',
        'visitor_name' => 'Otro',
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);

    $response = $this->getJson('/admin/chats/search?search=Especial');

    $response->assertOk();
    $names = collect($response->json('conversations'))->pluck('name');
    expect($names)->toContain('Visitante Especial');
    expect($names)->not->toContain('Otro');
});

test('search endpoint filtra por external_id (jid)', function (): void {
    Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59179988777@s.whatsapp.net',
        'visitor_name' => 'Anonymous',
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);
    Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59176655444@s.whatsapp.net',
        'visitor_name' => 'Other',
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);

    $response = $this->getJson('/admin/chats/search?search=59179988777');

    $response->assertOk();
    $names = collect($response->json('conversations'))->pluck('name');
    expect($names)->toContain('Anonymous');
    expect($names)->not->toContain('Other');
});

test('search endpoint sin search devuelve todas las conversaciones', function (): void {
    for ($i = 0; $i < 3; $i++) {
        Conversation::create([
            'channel_id' => $this->channel->id,
            'external_id' => "5917{$i}@s.whatsapp.net",
            'visitor_name' => "User $i",
            'status' => ConversationStatus::Open,
            'last_message_at' => now(),
        ]);
    }

    $response = $this->getJson('/admin/chats/search');

    $response->assertOk();
    expect($response->json('conversations'))->toHaveCount(3);
});

test('search endpoint filtra por status', function (): void {
    Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59171111111@s.whatsapp.net',
        'visitor_name' => 'Open One',
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);
    Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59172222222@s.whatsapp.net',
        'visitor_name' => 'Closed One',
        'status' => ConversationStatus::Closed,
        'last_message_at' => now(),
    ]);

    $response = $this->getJson('/admin/chats/search?status=open');

    $response->assertOk();
    $names = collect($response->json('conversations'))->pluck('name');
    expect($names)->toContain('Open One');
    expect($names)->not->toContain('Closed One');
});

test('search endpoint filtra por channel_id', function (): void {
    $channel2 = Channel::factory()->webWidget()->create(['enabled' => true]);

    Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59171111111@s.whatsapp.net',
        'visitor_name' => 'Channel 1',
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);
    Conversation::create([
        'channel_id' => $channel2->id,
        'external_id' => 'widget-001',
        'visitor_name' => 'Channel 2',
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);

    $response = $this->getJson('/admin/chats/search?channel_id='.$this->channel->id);

    $response->assertOk();
    $names = collect($response->json('conversations'))->pluck('name');
    expect($names)->toContain('Channel 1');
    expect($names)->not->toContain('Channel 2');
});

test('search endpoint requiere permiso view chats', function (): void {
    $userWithoutPerms = User::factory()->create();
    $this->actingAs($userWithoutPerms);

    $response = $this->getJson('/admin/chats/search?search=Percy');
    $response->assertForbidden();
});
