<?php

namespace Modules\PosWoo\Http\Controllers;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ChatBot\Enums\ChannelType;
use Modules\ChatBot\Enums\ConversationStatus;
use Modules\ChatBot\Models\Channel;
use Modules\ChatBot\Models\Conversation;
use Modules\PosWoo\Services\WooCommerceService;
use Spatie\Permission\Models\Role;

class PosWooController extends Controller
{
    use AuthorizesRequests;

    public function dashboard(WooCommerceService $woo): Response
    {
        $this->authorize('view pos-woo');

        $products = $woo->searchProducts('', 1, 10);

        return Inertia::render('PosWoo::Dashboard', [
            'initialProducts' => $products['data'],
            'error' => $products['error'],
            'currency' => $woo->getStoreCurrency(),
        ]);
    }

    public function searchProducts(Request $request, WooCommerceService $woo): JsonResponse
    {
        $this->authorize('view pos-woo');

        $data = $woo->searchProducts(
            query: $request->string('search', ''),
            page: (int) $request->integer('page', 1),
            perPage: (int) $request->integer('per_page', 10),
        );

        return response()->json($data);
    }

    public function productVariations(int $productId, WooCommerceService $woo): JsonResponse
    {
        $this->authorize('view pos-woo');

        $data = $woo->getVariations($productId);

        return response()->json($data);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $this->authorize('view pos-woo');

        $search = trim((string) $request->string('search', ''));

        if (strlen($search) < 4) {
            return response()->json(['data' => [], 'error' => null]);
        }

        $customers = User::query()
            ->with('avatar:id,disk,path')
            ->whereNotNull('phone')
            ->where(function ($q) use ($search) {
                $q->where('phone', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            })
            ->orderByRaw(
                'CASE
                    WHEN phone LIKE ? THEN 0
                    WHEN phone LIKE ? THEN 1
                    WHEN name LIKE ? THEN 2
                    WHEN email LIKE ? THEN 3
                    ELSE 4
                END',
                [
                    $search.'%',
                    '%'.$search,
                    $search.'%',
                    $search.'%',
                ],
            )
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone', 'avatar_media_id']);

        $data = $customers->map(function (User $user): array {
            return [
                'id' => $user->id,
                'name' => $user->name ?? '',
                'email' => $user->email ?? '',
                'phone' => $user->phone ?? '',
                'avatar_url' => $user->avatar?->url(),
            ];
        })->values()->all();

        return response()->json(['data' => $data, 'error' => null]);
    }

    public function checkout(Request $request, WooCommerceService $woo): JsonResponse
    {
        $this->authorize('manage pos-woo');

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.variation_id' => 'sometimes|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'sometimes|numeric|min:0',
            'customer_id' => 'sometimes|integer',
            'payment_method' => 'required|string',
            'payment_method_title' => 'sometimes|string',
            'note' => 'sometimes|string|max:500',
            'type' => 'sometimes|in:direct,subscription',
            'subscription_title' => 'required_if:type,subscription|nullable|string|max:255',
            'subscription_end_date' => 'required_if:type,subscription|nullable|date',
        ]);

        $customer = null;
        if (! empty($validated['customer_id'])) {
            $user = User::find($validated['customer_id']);
            if ($user) {
                $customer = [
                    'id' => $user->id,
                    'name' => $user->name ?? '',
                    'email' => $user->email ?? '',
                    'phone' => $user->phone ?? '',
                ];
            }
        }

        if (is_array($customer)) {
            $rawEmail = (string) ($customer['email'] ?? '');
            if ($rawEmail !== '' && (substr_count($rawEmail, '@') !== 1 || ! filter_var($rawEmail, FILTER_VALIDATE_EMAIL))) {
                $customer['email'] = $woo->sanitizeBillingEmail($rawEmail, (string) ($customer['phone'] ?? ''), (string) $customer['id']);
            }
        }

        $data = $woo->createOrder([
            'items' => $validated['items'],
            'customer' => $customer,
            'payment_method' => $validated['payment_method'],
            'payment_method_title' => $validated['payment_method_title'] ?? $validated['payment_method'],
            'note' => $validated['note'] ?? '',
            'type' => $validated['type'] ?? 'direct',
            'subscription_title' => $validated['subscription_title'] ?? null,
            'subscription_end_date' => $validated['subscription_end_date'] ?? null,
        ]);

