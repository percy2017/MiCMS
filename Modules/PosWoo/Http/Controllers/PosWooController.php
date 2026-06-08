<?php

namespace Modules\PosWoo\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PosWoo\Services\WooCommerceService;

class PosWooController extends Controller
{
    use AuthorizesRequests;

    public function dashboard(WooCommerceService $woo): Response
    {
        $this->authorize('view pos-woo');

        $products = $woo->searchProducts('', 1, 50);

        return Inertia::render('PosWoo::Dashboard', [
            'initialProducts' => $products['data'],
            'error' => $products['error'],
        ]);
    }

    public function searchProducts(Request $request, WooCommerceService $woo): JsonResponse
    {
        $this->authorize('view pos-woo');

        $data = $woo->searchProducts(
            query: $request->string('search', ''),
            page: (int) $request->integer('page', 1),
            perPage: (int) $request->integer('per_page', 50),
        );

        return response()->json($data);
    }

    public function productVariations(int $productId, WooCommerceService $woo): JsonResponse
    {
        $this->authorize('view pos-woo');

        $data = $woo->getVariations($productId);

        return response()->json($data);
    }

    public function searchCustomers(Request $request, WooCommerceService $woo): JsonResponse
    {
        $this->authorize('view pos-woo');

        $data = $woo->searchCustomers(
            query: $request->string('search', ''),
        );

        return response()->json($data);
    }

    public function checkout(Request $request, WooCommerceService $woo): JsonResponse
    {
        $this->authorize('manage pos-woo');

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.variation_id' => 'sometimes|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'customer_id' => 'sometimes|integer',
            'payment_method' => 'required|string|in:cash,card',
            'note' => 'sometimes|string|max:500',
        ]);

        $paymentTitles = [
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
        ];

        $data = $woo->createOrder([
            'items' => $validated['items'],
            'customer_id' => $validated['customer_id'] ?? null,
            'payment_method' => $validated['payment_method'],
            'payment_method_title' => $paymentTitles[$validated['payment_method']],
            'note' => $validated['note'] ?? '',
        ]);

        return response()->json($data);
    }

    public function ordersPage(Request $request, WooCommerceService $woo): Response
    {
        $this->authorize('view pos-woo');

        $page = (int) $request->integer('page', 1);
        $search = $request->string('search', '')->toString();
        $orders = $woo->listOrders(page: $page, perPage: 10, search: $search);

        return Inertia::render('PosWoo::Orders', [
            'orders' => $orders['data'],
            'total' => $orders['total'],
            'totalPages' => $orders['totalPages'],
            'currentPage' => $orders['currentPage'],
            'perPage' => $orders['perPage'],
            'search' => $search,
            'error' => $orders['error'],
        ]);
    }

    public function orders(Request $request, WooCommerceService $woo): JsonResponse
    {
        $this->authorize('view pos-woo');

        $data = $woo->listOrders(
            page: (int) $request->integer('page', 1),
            perPage: (int) $request->integer('per_page', 10),
            search: $request->string('search', '')->toString(),
        );

        return response()->json($data);
    }
}
