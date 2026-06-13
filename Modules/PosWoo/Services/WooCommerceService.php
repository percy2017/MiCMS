<?php

namespace Modules\PosWoo\Services;

use Automattic\WooCommerce\HttpClient\Response;
use Codexshaper\WooCommerce\Facades\Customer;
use Codexshaper\WooCommerce\Facades\Order;
use Codexshaper\WooCommerce\Facades\PaymentGateway;
use Codexshaper\WooCommerce\Facades\Product;
use Codexshaper\WooCommerce\Facades\Setting;
use Codexshaper\WooCommerce\Facades\Variation;
use Codexshaper\WooCommerce\Facades\WooCommerce as WooCommerceFacade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WooCommerceService
{
    public function searchProducts(string $query = '', int $page = 1, int $perPage = 10): array
    {
        try {
            $options = [
                'per_page' => $perPage,
                'page' => $page,
            ];

            if ($query !== '') {
                $options['search'] = $query;
            }

            $products = Product::all($options);

            $total = 0;

            $mapped = collect($products)->map(function (mixed $product): array {
                $product = (array) $product;

                return [
                    'id' => (int) ($product['id'] ?? 0),
                    'name' => $product['name'] ?? '',
                    'slug' => $product['slug'] ?? '',
                    'type' => $product['type'] ?? 'simple',
                    'price' => $product['price'] ?? '',
                    'regular_price' => $product['regular_price'] ?? '',
                    'sale_price' => $product['sale_price'] ?? '',
                    'stock_status' => $product['stock_status'] ?? 'instock',
                    'stock_quantity' => $product['stock_quantity'] ?? null,
                    'categories' => collect($product['categories'] ?? [])->map(fn (mixed $c): array => [
                        'id' => (int) ($c->id ?? 0),
                        'name' => $c->name ?? '',
                    ])->values()->all(),
                    'images' => collect($product['images'] ?? [])->map(fn (mixed $img): array => [
                        'src' => $img->src ?? '',
                        'alt' => $img->alt ?? '',
                    ])->values()->all(),
                    'variations' => collect($product['variations'] ?? [])->map(fn (mixed $v): int => (int) $v)->values()->all(),
                ];
            })->values()->all();

            return ['data' => $mapped, 'total' => $total, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce searchProducts failed: '.$e->getMessage());

            return ['data' => [], 'total' => 0, 'error' => $e->getMessage()];
        }
    }

    public function getProduct(int $id): array
    {
        try {
            $product = Product::find($id);

            if (! $product) {
                return ['data' => null, 'error' => 'Producto no encontrado'];
            }

            $product = (array) $product;

            return ['data' => [
                'id' => (int) ($product['id'] ?? 0),
                'name' => $product['name'] ?? '',
                'type' => $product['type'] ?? 'simple',
                'price' => $product['price'] ?? '',
                'regular_price' => $product['regular_price'] ?? '',
                'description' => $product['description'] ?? '',
                'stock_status' => $product['stock_status'] ?? 'instock',
                'categories' => collect($product['categories'] ?? [])->map(fn (mixed $c): array => [
                    'id' => (int) ($c->id ?? 0),
                    'name' => $c->name ?? '',
                ])->values()->all(),
                'images' => collect($product['images'] ?? [])->map(fn (mixed $img): array => [
                    'src' => $img->src ?? '',
                    'alt' => $img->alt ?? '',
                ])->values()->all(),
                'variations' => collect($product['variations'] ?? [])->map(fn (mixed $v): int => (int) $v)->values()->all(),
            ], 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce getProduct failed: '.$e->getMessage());

            return ['data' => null, 'error' => $e->getMessage()];
        }
    }

    public function getVariations(int $productId): array
    {
        try {
            $variations = Variation::all($productId);

            $mapped = collect($variations)->map(function (mixed $v): array {
                $v = (array) $v;

                $attributes = collect($v['attributes'] ?? [])->map(fn (mixed $a): array => [
                    'name' => $a->name ?? '',
                    'option' => $a->option ?? '',
                ])->values()->all();

                return [
                    'id' => (int) ($v['id'] ?? 0),
                    'price' => $v['price'] ?? '0',
                    'regular_price' => $v['regular_price'] ?? '0',
                    'sale_price' => $v['sale_price'] ?? '',
                    'stock_status' => $v['stock_status'] ?? 'instock',
                    'stock_quantity' => $v['stock_quantity'] ?? null,
                    'sku' => $v['sku'] ?? '',
                    'attributes' => $attributes,
                ];
            })->values()->all();

            return ['data' => $mapped, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce getVariations failed: '.$e->getMessage());

            return ['data' => [], 'error' => $e->getMessage()];
        }
    }

    public function searchCustomers(string $query = ''): array
    {
        try {
            $options = ['per_page' => 20];

            if ($query !== '') {
                $options['search'] = $query;
            }

            $customers = Customer::all($options);

            $mapped = collect($customers)->map(function (mixed $c): array {
                $c = (array) $c;

                return [
                    'id' => (int) ($c['id'] ?? 0),
                    'name' => trim(($c['first_name'] ?? '').' '.($c['last_name'] ?? '')),
                    'email' => $c['email'] ?? '',
                    'phone' => $c['phone'] ?? '',
                ];
            })->values()->all();

            return ['data' => $mapped, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce searchCustomers failed: '.$e->getMessage());

            return ['data' => [], 'error' => $e->getMessage()];
        }
    }

    public function listPaymentGateways(bool $onlyEnabled = true): array
    {
        try {
            $gateways = PaymentGateway::all();

            $mapped = collect($gateways)->map(function (mixed $g): array {
                $g = (array) $g;
                $enabled = ($g['enabled'] ?? 'no') === 'yes' || $g['enabled'] === true || $g['enabled'] === '1' || $g['enabled'] === 1;

                return [
                    'id' => (string) ($g['id'] ?? ''),
                    'title' => $g['title'] ?? ($g['method_title'] ?? $g['id'] ?? ''),
                    'method_title' => $g['method_title'] ?? '',
                    'method_description' => $g['method_description'] ?? '',
                    'enabled' => $enabled,
                ];
            })->filter(fn (array $g): bool => $g['id'] !== '');

            if ($onlyEnabled) {
                $mapped = $mapped->filter(fn (array $g): bool => $g['enabled']);
            }

            return ['data' => $mapped->values()->all(), 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce listPaymentGateways failed: '.$e->getMessage());

            return ['data' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  array{items: array, customer?: array, payment_method: string, payment_method_title: string, note?: string}  $data
     */
    public function createOrder(array $data): array
    {
        try {
            $lineItems = [];
            foreach ($data['items'] as $item) {
                $lineItem = [
                    'product_id' => $item['product_id'],
                    'variation_id' => $item['variation_id'] ?? 0,
                    'quantity' => $item['quantity'],
                ];
                if (isset($item['price']) && $item['price'] !== '' && (float) $item['price'] >= 0) {
                    $lineItem['subtotal'] = (string) round(((float) $item['price']) * (int) $item['quantity'], 2);
                    $lineItem['total'] = $lineItem['subtotal'];
                }
                $lineItems[] = $lineItem;
            }

            $orderData = [
                'payment_method' => $data['payment_method'],
                'payment_method_title' => $data['payment_method_title'],
                'status' => 'completed',
                'set_paid' => true,
                'line_items' => $lineItems,
                'meta_data' => [
                    ['key' => '_wc_order_attribution_device', 'value' => 'desktop'],
                    ['key' => '_wc_order_attribution_medium', 'value' => 'pos'],
                    ['key' => '_wc_order_attribution_source_type', 'value' => 'pos'],
                    ['key' => '_wc_order_attribution_utm_source', 'value' => 'pos'],
                    ['key' => '_pos_sale', 'value' => 'true'],
                ],
            ];

            $customer = $data['customer'] ?? null;
            if (is_array($customer) && ! empty($customer['id'])) {
                $fullName = trim((string) ($customer['name'] ?? ''));
                $parts = $fullName !== '' ? explode(' ', $fullName, 2) : ['', ''];
                $firstName = $parts[0] ?: 'Cliente';
                $lastName = $parts[1] ?? '';
                $rawEmail = (string) ($customer['email'] ?? '');
                $email = $this->sanitizeBillingEmail($rawEmail, (string) ($customer['phone'] ?? ''), (string) ($customer['id'] ?? ''));
                $phone = $customer['phone'] ?? null;

                $orderData['billing'] = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                    'address_1' => '-',
                    'city' => '-',
                    'country' => 'BO',
                ];

                $orderData['meta_data'][] = ['key' => '_contact_id', 'value' => (string) $customer['id']];
                $orderData['meta_data'][] = ['key' => '_contact_name', 'value' => $fullName];
                if ($phone) {
                    $orderData['meta_data'][] = ['key' => '_contact_phone', 'value' => (string) $phone];
                }
                if ($rawEmail !== '' && $rawEmail !== $email) {
                    $orderData['meta_data'][] = ['key' => '_billing_email_sanitized', 'value' => $rawEmail];
                }
            }

            if (! empty($data['note'])) {
                $orderData['customer_note'] = $data['note'];
            }

            if (($data['type'] ?? 'direct') === 'subscription') {
                $orderData['meta_data'][] = ['key' => '_is_pos_subscription', 'value' => 'true'];
                $orderData['meta_data'][] = ['key' => '_subscription_title', 'value' => (string) ($data['subscription_title'] ?? '')];
                $orderData['meta_data'][] = ['key' => '_subscription_end_date', 'value' => (string) ($data['subscription_end_date'] ?? '')];
                $orderData['meta_data'][] = ['key' => '_sale_date', 'value' => now()->toDateString()];
            }

            $order = Order::create($orderData);
            $order = $order instanceof Collection ? $order->all() : (array) $order;

            return ['data' => [
                'id' => (int) ($order['id'] ?? 0),
                'status' => $order['status'] ?? '',
                'total' => $order['total'] ?? '0',
                'date_created' => $order['date_created'] ?? '',
            ], 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce createOrder failed: '.$e->getMessage());

            return ['data' => null, 'error' => $e->getMessage()];
        }
    }

    public function listOrders(int $page = 1, int $perPage = 10, string $search = '', string $status = '', ?string $after = null, ?string $before = null): array
    {
        try {
            $options = [
                'per_page' => $perPage,
                'page' => $page,
                'orderby' => 'date',
                'order' => 'desc',
            ];

            if ($search !== '') {
                $options['search'] = $search;
            }
            if ($status !== '') {
                $options['status'] = $status;
            }
            if ($after !== null) {
                $options['after'] = $after;
            }
            if ($before !== null) {
                $options['before'] = $before;
            }

            $orders = Order::all($options);

            $response = WooCommerceFacade::getResponse();
            $rawHeaders = $response instanceof Response ? $response->getHeaders() : [];

            $total = self::headerValue($rawHeaders, 'X-WP-Total', count($orders));
            $totalPages = self::headerValue($rawHeaders, 'X-WP-TotalPages', 1);

            $mapped = collect($orders)->map(function (mixed $o): array {
                $o = (array) $o;

                $items = collect($o['line_items'] ?? [])->map(fn (mixed $i): array => [
                    'name' => $i->name ?? '',
                    'quantity' => (int) ($i->quantity ?? 0),
                    'price' => $i->price ?? '0',
                    'total' => $i->total ?? '0',
                ])->values()->all();

                $billing = (array) ($o['billing'] ?? []);

                return [
                    'id' => (int) ($o['id'] ?? 0),
                    'status' => $o['status'] ?? '',
                    'total' => $o['total'] ?? '0',
                    'date_created' => $o['date_created'] ?? '',
                    'customer_name' => trim(($billing['first_name'] ?? '').' '.($billing['last_name'] ?? '')),
                    'customer_email' => $billing['email'] ?? '',
                    'customer_phone' => $billing['phone'] ?? '',
                    'items' => $items,
                    'payment_method_title' => $o['payment_method_title'] ?? '',
                    'meta_data' => $o['meta_data'] ?? [],
                    'currency' => $o['currency'] ?? '',
                ];
            })->values()->all();

            return [
                'data' => $mapped,
                'total' => (int) $total,
                'totalPages' => (int) $totalPages,
                'currentPage' => $page,
                'current_page' => $page,
                'lastPage' => (int) $totalPages,
                'last_page' => (int) $totalPages,
                'perPage' => $perPage,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce listOrders failed: '.$e->getMessage());

            return ['data' => [], 'total' => 0, 'totalPages' => 0, 'currentPage' => $page, 'perPage' => $perPage, 'error' => $e->getMessage()];
        }
    }

    public function listSubscriptions(): array
    {
        try {
            $orders = Order::all(['meta_key' => '_is_pos_subscription', 'meta_value' => 'true', 'per_page' => 100]);

            $mapped = collect($orders)->map(fn (mixed $o): array => (array) $o)->filter(function (array $o): bool {
                $raw = $o['meta_data'] ?? [];
                foreach ($raw as $m) {
                    $item = is_array($m) ? $m : (array) $m;
                    if (($item['key'] ?? '') === '_subscription_end_date') {
                        return true;
                    }
                }

                return false;
            })->values()->all();

            return ['data' => $mapped, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce listSubscriptions failed: '.$e->getMessage());

            return ['data' => [], 'error' => $e->getMessage()];
        }
    }

    public function ordersInPeriodTotal(string $after, ?string $before = null): array
    {
        try {
            $storeUrl = (string) config('woocommerce.store_url');
            $ck = (string) config('woocommerce.consumer_key');
            $cs = (string) config('woocommerce.consumer_secret');

            $sum = 0.0;
            $count = 0;
            $page = 1;
            $totalPages = 1;
            $max = 50;

            while ($page <= $totalPages && $page <= $max) {
                $params = ['per_page' => 100, 'page' => $page, 'after' => $after];
                if ($before !== null && $before !== '') {
                    $params['before'] = $before;
                }
                $qs = http_build_query($params);
                $u = "{$storeUrl}/wp-json/wc/v3/orders?consumer_key={$ck}&consumer_secret={$cs}&{$qs}";

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $u,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => true,
                    CURLOPT_TIMEOUT => 30,
                ]);
                $raw = curl_exec($ch);
                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if (! is_string($raw) || $httpCode !== 200) {
                    break;
                }
                $headersRaw = substr($raw, 0, $headerSize);
                $body = substr($raw, $headerSize);
                $decoded = json_decode($body, true);
                if (! is_array($decoded)) {
                    break;
                }

                foreach ($decoded as $o) {
                    $o = (array) $o;
                    $sum += (float) ($o['total'] ?? 0);
                    $count++;
                }

                if ($page === 1) {
                    if (preg_match('/X-WP-TotalPages:\s*(\d+)/i', $headersRaw, $m)) {
                        $totalPages = (int) $m[1];
                    } else {
                        $totalPages = 1;
                    }
                }
                $page++;
            }

            return ['count' => $count, 'total' => $sum, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce ordersInPeriodTotal failed: '.$e->getMessage());

            return ['count' => 0, 'total' => 0.0, 'error' => $e->getMessage()];
        }
    }

    public function getStoreCurrency(): array
    {
        try {
            $current = WooCommerceFacade::find('data/currencies/current');
            $settings = Setting::options('general');
            $position = 'left';
            $decimals = 2;
            foreach ((array) $settings as $s) {
                $s = (array) $s;
                if (($s['id'] ?? '') === 'woocommerce_currency_pos') {
                    $position = (string) ($s['value'] ?? $position);
                }
                if (($s['id'] ?? '') === 'woocommerce_price_num_decimals') {
                    $decimals = (int) ($s['value'] ?? $decimals);
                }
            }

            $code = strtoupper((string) ($current->code ?? 'USD'));
            $symbol = html_entity_decode((string) ($current->symbol ?? $code), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return ['code' => $code, 'symbol' => $symbol, 'decimals' => $decimals, 'position' => $position, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce getStoreCurrency failed: '.$e->getMessage());

            return ['code' => 'USD', 'symbol' => '$', 'decimals' => 2, 'position' => 'left', 'error' => $e->getMessage()];
        }
    }

    public function getOrder(int $id): array
    {
        try {
            $orders = Order::all(['include' => [$id]]);
            $order = $orders[0] ?? null;
            if (! $order) {
                return ['data' => null, 'error' => 'Orden no encontrada'];
            }

            return ['data' => (array) $order, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce getOrder failed: '.$e->getMessage());

            return ['data' => null, 'error' => $e->getMessage()];
        }
    }

    public function deleteOrder(int $id): array
    {
        try {
            Order::delete($id, ['force' => true]);

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce deleteOrder failed: '.$e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function updateOrderMeta(int $id, array $metaData): array
    {
        try {
            $orders = Order::all(['include' => [$id]]);
            $order = $orders[0] ?? null;
            if (! $order) {
                return ['data' => null, 'error' => 'Orden no encontrada'];
            }

            $existing = (array) $order;
            $currentMeta = $existing['meta_data'] ?? [];

            $filtered = collect($currentMeta)->reject(function (mixed $m) use ($metaData): bool {
                $item = is_array($m) ? $m : (array) $m;

                return isset($metaData[$item['key'] ?? '']);
            })->values()->all();

            foreach ($metaData as $key => $value) {
                $filtered[] = ['key' => $key, 'value' => (string) $value];
            }

            $updated = Order::update($id, ['meta_data' => $filtered]);

            return ['data' => (array) $updated, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce updateOrderMeta failed: '.$e->getMessage());

            return ['data' => null, 'error' => $e->getMessage()];
        }
    }

    public function updateOrderItems(int $id, array $items): array
    {
        try {
            $orders = Order::all(['include' => [$id]]);
            $order = $orders[0] ?? null;
            if (! $order) {
                return ['data' => null, 'error' => 'Orden no encontrada'];
            }

            $lineItems = [];
            foreach ($items as $item) {
                $qty = (int) ($item['quantity'] ?? 1);
                $itemId = (int) ($item['id'] ?? 0);

                if ($qty === 0 && $itemId > 0) {
                    $lineItems[] = ['id' => $itemId, 'quantity' => 0];

                    continue;
                }

                $entry = ['quantity' => $qty];
                if ($itemId > 0) {
                    $entry['id'] = $itemId;
                }
                if (isset($item['price'])) {
                    $entry['price'] = (float) $item['price'];
                }
                if (! empty($item['product_id']) && $itemId === 0) {
                    $entry['product_id'] = (int) $item['product_id'];
                }
                $lineItems[] = $entry;
            }

            $updated = Order::update($id, ['line_items' => $lineItems]);

            return ['data' => (array) $updated, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce updateOrderItems failed: '.$e->getMessage());

            return ['data' => null, 'error' => $e->getMessage()];
        }
    }

    public function updateOrderPayment(int $id, string $paymentMethod, ?string $paymentMethodTitle = null): array
    {
        try {
            $payload = ['payment_method' => $paymentMethod];
            if ($paymentMethodTitle !== null && $paymentMethodTitle !== '') {
                $payload['payment_method_title'] = $paymentMethodTitle;
            }
            $updated = Order::update($id, $payload);

            return ['data' => (array) $updated, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce updateOrderPayment failed: '.$e->getMessage());

            return ['data' => null, 'error' => $e->getMessage()];
        }
    }

    private static function headerValue(array $headers, string $key, mixed $default): mixed
    {
        if (array_key_exists($key, $headers)) {
            return $headers[$key];
        }

        $lower = strtolower($key);
        foreach ($headers as $k => $v) {
            if (strtolower((string) $k) === $lower) {
                return $v;
            }
        }

        return $default;
    }

    /**
     * Devuelve un email RFC-5321 válido para usar como billing.email
     * de WooCommerce. Si el email original es vacío, malformado (doble
     *
     * @, sin local part, etc.) o no es único, genera un placeholder
     * basado en el teléfono o el id del customer.
     */
    public function sanitizeBillingEmail(string $raw, string $phone = '', string $customerId = ''): string
    {
        $raw = trim($raw);

        if ($raw !== '' && filter_var($raw, FILTER_VALIDATE_EMAIL) && substr_count($raw, '@') === 1) {
            return $raw;
        }

        $seed = $phone !== '' ? preg_replace('/\D+/', '', $phone) : '';
        if ($seed === '') {
            $seed = $customerId !== '' ? 'u'.$customerId : 'pos'.time();
        }
        if ($seed === '') {
            $seed = 'unknown';
        }

        return 'pos-'.$seed.'@pos.local';
    }
}
