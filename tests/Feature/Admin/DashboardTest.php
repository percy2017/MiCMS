<?php

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;
use Modules\PosWoo\Services\WooCommerceService;

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
        ->has('media.metrics', fn ($m) => $m->has('total')->has('today')->has('size_bytes')->etc())
        ->has('media.by_mime')
        ->has('media.recent')
        ->has('expiring_subscriptions')
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

it('counts media and groups by mime_type', function () {
    $admin = adminUser();
    actingAs($admin);

    Media::factory()->count(4)->create(['mime_type' => 'image/jpeg']);
    Media::factory()->count(2)->create(['mime_type' => 'application/pdf']);

    get('/admin')->assertInertia(fn ($page) => $page
        ->component('admin/index')
        ->where('media.metrics.total', 6)
        ->where('media.metrics.today', 6)
        ->has('media.by_mime', fn ($m) => $m
            ->where('image/jpeg', 4)
            ->where('application/pdf', 2)
            ->etc())
    );
});

it('lists subscriptions expiring today and excludes others', function () {
    $admin = adminUser();
    actingAs($admin);

    Carbon\Carbon::setTestNow('2026-06-12 10:00:00');
    $today = '2026-06-12';
    $tomorrow = '2026-06-13';

    User::factory()->create([
        'name' => 'Cliente Hoy',
        'phone' => '59170000001',
        'email' => 'hoy@example.test',
    ]);

    $todayOrder = (object) [
        'id' => 9001,
        'meta_data' => [
            (object) ['key' => '_is_pos_subscription', 'value' => 'true'],
            (object) ['key' => '_subscription_end_date', 'value' => $today],
            (object) ['key' => '_subscription_title', 'value' => 'Plan Pro'],
        ],
    ];
    $tomorrowOrder = (object) [
        'id' => 9002,
        'meta_data' => [
            (object) ['key' => '_is_pos_subscription', 'value' => 'true'],
            (object) ['key' => '_subscription_end_date', 'value' => $tomorrow],
        ],
    ];

    $woo = Mockery::mock(WooCommerceService::class);
    $woo->shouldReceive('listSubscriptions')->andReturn(['data' => [$todayOrder, $tomorrowOrder], 'error' => null]);
    $woo->shouldReceive('getOrder')->with(9001)->andReturn(['data' => [
        'id' => 9001,
        'status' => 'completed',
        'total' => '99.00',
        'date_created' => '2026-06-01T10:00:00',
        'customer_name' => 'cliente',
        'customer_email' => '59170000001@whatsapp.local',
        'customer_phone' => '59170000001',
        'currency' => 'BOB',
    ], 'error' => null]);
    $woo->shouldReceive('getOrder')->with(9002)->andReturn(['data' => null, 'error' => null]);
    $woo->shouldReceive('listOrders')->andReturn(['data' => [], 'total' => 0, 'currentPage' => 1, 'totalPages' => 1, 'perPage' => 1, 'error' => null]);
    $woo->shouldReceive('ordersInPeriodTotal')->andReturn(['count' => 0, 'total' => 0.0, 'error' => null]);
    $woo->shouldReceive('getStoreCurrency')->andReturn(['code' => 'BOB', 'symbol' => 'Bs.', 'decimals' => 2, 'position' => 'left', 'error' => null]);

    app()->instance(WooCommerceService::class, $woo);
    Cache::forget('admin.dashboard');

    get('/admin')->assertInertia(fn ($page) => $page
        ->component('admin/index')
        ->where('expiring_subscriptions', fn ($items) => count($items) === 1
            && $items[0]['id'] === 9001
            && $items[0]['user_id'] !== null
            && $items[0]['customer_name'] === 'Cliente Hoy'
            && $items[0]['end_date'] === $today
            && $items[0]['title'] === 'Plan Pro')
    );

    Carbon\Carbon::setTestNow();
});

it('returns an empty list when no subscriptions expire today', function () {
    $admin = adminUser();
    actingAs($admin);

    Carbon\Carbon::setTestNow('2026-06-12 10:00:00');
    $woo = Mockery::mock(WooCommerceService::class);
    $woo->shouldReceive('listSubscriptions')->andReturn(['data' => [
        (object) [
            'id' => 9003,
            'meta_data' => [
                (object) ['key' => '_is_pos_subscription', 'value' => 'true'],
                (object) ['key' => '_subscription_end_date', 'value' => '2026-12-31'],
            ],
        ],
    ], 'error' => null]);
    $woo->shouldReceive('listOrders')->andReturn(['data' => [], 'total' => 0, 'currentPage' => 1, 'totalPages' => 1, 'perPage' => 1, 'error' => null]);
    $woo->shouldReceive('ordersInPeriodTotal')->andReturn(['count' => 0, 'total' => 0.0, 'error' => null]);
    $woo->shouldReceive('getStoreCurrency')->andReturn(['code' => 'BOB', 'symbol' => 'Bs.', 'decimals' => 2, 'position' => 'left', 'error' => null]);

    app()->instance(WooCommerceService::class, $woo);
    Cache::forget('admin.dashboard');

    get('/admin')->assertInertia(fn ($page) => $page
        ->component('admin/index')
        ->where('expiring_subscriptions', fn ($items) => count($items) === 0)
    );

    Carbon\Carbon::setTestNow();
});
