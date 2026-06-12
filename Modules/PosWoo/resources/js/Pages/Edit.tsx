import { Head } from '@inertiajs/react';
import { Check, Loader2, Plus, Save, Search, Trash2, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEscapeKey } from '@/hooks/use-escape-key';
import { admin } from '@/routes';

type LineItem = {
    id: number;
    name: string;
    product_id: number;
    variation_id?: number;
    quantity: number;
    price: number;
    total: string;
    image?: { src?: string };
};

type PaymentGateway = { id: string; title: string; method_title: string; enabled: boolean };
type Currency = { code: string; symbol: string; decimals: number };

type Props = {
    order: Record<string, unknown>;
    meta: Record<string, string>;
    paymentGateways: PaymentGateway[];
    currency: Currency;
};

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.getAttribute('content') ?? '';
}

export default function OrderEdit({ order, meta: initialMeta, paymentGateways, currency }: Props) {
    const [meta, setMeta] = useState<Record<string, string>>(initialMeta);
    const [items, setItems] = useState<LineItem[]>((order as { line_items?: LineItem[] }).line_items ?? []);
    const [paymentMethod, setPaymentMethod] = useState<string>(String((order as { payment_method?: string }).payment_method ?? ''));
    const [paymentMethodTitle, setPaymentMethodTitle] = useState<string>(String((order as { payment_method_title?: string }).payment_method_title ?? ''));
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [showProductSearch, setShowProductSearch] = useState(false);
    const [productQuery, setProductQuery] = useState('');
    const [searchResults, setSearchResults] = useState<Array<{ id: number; name: string; price: string }>>([]);
    const [searching, setSearching] = useState(false);
    const searchRef = useRef<HTMLInputElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>();

    const formatMoney = useCallback((value: number): string => {
        const numStr = value.toLocaleString('es-MX', { minimumFractionDigits: currency.decimals, maximumFractionDigits: currency.decimals });
        const sym = currency.symbol || currency.code;
        switch (currency.position) {
            case 'right':
            case 'right_space':
                return `${numStr} ${sym}`;
            case 'left_space':
                return `${sym} ${numStr}`;
            case 'left':
            default:
                return `${sym}${numStr}`;
        }
    }, [currency.symbol, currency.code, currency.decimals, currency.position]);

    const orderId = (order as { id?: number }).id ?? 0;

    const metaKeys = Object.keys(meta).filter((k) =>
        k.startsWith('_subscription_') || k === '_sale_date' || k === '_contact_name' || k === '_contact_phone',
    );

    const labelMap: Record<string, string> = {
        _subscription_title: 'Título',
        _subscription_end_date: 'Vence',
        _sale_date: 'Fecha de venta',
        _contact_name: 'Nombre de contacto',
        _contact_phone: 'Teléfono de contacto',
    };

    const closeProductSearch = useCallback(() => {
        setShowProductSearch(false);
        setProductQuery('');
        setSearchResults([]);
    }, []);

    useEscapeKey(showProductSearch, closeProductSearch);

    function updateMeta(key: string, value: string) {
        setMeta((prev) => ({ ...prev, [key]: value }));
        setSaved(false);
    }

    function updateItem(index: number, field: keyof LineItem, value: string | number) {
        setItems((prev) => {
            const copy = [...prev];
            copy[index] = { ...copy[index], [field]: value };
            return copy;
        });
        setSaved(false);
    }

    function removeItem(index: number) {
        setItems((prev) => prev.filter((_, i) => i !== index));
        setSaved(false);
    }

    function updatePaymentMethod(id: string) {
        setPaymentMethod(id);
        const gw = paymentGateways.find((g) => g.id === id);
        setPaymentMethodTitle(gw?.title ?? id);
        setSaved(false);
    }

    async function searchProducts(q: string) {
        if (q.length < 2) {
            setSearchResults([]);
            return;
        }
        setSearching(true);
        try {
            const r = await fetch(`/admin/pos-woo/productos?search=${encodeURIComponent(q)}&per_page=10`);
            const data = await r.json();
            setSearchResults(data.data ?? []);
        } catch {
            setSearchResults([]);
        } finally {
            setSearching(false);
        }
    }

    function onSearchChange(q: string) {
        setProductQuery(q);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => searchProducts(q), 300);
    }

    function addProduct(product: { id: number; name: string; price: string }) {
        setItems((prev) => [
            ...prev,
            {
                id: 0,
                name: product.name,
                product_id: product.id,
                quantity: 1,
                price: parseFloat(product.price),
                total: product.price,
            },
        ]);
        closeProductSearch();
        setSaved(false);
    }

    async function handleSave() {
        setSaving(true);
        setError(null);
        setSaved(false);

        try {
            const body: Record<string, unknown> = {};
            if (metaKeys.length > 0) {
                body.meta = meta;
            }
            body.items = items.map((item) => ({
                id: item.id > 0 ? item.id : undefined,
                product_id: item.product_id > 0 && item.id === 0 ? item.product_id : undefined,
                name: item.name,
                quantity: item.quantity,
                price: item.price,
            }));
            if (paymentMethod !== '' && paymentMethod !== ((order as { payment_method?: string }).payment_method ?? '')) {
                body.payment_method = paymentMethod;
                body.payment_method_title = paymentMethodTitle;
            }

            const r = await fetch(`/admin/pos-woo/pedidos/${orderId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                body: JSON.stringify(body),
            });
            const data = await r.json();
            if (data.errors?.length > 0) {
                setError(data.errors.join('; '));
            } else {
                setSaved(true);
                setTimeout(() => setSaved(false), 3000);
            }
        } catch (e) {
            setError('Error de conexión');
        } finally {
            setSaving(false);
        }
    }

    const billing = (order as { billing?: Record<string, string> }).billing ?? {};
    const total = items.reduce((sum, item) => sum + item.price * item.quantity, 0);

    return (
        <>
            <Head title={`Orden #${orderId}`} />

            <div className="flex h-full min-h-0 flex-1 flex-col overflow-hidden p-4">
                <div className="mx-auto grid w-full max-w-5xl min-h-0 flex-1 grid-cols-1 gap-6 overflow-y-auto pr-1 lg:grid-cols-[1fr_320px]">
                    <div className="flex min-h-0 flex-col gap-6">
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">Orden #{orderId}</h1>
                            <p className="text-sm text-muted-foreground">
                                {billing.first_name ?? ''} {billing.last_name ?? ''} · {billing.email ?? ''}
                            </p>
                        </div>

                        <div className="rounded-lg border p-4">
                            <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Pago</h2>
                            <div className="grid gap-1.5">
                                <Label htmlFor="payment-method" className="text-xs font-medium">Método de pago</Label>
                                <select
                                    id="payment-method"
                                    value={paymentMethod}
                                    onChange={(e) => updatePaymentMethod(e.target.value)}
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <option value="">— Sin cambiar —</option>
                                    {paymentGateways.map((g) => (
                                        <option key={g.id} value={g.id}>
                                            {g.title || g.method_title || g.id}
                                        </option>
                                    ))}
                                </select>
                                {paymentMethodTitle && (
                                    <p className="text-xs text-muted-foreground">Actual: {paymentMethodTitle}</p>
                                )}
                            </div>
                        </div>

                        <div className="rounded-lg border p-4">
                            <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Items de la orden</h2>

                            <div className="space-y-3">
                                {items.map((item, i) => (
                                    <div key={i} className="flex items-start gap-2 rounded-md border bg-card p-3">
                                        <div className="grid flex-1 grid-cols-3 gap-2">
                                            <div className="col-span-3 sm:col-span-1">
                                                <Label className="text-xs text-muted-foreground">Producto</Label>
                                                <Input
                                                    type="text"
                                                    value={item.name}
                                                    onChange={(e) => updateItem(i, 'name', e.target.value)}
                                                />
                                            </div>
                                            <div>
                                                <Label className="text-xs text-muted-foreground">Cant</Label>
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    value={item.quantity}
                                                    onChange={(e) => updateItem(i, 'quantity', Math.max(0, parseInt(e.target.value) || 0))}
                                                />
                                            </div>
                                            <div>
                                                <Label className="text-xs text-muted-foreground">Precio</Label>
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min={0}
                                                    value={item.price}
                                                    onChange={(e) => updateItem(i, 'price', parseFloat(e.target.value) || 0)}
                                                />
                                            </div>
                                        </div>
                                        <Button type="button" variant="ghost" size="icon" className="mt-5 shrink-0 text-destructive" onClick={() => removeItem(i)}>
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-3 flex items-center justify-between">
                                <Button type="button" variant="outline" size="sm" onClick={() => setShowProductSearch(true)} className="gap-1.5">
                                    <Plus className="size-3.5" />
                                    Agregar producto
                                </Button>
                                <p className="text-sm font-semibold">Total: {formatMoney(total)}</p>
                            </div>
                        </div>
                    </div>

                    <div className="flex min-h-0 flex-col gap-6">
                        <div className="rounded-lg border p-4">
                            <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Metadatos de suscripción</h2>

                            {metaKeys.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Esta orden no tiene metadatos de suscripción editables.</p>
                            ) : (
                                <div className="space-y-4">
                                    {metaKeys.map((key) => (
                                        <div key={key} className="grid gap-1.5">
                                            <Label htmlFor={`meta-${key}`} className="text-xs font-medium">
                                                {labelMap[key] ?? key.replace(/^_/, '').replace(/_/g, ' ')}
                                            </Label>
                                            {key.endsWith('_date') ? (
                                                <Input
                                                    id={`meta-${key}`}
                                                    type="date"
                                                    value={meta[key] ?? ''}
                                                    onChange={(e) => updateMeta(key, e.target.value)}
                                                />
                                            ) : (
                                                <Input
                                                    id={`meta-${key}`}
                                                    type="text"
                                                    value={meta[key] ?? ''}
                                                    onChange={(e) => updateMeta(key, e.target.value)}
                                                />
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {error && (
                            <div className="flex items-center gap-2 rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                                <X className="size-4 shrink-0" />
                                {error}
                            </div>
                        )}

                        <div className="flex items-center gap-3">
                            <Button type="button" onClick={handleSave} disabled={saving} className="gap-2">
                                {saving ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                                Guardar cambios
                            </Button>
                            {saved && (
                                <span className="flex items-center gap-1.5 text-sm text-emerald-600 dark:text-emerald-400">
                                    <Check className="size-4" />
                                    Guardado
                                </span>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {showProductSearch && (
                <div
                    className="fixed inset-0 z-50 flex items-start justify-center bg-black/50 pt-20"
                    role="dialog"
                    aria-modal="true"
                    onClick={(e) => {
                        if (e.target === e.currentTarget) closeProductSearch();
                    }}
                >
                    <div className="w-full max-w-md rounded-lg border bg-card p-4 shadow-lg">
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="font-semibold">Buscar producto</h3>
                            <Button type="button" variant="ghost" size="icon" onClick={closeProductSearch}>
                                <X className="size-4" />
                            </Button>
                        </div>
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                ref={searchRef}
                                className="pl-9"
                                placeholder="Buscar productos..."
                                value={productQuery}
                                onChange={(e) => onSearchChange(e.target.value)}
                                autoFocus
                            />
                        </div>
                        <div className="mt-3 max-h-60 space-y-1 overflow-y-auto">
                            {searching && <p className="py-2 text-center text-sm text-muted-foreground">Buscando...</p>}
                            {!searching && searchResults.length === 0 && productQuery.length >= 2 && (
                                <p className="py-2 text-center text-sm text-muted-foreground">Sin resultados</p>
                            )}
                            {searchResults.map((p) => (
                                <button
                                    key={p.id}
                                    type="button"
                                    className="w-full rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
                                    onClick={() => addProduct(p)}
                                >
                                    <span className="font-medium">{p.name}</span>
                                    <span className="ml-2 text-muted-foreground">{formatMoney(parseFloat(p.price))}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

OrderEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'PosWoo', href: '/admin/pos-woo' },
        { title: 'Pedidos', href: '/admin/pos-woo/pedidos' },
        { title: 'Editar', href: '' },
    ],
};
