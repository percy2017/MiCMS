<?php

namespace App\Services;

use Automattic\WooCommerce\HttpClient\Response;
use Codexshaper\WooCommerce\Facades\Customer;
use Codexshaper\WooCommerce\Facades\Order;
use Codexshaper\WooCommerce\Facades\Product;
use Codexshaper\WooCommerce\Facades\Variation;
use Codexshaper\WooCommerce\Facades\WooCommerce as WooCommerceFacade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WooCommerceService
{
    /**
     * @return array{data: array, total: int, error: ?string}
     */
    public function searchProducts(string $query = '', int $page = 1, int $perPage = 20): array
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

    /**
     * @return array{data: ?array, error: ?string}
     */
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

    /**
     * @return array{data: array, error: ?string}
     */
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

    /**
     * @return array{data: array, error: ?string}
     */
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

    /**
     * @param  array{items: array, customer_id?: int, payment_method: string, payment_method_title: string, note?: string}  $data
     * @return array{data: ?array, error: ?string}
     */
    public function createOrder(array $data): array
    {
        try {
            $lineItems = [];
            foreach ($data['items'] as $item) {
                $lineItems[] = [
                    'product_id' => $item['product_id'],
                    'variation_id' => $item['variation_id'] ?? 0,
                    'quantity' => $item['quantity'],
                ];
            }

            $orderData = [
                'payment_method' => $data['payment_method'],
                'payment_method_title' => $data['payment_method_title'],
                'status' => 'completed',
                'set_paid' => true,
                'line_items' => $lineItems,
            ];

            if (! empty($data['customer_id'])) {
                $orderData['customer_id'] = (int) $data['customer_id'];
            }

            if (! empty($data['note'])) {
                $orderData['customer_note'] = $data['note'];
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

    /**
     * @return array{data: array, total: int, totalPages: int, currentPage: int, perPage: int, error: ?string}
     */
    public function listOrders(int $page = 1, int $perPage = 20, string $search = ''): array
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
                    'items' => $items,
                    'payment_method_title' => $o['payment_method_title'] ?? '',
                ];
            })->values()->all();

            return [
                'data' => $mapped,
                'total' => (int) $total,
                'totalPages' => (int) $totalPages,
                'currentPage' => $page,
                'perPage' => $perPage,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('WooCommerce listOrders failed: '.$e->getMessage());

            return ['data' => [], 'total' => 0, 'totalPages' => 0, 'currentPage' => $page, 'perPage' => $perPage, 'error' => $e->getMessage()];
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
}
