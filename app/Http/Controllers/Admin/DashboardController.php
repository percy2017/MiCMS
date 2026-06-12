<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\Message;
use Modules\PosWoo\Services\WooCommerceService;

class DashboardController extends Controller
{
    public function index(Request $request, WooCommerceService $woo): Response
    {
        abort_unless($request->user()?->can('view admin'), 403);

        $payload = Cache::remember('admin.dashboard', 60, function () use ($woo): array {
            return [
                'chats' => $this->chatsSection(),
                'sales' => $this->salesSection($woo),
                'users' => $this->usersSection(),
                'recent_messages' => $this->recentMessages(),
            ];
        });

        return Inertia::render('admin/index', $payload);
    }

    /**
     * @return array{metrics: array<string, int>, recent: array<int, array<string, mixed>>}
     */
    private function chatsSection(): array
    {
        $today = Carbon::today();

        $row = Conversation::query()
            ->selectRaw("
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
                SUM(COALESCE(unread_by_admin, 0)) as unread_total,
                SUM(CASE WHEN last_message_at >= ? THEN 1 ELSE 0 END) as today_count
            ", [$today])
            ->first();

        $recent = Conversation::query()
            ->with(['user:id,name,avatar_media_id', 'user.avatar:id,disk,path', 'channel:id,name,type,settings,config'])
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Conversation $c): array => [
                'id' => $c->id,
                'name' => $c->user?->name ?? $c->visitor_name ?? 'Sin nombre',
                'avatar_url' => $c->user?->avatar?->url(),
                'channel_name' => $c->channel
                    ? ($c->channel->type?->value === 'evolution'
                        ? ($c->channel->config['instance_name'] ?? $c->channel->settings['display_name'] ?? $c->channel->name)
                        : ($c->channel->settings['display_name'] ?? $c->channel->name))
                    : null,
                'unread_by_admin' => (int) ($c->unread_by_admin ?? 0),
                'last_message_at' => $c->last_message_at?->toIso8601String(),
                'last_message_at_diff' => $c->last_message_at?->diffForHumans(),
                'last_message_preview' => $this->previewFor($c->messages->sortByDesc('created_at')->first()),
                'status' => $c->status?->value,
            ])
            ->values()
            ->all();

