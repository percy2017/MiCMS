import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';
import type { CartItem, Customer, PaymentGateway } from './types';

type Props = {
    cart: CartItem[];
    total: number;
    customer: Customer | null;
    gateways: PaymentGateway[];
    onUpdateQuantity: (cartKey: string, quantity: number) => void;
    onUpdatePrice: (cartKey: string, price: number) => void;
    onRemoveFromCart: (cartKey: string) => void;
    onCheckout: (paymentMethod: string, paymentMethodTitle: string) => void;
    saleType: 'direct' | 'subscription';
    onSaleTypeChange: (v: 'direct' | 'subscription') => void;
    subscriptionTitle: string;
    onSubscriptionTitleChange: (v: string) => void;
    subscriptionEndDate: string;
    onSubscriptionEndDateChange: (v: string) => void;
};

export function CartPanel({
    cart,
    total,
    customer,
    gateways,
    onUpdateQuantity,
    onUpdatePrice,
    onRemoveFromCart,
    onCheckout,
    saleType,
    onSaleTypeChange,
    subscriptionTitle,
    onSubscriptionTitleChange,
    subscriptionEndDate,
    onSubscriptionEndDateChange,
}: Props) {
    const firstEnabled = gateways.find((g) => g.enabled);
    const [methodId, setMethodId] = useState<string>('');

    const selectedGateway = (methodId ? gateways.find((g) => g.id === methodId) : null) ?? firstEnabled ?? null;
    const canCheckout = cart.length > 0 && customer !== null && selectedGateway !== null;

    function handleCobrar() {
        if (!selectedGateway) return;
        onCheckout(selectedGateway.id, selectedGateway.title);
    }

    return (
        <div className="flex h-full flex-col">
            <div className="border-b px-4 py-3">
                <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                    Carrito
                </h2>
                <p className="text-xs text-muted-foreground">
                    {cart.length === 0
                        ? 'Vacío'
                        : `${cart.reduce((s, i) => s + i.quantity, 0)} artículo${cart.length !== 1 ? 's' : ''}`}
                </p>
            </div>

            <div className="flex-1 overflow-y-auto px-4 py-2">
                {cart.length === 0 ? (
                    <div className="flex h-full items-center justify-center">
                        <p className="text-center text-sm text-muted-foreground">
                            Selecciona productos
                            <br />
                            para comenzar
                        </p>
                    </div>
                ) : (
                    <ul className="space-y-2">
                        {cart.map((item) => (
                            <li
                                key={item.cartKey}
                                className="rounded-lg bg-muted/50 p-2.5"
                            >
                                <div className="flex items-start gap-2">
                                    {item.image && (
                                        <img
                                            src={item.image}
                                            alt=""
                                            className="mt-0.5 size-8 shrink-0 rounded object-cover"
                                        />
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">
                                            {item.label}
                                        </p>
                                        <PriceEditor
                                            price={item.price}
                                            onSave={(v) => onUpdatePrice(item.cartKey, v)}
                                        />
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <button
                                            onClick={() =>
                                                onUpdateQuantity(item.cartKey, item.quantity - 1)
                                            }
                                            className="flex size-6 items-center justify-center rounded-md border text-xs transition-colors hover:bg-muted"
                                        >
                                            −
                                        </button>
                                        <span className="flex h-6 min-w-[24px] items-center justify-center text-sm font-medium tabular-nums">
                                            {item.quantity}
                                        </span>
                                        <button
                                            onClick={() =>
                                                onUpdateQuantity(item.cartKey, item.quantity + 1)
                                            }
                                            className="flex size-6 items-center justify-center rounded-md border text-xs transition-colors hover:bg-muted"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>
                                <div className="mt-1.5 flex items-center justify-between">
                                    <button
                                        onClick={() => onRemoveFromCart(item.cartKey)}
                                        className="text-[10px] text-destructive/70 hover:text-destructive"
                                    >
                                        Quitar
                                    </button>
                                    <span className="text-sm font-semibold tabular-nums">
                                        ${item.subtotal.toFixed(2)}
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <div className="space-y-3 border-t p-4">
                <div>
                    <label className="mb-1 block text-xs font-medium text-muted-foreground">
                        Método de pago
                    </label>
                    <select
                        value={methodId}
                        onChange={(e) => setMethodId(e.target.value)}
                        disabled={gateways.length === 0}
                        className="h-10 w-full rounded-lg border bg-background px-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {gateways.length === 0 && <option value="">Cargando…</option>}
                        {gateways.map((g) => (
                            <option key={g.id} value={g.id}>
                                {g.title}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="flex gap-1.5 rounded-md border bg-muted/30 p-1.5">
                    <button
                        type="button"
                        onClick={() => onSaleTypeChange('direct')}
                        className={cn(
                            'flex-1 rounded px-2 py-1 text-xs font-medium transition',
                            saleType === 'direct'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted',
                        )}
                    >
                        Directa
                    </button>
                    <button
                        type="button"
                        onClick={() => onSaleTypeChange('subscription')}
                        className={cn(
                            'flex-1 rounded px-2 py-1 text-xs font-medium transition',
                            saleType === 'subscription'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted',
                        )}
                    >
                        Suscripción
                    </button>
                </div>

                {saleType === 'subscription' && (
                    <div className="space-y-1.5 rounded-md border bg-muted/30 p-2">
                        <input
                            type="text"
                            value={subscriptionTitle}
                            onChange={(e) => onSubscriptionTitleChange(e.target.value)}
                            placeholder="Título (ej: 1 mes de mastv)"
                            className="h-8 w-full rounded border border-input bg-background px-2 text-xs"
                        />
                        <div>
                            <label className="block text-[9px] uppercase tracking-wide text-muted-foreground">Vence</label>
                            <input
                                type="date"
                                value={subscriptionEndDate}
                                onChange={(e) => onSubscriptionEndDateChange(e.target.value)}
                                className="h-8 w-full rounded border border-input bg-background px-2 text-xs"
                            />
                        </div>
                    </div>
                )}

                <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-muted-foreground">Total</span>
                    <span className="text-2xl font-bold tabular-nums">
                        ${total.toFixed(2)}
                    </span>
                </div>

                <button
                    onClick={handleCobrar}
                    disabled={!canCheckout}
                    className="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Cobrar ${total.toFixed(2)}
                </button>

                {!customer && cart.length > 0 && (
                    <p className="text-center text-xs text-destructive">
                    </p>
                )}
            </div>
        </div>
    );
}

function PriceEditor({ price, onSave }: { price: number; onSave: (v: number) => void }) {
    const [editing, setEditing] = useState(false);
    const [value, setValue] = useState(price.toFixed(2));

    useEffect(() => {
        if (!editing) {
            setValue(price.toFixed(2));
        }
    }, [price, editing]);

    function commit() {
        const parsed = parseFloat(value);
        if (!isNaN(parsed) && parsed >= 0) {
            onSave(parsed);
        } else {
            setValue(price.toFixed(2));
        }
        setEditing(false);
    }

    if (editing) {
        return (
            <div className="mt-0.5 flex items-center gap-1">
                <span className="text-xs text-muted-foreground">$</span>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={value}
                    onChange={(e) => setValue(e.target.value)}
                    onBlur={commit}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') commit();
                        if (e.key === 'Escape') {
                            setValue(price.toFixed(2));
                            setEditing(false);
                        }
                    }}
                    autoFocus
                    className="h-6 w-20 rounded border bg-background px-1.5 text-xs tabular-nums focus:border-primary focus:outline-none"
                />
            </div>
        );
    }

    return (
        <button
            type="button"
            onClick={() => {
                setValue(price.toFixed(2));
                setEditing(true);
            }}
            className="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground"
            title="Click para editar precio"
        >
            <span className="tabular-nums">${price.toFixed(2)} c/u</span>
            <svg className="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
            </svg>
        </button>
    );
}
