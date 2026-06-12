<?php

use App\Models\User;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('redirects guests to login', function () {
    get('/admin')->assertRedirect('/login');
});

it('returns 403 for authenticated users without view admin permission', function () {
    $user = User::factory()->create();
    actingAs($user);

    get('/admin')->assertForbidden();
});

it('renders the dashboard for admin with the expected sections', function () {
    $admin = adminUser();
    actingAs($admin);

    $response = get('/admin')->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('admin/index')
        ->has('chats.metrics', fn ($m) => $m
            ->has('open')->has('unread')->has('today')->etc())
        ->has('chats.recent')
        ->has('sales.currency', fn ($c) => $c
            ->has('code')->has('symbol')->has('decimals')->has('position')->etc())
        ->has('sales.metrics', fn ($m) => $m->has('total')->has('this_month')->has('this_month_sum')->has('today')->has('today_sum')->has('subscriptions')->etc())
        ->has('sales.recent')
        ->has('users.metrics')
        ->has('users.by_country')
        ->has('users.recent')
        ->has('recent_messages')
    );
});

it('limits recent conversations to five', function () {
    $admin = adminUser();
    actingAs($admin);
    $user = User::factory()->create();
    $channel = Channel::query()->first() ?? Channel::factory()->create();

    for ($i = 0; $i < 7; $i++) {
        Conversation::factory()->create([
            'user_id' => $user->id,
            'channel_id' => $channel->id,
            'last_message_at' => now()->subMinutes($i),
        ]);
    }

    get('/admin')->assertInertia(fn ($page) => $page
        ->component('admin/index')
        ->where('chats.recent', fn ($items) => count($items) === 5)
    );
});

it('returns the most recent 5 messages globally', function () {
    $admin = adminUser();
    actingAs($admin);
    $user = User::factory()->create();
    $channel = Channel::query()->first() ?? Channel::factory()->create();
    $conv = Conversation::factory()->create([
        'user_id' => $user->id,
        'channel_id' => $channel->id,
    ]);

    for ($i = 0; $i < 8; $i++) {
        Message::factory()->create([
            'conversation_id' => $conv->id,
            'content' => "msg-{$i}",
            'role' => 'user',
            'created_at' => now()->subSeconds($i),
        ]);
    }

    get('/admin')->assertInertia(fn ($page) => $page
        ->component('admin/index')
        ->where('recent_messages', fn ($items) => count($items) === 5)
    );
});

it('groups users by country code', function () {
    $admin = adminUser();
    actingAs($admin);

    User::factory()->count(3)->create(['country_code' => 'BO']);
    User::factory()->count(2)->create(['country_code' => 'AR']);
    User::factory()->count(1)->create(['country_code' => null]);

    get('/admin')->assertInertia(fn ($page) => $page
        ->component('admin/index')
        ->where('users.by_country.BO', 3)
        ->where('users.by_country.AR', 2)
    );
});
