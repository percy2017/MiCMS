import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useEscapeKey } from '@/hooks/use-escape-key';
import { CartPanel } from '../Components/cart-panel';
import { ProductGrid } from '../Components/product-grid';
import type {
    CartItem,
    Customer,
    PaymentGateway,
    Product,
    WooVariation,
} from '../Components/types';

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
    const [submitting, setSubmitting] = useState(false);
    const [showCustomerSearch, setShowCustomerSearch] = useState(false);
    const [customerQuery, setCustomerQuery] = useState('');
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [customersLoading, setCustomersLoading] = useState(false);
    const [variationPicker, setVariationPicker] = useState<{
        product: Product;
        variations: WooVariation[];
    } | null>(null);
    const [gateways, setGateways] = useState<PaymentGateway[]>([]);
    const [gatewaysLoading, setGatewaysLoading] = useState(false);
    const [gatewaysError, setGatewaysError] = useState<string | null>(null);
    const [customersShown, setCustomersShown] = useState(false);
    const [saleType, setSaleType] = useState<'direct' | 'subscription'>('direct');
    const [subscriptionTitle, setSubscriptionTitle] = useState('');
    const [subscriptionEndDate, setSubscriptionEndDate] = useState(() => {
        const d = new Date();
        d.setDate(d.getDate() + 30);
        return d.toISOString().slice(0, 10);
    });
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const customerTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEscapeKey(!!variationPicker, () => setVariationPicker(null));
    useEscapeKey(showCustomerSearch, () => {
        setShowCustomerSearch(false);
        setCustomerQuery('');
        setCustomersShown(false);
    });

    useEffect(() => {
        loadGateways();
    }, []);

    const doSearch = useCallback((query: string) => {
        setLoading(true);
        setError(null);
        fetch(`/admin/pos-woo/products?search=${encodeURIComponent(query)}&per_page=10`)
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

    function updatePrice(cartKey: string, price: number) {
        setCart((prev) =>
            prev.map((item) =>
                item.cartKey === cartKey
                    ? { ...item, price, subtotal: price * item.quantity }
                    : item,
            ),
        );
    }

    function removeFromCart(cartKey: string) {
        setCart((prev) => prev.filter((item) => item.cartKey !== cartKey));
    }

    const total = cart.reduce((sum, item) => sum + item.subtotal, 0);

    function loadCustomers(query: string) {
        if (query.length < 4) {
            setCustomers([]);
            setCustomersLoading(false);
            return;
        }
        setCustomersLoading(true);
        fetch(`/admin/pos-woo/customers?search=${encodeURIComponent(query)}`)
            .then((r) => r.json())
            .then((data) => {
                setCustomers(data.data ?? []);
                setCustomersShown(true);
            })
            .catch(() => {})
            .finally(() => setCustomersLoading(false));
    }

    function loadGateways() {
        setGatewaysLoading(true);
        setGatewaysError(null);
        fetch('/admin/pos-woo/payment-gateways')
            .then((r) => r.json())
            .then((data) => {
                if (data.error) {
                    setGatewaysError(data.error);
                    setGateways([]);
                } else {
                    setGateways(data.data ?? []);
                }
            })
            .catch(() => setGatewaysError('Error al cargar métodos de pago'))
            .finally(() => setGatewaysLoading(false));
    }

    const [confirmOpen, setConfirmOpen] = useState(false);
    const [pendingCheckout, setPendingCheckout] = useState<{ method: string; title: string } | null>(null);

    function openConfirmDialog(paymentMethod: string, paymentMethodTitle: string) {
        if (!customer) {
            setError('Debes asignar un cliente antes de cobrar.');
            return;
        }
        if (saleType === 'subscription') {
            if (!subscriptionTitle.trim()) {
                setError('Debes asignar un título a la suscripción.');
                return;
            }
            if (subscriptionEndDate <= new Date().toISOString().slice(0, 10)) {
                setError('La fecha de vencimiento debe ser posterior a hoy.');
                return;
            }
        }
        setPendingCheckout({ method: paymentMethod, title: paymentMethodTitle });
        setConfirmOpen(true);
    }

    function cancelConfirm() {
        setConfirmOpen(false);
        setPendingCheckout(null);
    }

    function submitCheckout() {
        if (!pendingCheckout) {
            return;
        }
        if (!customer) {
            setError('Debes asignar un cliente antes de cobrar.');
            cancelConfirm();
            return;
        }
        setSubmitting(true);
        setError(null);
        const items = cart.map((item) => ({
            product_id: item.productId,
            variation_id: item.variationId ?? undefined,
            quantity: item.quantity,
            price: item.price,
        }));

        fetch('/admin/pos-woo/checkout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') ?? '' },
            body: JSON.stringify({
                items,
                customer_id: customer.id,
                payment_method: pendingCheckout.method,
                payment_method_title: pendingCheckout.title,
                type: saleType,
                subscription_title: saleType === 'subscription' ? subscriptionTitle : undefined,
                subscription_end_date: saleType === 'subscription' ? subscriptionEndDate : undefined,
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
                setCustomerQuery('');
                setCustomers([]);
                setSaleType('direct');
                setSubscriptionTitle('');
                const d = new Date();
                d.setDate(d.getDate() + 30);
                setSubscriptionEndDate(d.toISOString().slice(0, 10));
            })
            .catch(() => setError('Error al procesar el cobro'))
            .finally(() => {
                setSubmitting(false);
                cancelConfirm();
            });
    }

    return (
        <>
            <Head title="Pos Woo - Terminal POS" />

            <div className="flex h-[calc(100vh-4rem)] flex-col overflow-hidden">
                {/* Top bar: search + customer */}
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

                    <button
                        type="button"
                        onClick={() => {
                            setShowCustomerSearch(true);
                            setCustomersShown(false);
                            setCustomers([]);
                        }}
                        className="flex h-10 shrink-0 items-center gap-2 rounded-lg border border-dashed px-3 text-sm text-muted-foreground transition-colors hover:border-primary/50 hover:text-foreground"
                    >
                        {customer ? (
                            <>
                                {customer.avatar_url ? (
                                    <img
                                        src={customer.avatar_url}
                                        alt={customer.name}
                                        className="size-6 shrink-0 rounded-full object-cover"
                                    />
                                ) : (
                                    <div className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                        {customer.name?.charAt(0)?.toUpperCase() ?? '?'}
                                    </div>
                                )}
                                <span className="max-w-[160px] truncate">{customer.name}</span>
                                <span className="text-xs text-muted-foreground">Cambiar</span>
                            </>
                        ) : (
                            <>
                                <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span>Seleccionar cliente</span>
                            </>
                        )}
                    </button>
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
                            gateways={gateways}
                            onUpdateQuantity={updateQuantity}
                            onUpdatePrice={updatePrice}
                            onRemoveFromCart={removeFromCart}
                            onCheckout={openConfirmDialog}
                            saleType={saleType}
                            onSaleTypeChange={setSaleType}
                            subscriptionTitle={subscriptionTitle}
                            onSubscriptionTitleChange={setSubscriptionTitle}
                            subscriptionEndDate={subscriptionEndDate}
                            onSubscriptionEndDateChange={setSubscriptionEndDate}
                        />
                    </div>
                </div>
            </div>

            {/* Variation picker modal */}
            {variationPicker && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                    role="dialog"
                    aria-modal="true"
                    onClick={(e) => {
                        if (e.target === e.currentTarget) setVariationPicker(null);
                    }}
                >
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
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                    role="dialog"
                    aria-modal="true"
                    onClick={(e) => {
                        if (e.target === e.currentTarget) {
                            setShowCustomerSearch(false);
                            setCustomerQuery('');
                            setCustomersShown(false);
                        }
                    }}
                >
                    <div className="w-full max-w-md rounded-xl bg-background p-6 shadow-2xl">
                        <h3 className="mb-4 text-lg font-semibold">Buscar cliente</h3>
                        <input
                            type="text"
                            value={customerQuery}
                            onChange={(e) => {
                                const value = e.target.value;
                                setCustomerQuery(value);
                                setCustomersShown(value.length >= 4);
                                if (customerTimer.current) clearTimeout(customerTimer.current);
                                customerTimer.current = setTimeout(() => {
                                    loadCustomers(value);
                                }, 250);
                            }}
                            placeholder="Nombre, email o teléfono (mín. 4 caracteres)..."
                            className="mb-4 h-10 w-full rounded-lg border bg-muted/50 px-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            autoFocus
                        />
                        {customersLoading && (
                            <div className="flex justify-center py-4">
                                <div className="size-5 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent" />
                            </div>
                        )}
                        <div className="mb-4 max-h-64 space-y-1 overflow-y-auto">
                            {!customersLoading && customers.length === 0 && customersShown && (
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
                                        setCustomersShown(false);
                                    }}
                                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-muted"
                                >
                                    {c.avatar_url ? (
                                        <img
                                            src={c.avatar_url}
                                            alt={c.name}
                                            className="size-10 shrink-0 rounded-full object-cover"
                                        />
                                    ) : (
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                            {c.name?.charAt(0)?.toUpperCase() ?? '?'}
                                        </div>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate font-medium">{c.name || 'Sin nombre'}</div>
                                        <div className="truncate text-xs text-muted-foreground">
                                            {c.phone || c.email || '—'}
                                        </div>
                                    </div>
                                </button>
                            ))}
                        </div>
                        <div className="flex justify-end gap-2">
                            <button
                                onClick={() => {
                                    setShowCustomerSearch(false);
                                    setCustomerQuery('');
                                    setCustomersShown(false);
                                }}
                                className="rounded-lg px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Confirm checkout dialog */}
            <Dialog open={confirmOpen} onOpenChange={(open) => { if (!open) cancelConfirm(); }}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Confirmar venta</DialogTitle>
                        <DialogDescription>
                            Revisa los datos antes de registrar la orden en WooCommerce.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3 text-sm">
                        <div className="flex items-center justify-between rounded-md border bg-muted/30 px-3 py-2">
                            <span className="text-muted-foreground">Cliente</span>
                            <span className="font-medium">{customer?.name ?? '—'}</span>
                        </div>

                        <div className="rounded-md border">
                            <div className="border-b bg-muted/30 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Artículos ({cart.reduce((s, i) => s + i.quantity, 0)})
                            </div>
                            <ul className="divide-y">
                                {cart.map((item) => (
                                    <li key={item.cartKey} className="flex items-center justify-between gap-2 px-3 py-1.5 text-sm">
                                        <span className="min-w-0 truncate">
                                            {item.label}
                                            <span className="ml-1.5 text-muted-foreground">x{item.quantity}</span>
                                        </span>
                                        <span className="shrink-0 font-mono tabular-nums">${item.subtotal.toFixed(2)}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        {saleType === 'subscription' && (
                            <div className="rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-xs">
                                <p className="font-semibold text-primary">Suscripción</p>
                                <p className="text-muted-foreground">{subscriptionTitle}</p>
                                <p className="text-muted-foreground">Vence: {new Date(subscriptionEndDate).toLocaleDateString('es')}</p>
                            </div>
                        )}

                        <div className="flex items-center justify-between rounded-md border bg-muted/30 px-3 py-2">
                            <span className="text-muted-foreground">Método de pago</span>
                            <span className="font-medium">{pendingCheckout?.title ?? '—'}</span>
                        </div>

                        <div className="flex items-center justify-between rounded-md bg-primary/5 px-3 py-2">
                            <span className="font-semibold">Total</span>
                            <span className="text-xl font-bold tabular-nums">${total.toFixed(2)}</span>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={cancelConfirm}
                            disabled={submitting}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            onClick={submitCheckout}
                            disabled={submitting}
                        >
                            {submitting ? 'Procesando…' : 'Confirmar y cobrar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

        </>
    );
}

PosDashboard.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Pos Woo', href: '/admin/pos-woo' },
    ],
};
