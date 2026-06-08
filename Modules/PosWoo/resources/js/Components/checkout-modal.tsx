import { useState } from 'react';
import type { CartItem, Customer } from './types';

type Props = {
    cart: CartItem[];
    total: number;
    customer: Customer | null;
    onClose: () => void;
    onConfirm: (paymentMethod: string) => void;
};

export function CheckoutModal({ cart, total, customer, onClose, onConfirm }: Props) {
    const [method, setMethod] = useState<'cash' | 'card'>('cash');
    const [received, setReceived] = useState('');
    const [confirmed, setConfirmed] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    const receivedAmount = parseFloat(received) || 0;
    const change = receivedAmount - total;

    async function handleConfirm() {
        setSubmitting(true);
        try {
            await onConfirm(method);
            setConfirmed(true);
            setTimeout(() => {
                setConfirmed(false);
                setReceived('');
            }, 1500);
        } catch {
            // error is handled by parent
        } finally {
            setSubmitting(false);
        }
    }

    if (confirmed) {
        return (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div className="w-full max-w-sm rounded-xl bg-background p-8 text-center shadow-2xl">
                    <div className="mx-auto mb-4 flex size-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                        <svg className="size-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h3 className="mb-2 text-xl font-bold">Venta completada</h3>
                    <p className="text-sm text-muted-foreground">
                        Total: ${total.toFixed(2)} — {method === 'cash' ? 'Efectivo' : 'Tarjeta'}
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="w-full max-w-lg rounded-xl bg-background shadow-2xl">
                <div className="flex items-center justify-between border-b px-6 py-4">
                    <h3 className="text-lg font-semibold">Confirmar venta</h3>
                    <button
                        onClick={onClose}
                        className="rounded-lg p-1 text-muted-foreground transition-colors hover:bg-muted"
                    >
                        <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="max-h-60 overflow-y-auto px-6 py-4">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-xs uppercase text-muted-foreground">
                                <th className="pb-2 font-medium">Producto</th>
                                <th className="pb-2 text-right font-medium">Cant</th>
                                <th className="pb-2 text-right font-medium">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            {cart.map((item) => (
                                <tr key={item.cartKey} className="border-b border-muted/50">
                                    <td className="py-2">{item.label}</td>
                                    <td className="py-2 text-right tabular-nums">{item.quantity}</td>
                                    <td className="py-2 text-right font-medium tabular-nums">
                                        ${item.subtotal.toFixed(2)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="border-t px-6 py-3">
                    {customer && (
                        <div className="mb-3 flex items-center gap-2 text-sm text-muted-foreground">
                            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            {customer.name} — {customer.email}
                        </div>
                    )}
                    <div className="flex items-center justify-between text-lg font-bold">
                        <span>Total</span>
                        <span className="tabular-nums">${total.toFixed(2)}</span>
                    </div>
                </div>

                <div className="border-t px-6 py-4">
                    <p className="mb-3 text-sm font-medium text-muted-foreground">Método de pago</p>
                    <div className="mb-4 flex gap-2">
                        <button
                            onClick={() => setMethod('cash')}
                            className={`flex flex-1 items-center justify-center gap-2 rounded-lg border px-4 py-3 text-sm font-medium transition-all ${
                                method === 'cash'
                                    ? 'border-primary bg-primary/5 text-primary'
                                    : 'border-muted-foreground/20 text-muted-foreground hover:border-muted-foreground/40'
                            }`}
                        >
                            <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Efectivo
                        </button>
                        <button
                            onClick={() => setMethod('card')}
                            className={`flex flex-1 items-center justify-center gap-2 rounded-lg border px-4 py-3 text-sm font-medium transition-all ${
                                method === 'card'
                                    ? 'border-primary bg-primary/5 text-primary'
                                    : 'border-muted-foreground/20 text-muted-foreground hover:border-muted-foreground/40'
                            }`}
                        >
                            <svg className="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                            Tarjeta
                        </button>
                    </div>

                    {method === 'cash' && (
                        <div className="mb-4 rounded-lg bg-muted/50 p-3">
                            <label className="mb-1 block text-xs font-medium text-muted-foreground">
                                Monto recibido
                            </label>
                            <div className="relative">
                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">
                                    $
                                </span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={received}
                                    onChange={(e) => setReceived(e.target.value)}
                                    placeholder="0.00"
                                    className="h-10 w-full rounded-lg border bg-background pl-7 pr-3 text-lg font-bold tabular-nums focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                    autoFocus
                                />
                            </div>
                            {receivedAmount >= total && (
                                <p className="mt-1 text-right text-sm text-emerald-600">
                                    Cambio: ${change.toFixed(2)}
                                </p>
                            )}
                            {receivedAmount > 0 && receivedAmount < total && (
                                <p className="mt-1 text-right text-xs text-destructive">
                                    Faltan ${(total - receivedAmount).toFixed(2)}
                                </p>
                            )}
                        </div>
                    )}

                    <div className="flex gap-2">
                        <button
                            onClick={onClose}
                            className="flex-1 rounded-lg border px-4 py-2.5 text-sm font-medium transition-colors hover:bg-muted"
                        >
                            Cancelar
                        </button>
                        <button
                            onClick={handleConfirm}
                            disabled={
                                submitting ||
                                (method === 'cash' ? receivedAmount < total : false)
                            }
                            className="flex-1 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {submitting ? 'Procesando...' : 'Confirmar venta'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