        return [
            'metrics' => [
                'open' => (int) ($row->open_count ?? 0),
                'unread' => (int) ($row->unread_total ?? 0),
                'today' => (int) ($row->today_count ?? 0),
            ],
            'recent' => $recent,
        ];
    }

    /**
     * @return array{currency: array<string, mixed>, metrics: array<string, int>, recent: array<int, array<string, mixed>>}
     */
    private function salesSection(WooCommerceService $woo): array
    {
        $currency = $woo->getStoreCurrency();
        $result = $woo->listOrders(page: 1, perPage: 5, search: '', status: '');
        $error = $result['error'] ?? null;

        $recent = collect($result['data'] ?? [])->map(function (array $o): array {
            $customerName = trim((string) ($o['customer_name'] ?? ''));
            $customerEmail = (string) ($o['customer_email'] ?? '');
            $customerPhone = preg_replace('/\D+/', '', (string) ($o['customer_phone'] ?? ''));

            $user = null;
            if ($customerPhone !== '') {
                $user = User::with('avatar:id,disk,path')->where('phone', 'like', "%{$customerPhone}")->first();
            }

            $display = $user?->name ?? ($customerName !== '' ? $customerName : '');

            return [
                'id' => (int) ($o['id'] ?? 0),
                'status' => (string) ($o['status'] ?? ''),
                'total' => (string) ($o['total'] ?? '0'),
                'date_created' => (string) ($o['date_created'] ?? ''),
                'customer_name' => $display,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'user_id' => $user?->id,
                'avatar_url' => $user?->avatar?->url(),
                'currency' => strtoupper((string) ($o['currency'] ?? '')),
            ];
        })->values()->all();

        $totalResult = $woo->listOrders(page: 1, perPage: 1, search: '', status: '');
        $totalAll = is_array($totalResult['total'] ?? null) ? 0 : (int) ($totalResult['total'] ?? 0);

        $monthStart = Carbon::now()->startOfMonth()->toIso8601String();
        $monthPeriod = $woo->ordersInPeriodTotal($monthStart);
        $monthCount = (int) ($monthPeriod['count'] ?? 0);
        $monthSum = (float) ($monthPeriod['total'] ?? 0);

        $todayStart = Carbon::today()->toIso8601String();
        $todayPeriod = $woo->ordersInPeriodTotal($todayStart);
        $todayCount = (int) ($todayPeriod['count'] ?? 0);
        $todaySum = (float) ($todayPeriod['total'] ?? 0);

        $subsResult = $woo->listSubscriptions();
        $subsTotal = is_array($subsResult['data'] ?? null) ? count($subsResult['data']) : 0;

        return [
            'currency' => $currency,
            'metrics' => [
                'total' => $totalAll,
                'this_month' => $monthCount,
                'this_month_sum' => $monthSum,
                'today' => $todayCount,
                'today_sum' => $todaySum,
                'subscriptions' => $subsTotal,
            ],
            'recent' => $recent,
            'error' => $error,
        ];
    }

    /**
     * @return array{metrics: array<string, int>, recent: array<int, array<string, mixed>>}
     */
    private function usersSection(): array
    {
        $today = Carbon::today();

        $total = (int) User::count();
        $todayCount = (int) User::whereDate('created_at', $today)->count();

        $byCountry = User::query()
            ->whereNotNull('country_code')
            ->selectRaw('country_code, COUNT(*) as c')
            ->groupBy('country_code')
            ->orderByDesc('c')
            ->limit(5)
            ->pluck('c', 'country_code')
            ->map(fn ($v): int => (int) $v)
            ->toArray();

        $recent = User::query()
            ->with('avatar:id,disk,path')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => (string) ($u->name ?? ''),
                'email' => (string) ($u->email ?? ''),
                'phone' => $u->phone,
                'country_code' => $u->country_code,
                'avatar_url' => $u->avatar?->url(),
                'created_at' => $u->created_at?->toIso8601String(),
                'created_at_diff' => $u->created_at?->diffForHumans(),
            ])
            ->values()
            ->all();

        return [
            'metrics' => [
                'total' => $total,
                'today' => $todayCount,
                'countries' => count(User::query()->whereNotNull('country_code')->distinct('country_code')->pluck('country_code')->all()),
            ],
            'by_country' => $byCountry,
            'recent' => $recent,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentMessages(): array
    {
        return Message::query()
            ->with([
                'conversation:id,user_id,channel_id,visitor_name,external_id',
                'conversation.user:id,name,avatar_media_id',
                'conversation.user.avatar:id,disk,path',
                'conversation.channel:id,name,type,settings,config',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Message $m): array => [
                'id' => $m->id,
                'conversation_id' => $m->conversation_id,
                'role' => $m->role,
                'type' => $m->type?->value,
                'content' => (string) ($m->content ?? ''),
                'created_at' => $m->created_at?->toIso8601String(),
                'created_at_diff' => $m->created_at?->diffForHumans(),
                'user_name' => $m->conversation?->user?->name ?? $m->conversation?->visitor_name ?? 'Sin nombre',
                'user_avatar_url' => $m->conversation?->user?->avatar?->url(),
                'channel_name' => $m->conversation?->channel
                    ? ($m->conversation->channel->type?->value === 'evolution'
                        ? ($m->conversation->channel->config['instance_name'] ?? $m->conversation->channel->settings['display_name'] ?? $m->conversation->channel->name)
                        : ($m->conversation->channel->settings['display_name'] ?? $m->conversation->channel->name))
                    : null,
                'preview' => $this->previewFor($m),
            ])
            ->values()
            ->all();
    }

    private function previewFor(?Message $m): ?string
    {
        if (! $m) {
            return null;
        }
        $content = trim((string) ($m->content ?? ''));
        if ($content !== '') {
            return mb_strlen($content) > 80 ? mb_substr($content, 0, 77).'…' : $content;
        }
        $type = $m->type?->value ?? 'text';

        return match ($type) {
            'image' => '🖼 Imagen',
            'audio' => '🎵 Audio',
            'video' => '🎬 Video',
            'document' => '📄 Documento',
            'sticker' => '🏷 Sticker',
            'location' => '📍 Ubicación',
            'contact' => '👤 Contacto',
            default => '—',
        };
    }
}
