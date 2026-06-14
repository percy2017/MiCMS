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

test('search endpoint filtra por external_id (jid)', function (): void {
    Conversation::factory()->create([
        'channel_id' => $this->channel->id,
        'external_id' => '59171111111@s.whatsapp.net',
        'user_id' => null,
        'status' => ConversationStatus::Open,
    ]);
    Conversation::factory()->create([
        'channel_id' => $this->channel->id,
        'external_id' => '59172222222@s.whatsapp.net',
        'user_id' => null,
        'status' => ConversationStatus::Open,
    ]);

    $response = $this->getJson('/admin/chats/search?search=59171111111');

    $response->assertOk();
    $phones = collect($response->json('conversations'))->pluck('visitor_phone');
    expect($phones)->toContain('59171111111@s.whatsapp.net');
    expect($phones)->not->toContain('59172222222@s.whatsapp.net');
});

test('search endpoint filtra por status', function (): void {
    Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59173333333@s.whatsapp.net',
        'user_id' => null,
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);
    Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59174444444@s.whatsapp.net',
        'user_id' => null,
        'status' => ConversationStatus::Closed,
        'last_message_at' => now(),
    ]);

    $response = $this->getJson('/admin/chats/search?status=open');

    $response->assertOk();
    $statuses = collect($response->json('conversations'))->pluck('status')->unique()->values();
    expect($statuses)->toContain('open');
    expect($statuses)->not->toContain('closed');
});

test('search endpoint filtra por channel_id', function (): void {
    $otherChannel = Channel::factory()->evolution()->create(['enabled' => true]);

    Conversation::create([
        'channel_id' => $this->channel->id,
        'external_id' => '59175555555@s.whatsapp.net',
        'user_id' => null,
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);
    Conversation::create([
        'channel_id' => $otherChannel->id,
        'external_id' => '59176666666@s.whatsapp.net',
        'user_id' => null,
        'status' => ConversationStatus::Open,
        'last_message_at' => now(),
    ]);

    $response = $this->getJson("/admin/chats/search?channel_id={$this->channel->id}");

    $response->assertOk();
    $channelIds = collect($response->json('conversations'))->pluck('channel_id')->unique()->values();
    expect($channelIds)->toContain($this->channel->id);
    expect($channelIds)->not->toContain($otherChannel->id);
});
