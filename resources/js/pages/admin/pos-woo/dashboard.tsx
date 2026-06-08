import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { CartPanel } from '@/pages/admin/pos-woo/parts/cart-panel';
import { CheckoutModal } from '@/pages/admin/pos-woo/parts/checkout-modal';
import { ProductGrid } from '@/pages/admin/pos-woo/parts/product-grid';
import type {
    CartItem,
    Customer,
    Product,
    WooVariation,
} from '@/pages/admin/pos-woo/parts/types';

type Props = {
    initialProducts: Product[];
    error?: string | null;
};

export default function PosDashboard({ initialProducts, error: serverError }: Props) {
    const [products, setProducts] = useState<Product[]>(initialProducts);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(serverError ?? null);
    const [cart, setCart] = useState<CartItem[]>([]);
    const [customer, setCustomer] = useState<Customer | null>(null);
    const [showCheckout, setShowCheckout] = useState(false);
    const [showCustomerSearch, setShowCustomerSearch] = useState(false);
    const [customerQuery, setCustomerQuery] = useState('');
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [customersLoading, setCustomersLoading] = useState(false);
    const [variationPicker, setVariationPicker] = useState<{
        product: Product;
        variations: WooVariation[];
    } | null>(null);
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const doSearch = useCallback((query: string) => {
        setLoading(true);
        setError(null);
        fetch(`/admin/pos-woo/products?search=${encodeURIComponent(query)}&per_page=50`)
            .then((r) => r.json())
            .then((data) => {
                if (data.error) {
                    setError(data.error);
                    setProducts([]);
                } else {
                    setProducts(data.data ?? []);
                }
            })
            .catch(() => setError('Error de conexión con WooCommerce'))
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => {
        if (searchTimer.current) clearTimeout(searchTimer.current);
        searchTimer.current = setTimeout(() => {
            doSearch(search);
        }, 300);
        return () => {
            if (searchTimer.current) clearTimeout(searchTimer.current);
        };
    }, [search, doSearch]);

    function openVariationPicker(product: Product) {
        setLoading(true);
        fetch(`/admin/pos-woo/products/${product.id}/variations`)
            .then((r) => r.json())
            .then((data) => {
                if (data.error) {
                    setError(data.error);
                } else {
                    setVariationPicker({ product, variations: data.data ?? [] });
                }
            })
            .catch(() => setError('Error al cargar variaciones'))
            .finally(() => setLoading(false));
    }

    function addToCart(product: Product, variation?: WooVariation) {
        const label = variation
            ? `${product.name} — ${variation.attributes.map((a) => a.option).join(', ')}`
            : product.name;
        const price = variation ? parseFloat(variation.price) : parseFloat(product.price ?? '0');

        if (!price || isNaN(price) || price <= 0) return;

        setCart((prev) => {
            const key = variation ? `${product.id}-${variation.id}` : `${product.id}`;
            const existing = prev.find((item) => item.cartKey === key);
            if (existing) {
                return prev.map((item) =>
                    item.cartKey === key
                        ? { ...item, quantity: item.quantity + 1, subtotal: (item.quantity + 1) * price }
                        : item,
                );
            }
            return [
                ...prev,
                {
                    cartKey: key,
                    productId: product.id,
                    variationId: variation?.id ?? null,
                    label,
                    price,
                    quantity: 1,
                    subtotal: price,
                    image: product.images?.[0]?.src ?? null,
                },
            ];
        });
        setVariationPicker(null);
    }

    function updateQuantity(cartKey: string, quantity: number) {
        if (quantity <= 0) {
            setCart((prev) => prev.filter((item) => item.cartKey !== cartKey));
            return;
        }
        setCart((prev) =>
            prev.map((item) =>
                item.cartKey === cartKey
                    ? { ...item, quantity, subtotal: item.price * quantity }
                    : item,
            ),
        );
    }

    function removeFromCart(cartKey: string) {
        setCart((prev) => prev.filter((item) => item.cartKey !== cartKey));
    }

    const total = cart.reduce((sum, item) => sum + item.subtotal, 0);

    function loadCustomers(query: string) {
        setCustomersLoading(true);
        fetch(`/admin/pos-woo/customers?search=${encodeURIComponent(query)}`)
            .then((r) => r.json())
            .then((data) => {
                setCustomers(data.data ?? []);
            })
            .catch(() => {})
            .finally(() => setCustomersLoading(false));
    }

    function handleCheckoutConfirm(paymentMethod: string) {
        const items = cart.map((item) => ({
            product_id: item.productId,
            variation_id: item.variationId ?? undefined,
            quantity: item.quantity,
        }));

        fetch('/admin/pos-woo/checkout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') ?? '' },
            body: JSON.stringify({
                items,
                customer_id: customer?.id ?? undefined,
                payment_method: paymentMethod,
            }),
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.error) {
                    setError(data.error);
                    return;
                }
                setCart([]);
                setCustomer(null);
                setShowCheckout(false);
                router.reload({ only: ['initialProducts'] });
            })
            .catch(() => setError('Error al procesar el cobro'));
    }

    return (
        <>
            <Head title="Pos Woo - Terminal POS" />

            <div className="flex h-[calc(100vh-4rem)] flex-col overflow-hidden">
                {/* Search bar */}
                <div className="flex items-center gap-3 border-b bg-background px-4 py-3">
                    <div className="relative flex-1">
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar producto por nombre..."
                            className="h-10 w-full rounded-lg border bg-muted/50 pl-10 pr-4 text-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        />
                        <svg
                            className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            strokeWidth={2}
                        >
                            <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                        </svg>
                        {loading && (
                            <div className="absolute right-3 top-1/2 -translate-y-1/2">
                                <div className="size-4 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent" />
                            </div>
                        )}
                    </div>

                    <Link
                        href="/admin/paquetes"
                        className="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Configuración
                    </Link>
                </div>

                {/* Error banner */}
                {error && (
                    <div className="flex items-center gap-2 bg-destructive/10 px-4 py-2 text-sm text-destructive">
                        <svg className="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {error}
                    </div>
                )}

                {/* Main content */}
                <div className="flex flex-1 overflow-hidden">
                    <div className="flex-1 overflow-y-auto p-4">
                        <ProductGrid
                            products={products}
                            onAddToCart={(product) => {
                                if (product.type === 'variable') {
                                    openVariationPicker(product);
                                } else {
                                    addToCart(product);
                                }
                            }}
                        />
                    </div>

                    <div className="w-full max-w-sm border-l bg-background">
                        <CartPanel
                            cart={cart}
                            total={total}
                            customer={customer}
                            onUpdateQuantity={updateQuantity}
                            onRemoveFromCart={removeFromCart}
                            onCheckout={() => setShowCheckout(true)}
                            onCustomerSearch={() => {
                                setShowCustomerSearch(true);
                                loadCustomers('');
                            }}
                        />
                    </div>
                </div>
            </div>

            {/* Variation picker modal */}
            {variationPicker && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="w-full max-w-sm rounded-xl bg-background p-6 shadow-2xl">
                        <div className="mb-1 flex items-center justify-between">
                            <h3 className="text-lg font-semibold">{variationPicker.product.name}</h3>
                            <button
                                onClick={() => setVariationPicker(null)}
                                className="rounded-lg p-1 text-muted-foreground transition-colors hover:bg-muted"
                            >
                                <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <p className="mb-3 text-sm text-muted-foreground">Selecciona una variante:</p>
                        <div className="space-y-2">
                            {variationPicker.variations.length === 0 && (
                                <p className="text-sm text-muted-foreground">Sin variantes disponibles</p>
                            )}
                            {variationPicker.variations.map((v) => (
                                <button
                                    key={v.id}
                                    onClick={() => addToCart(variationPicker.product, v)}
                                    disabled={v.stock_status === 'outofstock'}
                                    className="flex w-full items-center justify-between rounded-lg border bg-card px-4 py-3 text-left transition-colors hover:border-primary disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <div>
                                        <p className="text-sm font-medium">
                                            {v.attributes.map((a) => a.option).join(', ')}
                                        </p>
                                        {v.sku && (
                                            <p className="text-xs text-muted-foreground">SKU: {v.sku}</p>
                                        )}
                                    </div>
                                    <div className="text-right">
                                        <p className="text-sm font-bold text-primary">
                                            ${parseFloat(v.price).toFixed(2)}
                                        </p>
                                        {v.stock_status === 'outofstock' && (
                                            <p className="text-xs text-destructive">Sin stock</p>
                                        )}
                                    </div>
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* Customer search modal */}
            {showCustomerSearch && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="w-full max-w-md rounded-xl bg-background p-6 shadow-2xl">
                        <h3 className="mb-4 text-lg font-semibold">Buscar cliente</h3>
                        <input
                            type="text"
                            value={customerQuery}
                            onChange={(e) => {
                                setCustomerQuery(e.target.value);
                                loadCustomers(e.target.value);
                            }}
                            placeholder="Nombre o email..."
                            className="mb-4 h-10 w-full rounded-lg border bg-muted/50 px-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            autoFocus
                        />
                        {customersLoading && (
                            <div className="flex justify-center py-4">
                                <div className="size-5 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent" />
                            </div>
                        )}
                        <div className="mb-4 max-h-48 space-y-1 overflow-y-auto">
                            {!customersLoading && customers.length === 0 && (
                                <p className="py-3 text-center text-sm text-muted-foreground">
                                    Sin resultados. La venta se hará como invitado.
                                </p>
                            )}
                            {customers.map((c) => (
                                <button
                                    key={c.id}
                                    onClick={() => {
                                        setCustomer(c);
                                        setShowCustomerSearch(false);
                                        setCustomerQuery('');
                                    }}
                                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-muted"
                                >
                                    <div className="flex size-8 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                        {c.name.charAt(0)}
                                    </div>
                                    <div>
                                        <div className="font-medium">{c.name}</div>
                                        <div className="text-xs text-muted-foreground">{c.email}</div>
                                    </div>
                                </button>
                            ))}
                        </div>
                        <div className="flex justify-end gap-2">
                            <button
                                onClick={() => {
                                    setShowCustomerSearch(false);
                                    setCustomerQuery('');
                                }}
                                className="rounded-lg px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Checkout modal */}
            {showCheckout && (
                <CheckoutModal
                    cart={cart}
                    total={total}
                    customer={customer}
                    onClose={() => setShowCheckout(false)}
                    onConfirm={handleCheckoutConfirm}
                />
            )}
        </>
    );
}

PosDashboard.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Pos Woo', href: '/admin/pos-woo' },
    ],
};