        return response()->json($data);
    }

    public function ordersPage(Request $request, WooCommerceService $woo): Response
    {
        $this->authorize('view pos-woo');

        $page = (int) $request->integer('page', 1);
        $search = $request->string('search', '')->toString();
        $status = $request->string('status', '')->toString();
        $orders = $woo->listOrders(page: $page, perPage: 10, search: $search, status: $status);

        $enriched = array_map(fn (array $order): array => $this->enrichOrderWithUser($order), $orders['data']);

        return Inertia::render('PosWoo::Orders', [
            'initialOrders' => $enriched,
            'initialTotal' => $orders['total'],
            'initialTotalPages' => $orders['totalPages'],
            'initialCurrentPage' => $orders['currentPage'],
            'initialPerPage' => $orders['perPage'],
            'initialSearch' => $search,
            'error' => $orders['error'],
            'currency' => $woo->getStoreCurrency(),
        ]);
    }

    public function paymentGateways(WooCommerceService $woo): JsonResponse
    {
        $this->authorize('view pos-woo');

        $data = $woo->listPaymentGateways();

        return response()->json($data);
    }

    public function orders(Request $request, WooCommerceService $woo): JsonResponse
    {
        $this->authorize('view pos-woo');

        $data = $woo->listOrders(
            page: (int) $request->integer('page', 1),
            perPage: (int) $request->integer('per_page', 10),
            search: $request->string('search', '')->toString(),
            status: $request->string('status', '')->toString(),
        );

        $enriched = array_map(fn (array $order): array => $this->enrichOrderWithUser($order), $data['data']);

        $data['data'] = $enriched;
        $data['currency'] = $woo->getStoreCurrency();

        return response()->json($data);
    }

    public function ordersByPhone(Request $request, WooCommerceService $woo): JsonResponse
    {
        $this->authorize('view pos-woo');

        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:5'],
        ]);

        $phone = preg_replace('/\D+/', '', (string) $validated['phone']) ?? '';
        if ($phone === '') {
            return response()->json(['data' => [], 'total' => 0, 'error' => null]);
        }

        $cacheKey = 'poswoo.orders.phone.'.$phone;

        $payload = Cache::remember($cacheKey, 30, function () use ($woo, $phone): array {
            $data = $woo->listOrders(page: 1, perPage: 3, search: $phone);
            $enriched = array_map(fn (array $order): array => $this->enrichOrderWithUser($order), $data['data']);

            return [
                'data' => $enriched,
                'total' => (int) ($data['total'] ?? 0),
                'error' => $data['error'] ?? null,
            ];
        });

        return response()->json($payload);
    }

    private function enrichOrderWithUser(array $order): array
    {
        $billingEmail = $order['customer_email'] ?? '';
        $phone = $order['customer_phone'] ?? null;
        $userId = null;
        $avatarUrl = null;
        $chatConversationId = null;

        if ($phone) {
            $user = User::where('phone', $phone)->first();
        } else {
            $user = null;
        }
        if (! $user) {
            $user = User::where('email', $billingEmail)->first();
        }
        if (! $user && str_contains($billingEmail, '@whatsapp.')) {
            $phonePart = explode('@', $billingEmail)[0];
            $phoneDigits = preg_replace('/\D+/', '', $phonePart);
            if ($phoneDigits) {
                $user = User::where('phone', 'like', '%'.$phoneDigits)->first();
            }
        }
        if ($user) {
            $userId = $user->id;
            if ($user->avatar_media_id) {
                $media = Media::find($user->avatar_media_id);
                $avatarUrl = $media?->url();
            }
            $chatConversationId = Conversation::where('user_id', $user->id)
                ->whereNotNull('external_id')
                ->value('id');
        }

        $meta = collect($order['meta_data'] ?? [])->map(fn ($m): array => is_array($m) ? $m : (array) $m);
        $isSubscription = $meta->contains(fn (array $m): bool => ($m['key'] ?? '') === '_is_pos_subscription');
        $subTitle = ($meta->firstWhere('key', '_subscription_title')['value'] ?? null) ?: null;
        $subEndDate = ($meta->firstWhere('key', '_subscription_end_date')['value'] ?? null) ?: null;

        $order['user_id'] = $userId;
        $order['avatar_url'] = $avatarUrl;
        $order['chat_conversation_id'] = $chatConversationId;
        $order['customer_phone'] = $phone;
        $order['is_subscription'] = $isSubscription;
        $order['subscription_title'] = $isSubscription ? $subTitle : null;
        $order['subscription_end_date'] = $isSubscription ? $subEndDate : null;
        $order['currency_code'] = strtoupper((string) ($order['currency'] ?? ''));

        return $order;
    }

    public function findOrCreateChat(Request $request): JsonResponse
    {
        $this->authorize('view pos-woo');

        $phone = trim((string) $request->input('phone'));
        $name = trim((string) $request->input('name'));

        if ($phone === '') {
            return response()->json(['error' => 'Teléfono requerido'], 422);
        }

        $user = User::where('phone', $phone)->first();
        if (! $user) {
            $normalized = preg_replace('/\D+/', '', $phone) ?: $phone;
            $email = $normalized.'@whatsapp.local';

            $user = User::create([
                'name' => $name !== '' ? $name : 'Cliente '.$normalized,
                'email' => $email,
                'phone' => $phone,
                'password' => bcrypt(bin2hex(random_bytes(8))),
            ]);

            $defaultRole = Role::where('name', 'user')->first()
                ?? Role::orderBy('id')->first();
            if ($defaultRole) {
                $user->assignRole($defaultRole);
            }
        }

        $conv = Conversation::where('user_id', $user->id)
            ->whereNotNull('external_id')
            ->first();

        if ($conv) {
            return response()->json(['conversation_id' => $conv->id]);
        }

        $channel = Channel::where('type', ChannelType::Evolution)
            ->where('enabled', true)
            ->first();

        if (! $channel) {
            $channel = Channel::where('enabled', true)->first();
        }

        if (! $channel) {
            return response()->json(['error' => 'No hay canal de chat activo'], 404);
        }

        $normalized = preg_replace('/\D+/', '', $phone) ?: $phone;
        $email = $normalized.'@whatsapp.local';

        $conv = Conversation::create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'external_id' => $normalized.'@s.whatsapp.net',
            'visitor_name' => $name !== '' ? $name : ($user->name ?: 'Cliente'),
            'visitor_email' => $email,
            'status' => ConversationStatus::Open,
            'last_message_at' => now(),
            'unread_by_admin' => 0,
        ]);

        return response()->json(['conversation_id' => $conv->id]);
    }

    public function subscriptionsPage(WooCommerceService $woo): Response
    {
        $this->authorize('view pos-woo');

        return Inertia::render('PosWoo::Calendar', [
            'currency' => $woo->getStoreCurrency(),
        ]);
    }

    public function subscriptions(WooCommerceService $woo): JsonResponse
    {
        $this->authorize('view pos-woo');

        $data = $woo->listSubscriptions();

        $events = [];
        foreach ($data['data'] as $order) {
            $raw = $order['meta_data'] ?? [];
            $meta = [];
            foreach ($raw as $m) {
                $item = is_array($m) ? $m : (array) $m;
                if (isset($item['key'])) {
                    $meta[$item['key']] = $item;
                }
            }

            $endDate = $meta['_subscription_end_date']['value'] ?? null;
            if (! $endDate) {
                continue;
            }

            $billing = (array) ($order['billing'] ?? []);
            $title = $meta['_subscription_title']['value'] ?? ($billing['first_name'] ?? 'Suscripción');

            $contactPhone = $meta['_contact_phone']['value'] ?? $billing['phone'] ?? null;
            $contactEmail = ($meta['_contact_id']['value'] ?? null) ? null : ($billing['email'] ?? null);

            $user = null;
            $avatarUrl = null;
            $userName = null;
            $chatConvId = null;

            if ($contactPhone) {
                $user = User::where('phone', $contactPhone)->first();
            }
            if (! $user && $contactEmail) {
                $user = User::where('email', $contactEmail)->first();
            }
            if ($user) {
                $userName = $user->name;
                if ($user->avatar_media_id) {
                    $media = Media::find($user->avatar_media_id);
                    if ($media) {
                        $avatarUrl = $media->url();
                    }
                }
                $conv = Conversation::where('user_id', $user->id)
                    ->whereNotNull('external_id')
                    ->latest('last_message_at')
                    ->first(['id']);
                $chatConvId = $conv?->id;
            }

            $events[] = [
                'title' => $title,
                'start' => $endDate,
                'allDay' => true,
                'extendedProps' => [
                    'order_id' => $order['id'] ?? null,
                    'customer_name' => trim(($billing['first_name'] ?? '').' '.($billing['last_name'] ?? '')),
                    'customer_email' => $billing['email'] ?? '',
                    'customer_phone' => $billing['phone'] ?? '',
                    'total' => $order['total'] ?? null,
                    'title' => $title,
                    'subscription_title' => $meta['_subscription_title']['value'] ?? '',
                    'start_date' => $meta['_sale_date']['value'] ?? $meta['_subscription_start_date']['value'] ?? null,
                    'contact_id' => $meta['_contact_id']['value'] ?? null,
                    'contact_name' => $meta['_contact_name']['value'] ?? null,
                    'contact_phone' => $meta['_contact_phone']['value'] ?? $contactPhone,
                    'user_avatar_url' => $avatarUrl,
                    'user_name' => $userName,
                    'chat_conversation_id' => $chatConvId,
                ],
            ];
        }

        return response()->json(['events' => $events]);
    }

    public function orderEdit(int $order, WooCommerceService $woo): Response|JsonResponse
    {
        $this->authorize('view pos-woo');

        $result = $woo->getOrder($order);

        if ($result['error']) {
            return response()->json(['error' => $result['error']], 404);
        }

        $raw = $result['data']['meta_data'] ?? [];
        $meta = [];
        foreach ($raw as $m) {
            $item = is_array($m) ? $m : (array) $m;
            if (isset($item['key'])) {
                $meta[$item['key']] = $item['value'] ?? '';
            }
        }

        $gateways = $woo->listPaymentGateways();

        return Inertia::render('PosWoo::Edit', [
            'order' => $result['data'],
            'meta' => $meta,
            'paymentGateways' => $gateways['data'] ?? [],
            'currency' => $woo->getStoreCurrency(),
        ]);
    }

    public function orderUpdate(Request $request, int $order, WooCommerceService $woo): JsonResponse
    {
        $this->authorize('manage pos-woo');

        $validated = $request->validate([
            'meta' => ['sometimes', 'array'],
            'meta.*' => ['string', 'max:500'],
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['sometimes', 'integer'],
            'items.*.quantity' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'items.*.price' => ['sometimes', 'numeric', 'min:0'],
            'items.*.name' => ['sometimes', 'string', 'max:255'],
            'items.*.product_id' => ['sometimes', 'integer'],
            'payment_method' => ['sometimes', 'string', 'max:100'],
            'payment_method_title' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $response = ['ok' => true, 'errors' => []];

        if (isset($validated['meta'])) {
            $metaResult = $woo->updateOrderMeta($order, $validated['meta'] ?? []);
            if ($metaResult['error']) {
                $response['errors'][] = 'meta: '.$metaResult['error'];
            }
        }

        if (isset($validated['items'])) {
            $itemsResult = $woo->updateOrderItems($order, $validated['items']);
            if ($itemsResult['error']) {
                $response['errors'][] = 'items: '.$itemsResult['error'];
            }
        }

        if (array_key_exists('payment_method', $validated) && $validated['payment_method'] !== '') {
            $paymentResult = $woo->updateOrderPayment(
                $order,
                (string) $validated['payment_method'],
                $validated['payment_method_title'] ?? null,
            );
            if ($paymentResult['error']) {
                $response['errors'][] = 'payment: '.$paymentResult['error'];
            }
        }

        $response['ok'] = empty($response['errors']);

        return response()->json($response);
    }

    public function orderDestroy(int $order, WooCommerceService $woo): JsonResponse
    {
        $this->authorize('manage pos-woo');

        $result = $woo->deleteOrder($order);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
