import { AlertCircle, ExternalLink, Receipt, RefreshCw, ShoppingCart } from 'lucide-react';
import { usePosWooOrdersByPhone } from '../Hooks/use-poswoo-orders-by-phone';
import { cn } from '@/lib/utils';

type Props = {
    phone: string | null;
};

const STATUS_MAP: Record<string, { label: string; className: string }> = {
    completed: { label: 'Completado', className: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' },
    processing: { label: 'En proceso', className: 'bg-blue-500/10 text-blue-600 dark:text-blue-400' },
    pending: { label: 'Pendiente', className: 'bg-yellow-500/10 text-yellow-600 dark:text-yellow-400' },
    cancelled: { label: 'Cancelado', className: 'bg-red-500/10 text-red-600 dark:text-red-400' },
    refunded: { label: 'Reembolsado', className: 'bg-orange-500/10 text-orange-600 dark:text-orange-400' },
    on_hold: { label: 'En espera', className: 'bg-muted text-muted-foreground' },
    failed: { label: 'Fallido', className: 'bg-red-500/10 text-red-600 dark:text-red-400' },
};

function formatMoney(amount: string | number): string {
    const n = typeof amount === 'string' ? parseFloat(amount) : amount;
    if (isNaN(n)) {
        return String(amount);
    }

    return new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(n);
}

function formatDate(iso: string): string {
    if (!iso) {
        return '';
    }
    const d = new Date(iso);

    return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit', year: '2-digit' });
}

export default function PosWooSalesPanel({ phone }: Props) {
    const { orders, total, loading, error, refetch } = usePosWooOrdersByPhone(phone);

    if (!phone) {
        return (
            <div className="border-b px-4 py-3">
                <p className="mb-1 px-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                    Ventas PosWoo
                </p>
                <div className="rounded-md border border-dashed p-3 text-center text-xs text-muted-foreground">
                    <ShoppingCart className="mx-auto mb-1 size-4 text-muted-foreground/50" />
                    <p>Sin teléfono registrado para este cliente</p>
                </div>
            </div>
        );
    }

    const totalSum = orders.reduce((acc, o) => acc + (parseFloat(o.total) || 0), 0);

    return (
        <div className="border-b px-4 py-3">
            <div className="mb-2 flex items-center justify-between px-1">
                <p className="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                    Ventas PosWoo
                </p>
                <button
                    type="button"
                    onClick={refetch}
                    disabled={loading}
                    className="flex items-center gap-1 rounded p-1 text-[10px] text-muted-foreground transition hover:bg-muted hover:text-foreground disabled:opacity-50"
                    title="Reintentar"
                >
                    <RefreshCw className={cn('size-3', loading && 'animate-spin')} />
                    Refrescar
                </button>
            </div>

            {loading && orders.length === 0 ? (
                <div className="space-y-2">
                    {[1, 2, 3].map((i) => (
                        <div key={i} className="h-14 animate-pulse rounded-md border bg-muted/30" />
                    ))}
                </div>
            ) : error ? (
                <div className="rounded-md border border-destructive/20 bg-destructive/5 p-3 text-center">
                    <AlertCircle className="mx-auto mb-1 size-4 text-destructive" />
                    <p className="text-xs text-destructive">No se pudieron cargar las ventas</p>
                    <button
                        type="button"
                        onClick={refetch}
                        className="mt-2 text-[10px] text-primary hover:underline"
                    >
                        Reintentar
                    </button>
                </div>
            ) : orders.length === 0 ? (
                <div className="rounded-md border border-dashed p-3 text-center text-xs text-muted-foreground">
                    <Receipt className="mx-auto mb-1 size-4 text-muted-foreground/50" />
                    <p>Aún no hay ventas para este cliente</p>
                </div>
            ) : (
                <>
                    <div className="mb-2 flex items-center justify-between rounded-md bg-primary/5 px-2 py-1.5 text-xs">
                        <span className="text-muted-foreground">
                            {orders.length} {orders.length === 1 ? 'venta' : 'ventas'} (últimas 3)
                        </span>
                        <span className="font-semibold text-primary">
                            {formatMoney(totalSum.toString())}
                        </span>
                    </div>
                    <div className="space-y-1.5">
                        {orders.map((order) => {
                            const status = STATUS_MAP[order.status] ?? { label: order.status, className: 'bg-muted text-muted-foreground' };

                            return (
                                <a
                                    key={order.id}
                                    href={`/admin/pos-woo/pedidos/${order.id}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-start gap-2 rounded-md border px-2 py-2 text-xs transition hover:bg-muted/50"
                                >
                                    <Receipt className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-1.5">
                                            <span className="font-medium">#{order.id}</span>
                                            <span
                                                className={cn(
                                                    'rounded-full px-1.5 py-0.5 text-[9px] font-medium',
                                                    status.className,
                                                )}
                                            >
                                                {status.label}
                                            </span>
                                        </div>
                                        <p className="mt-0.5 text-[10px] text-muted-foreground">
                                            {formatDate(order.date_created)}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 flex-col items-end gap-0.5">
                                        <span className="text-sm font-semibold">
                                            {formatMoney(order.total)}
                                        </span>
                                        <ExternalLink className="size-3 text-muted-foreground" />
                                    </div>
                                </a>
                            );
                        })}
                    </div>
                    {total > orders.length && (
                        <p className="mt-2 text-center text-[10px] text-muted-foreground">
                            Mostrando {orders.length} de {total} ventas totales
                        </p>
                    )}
                </>
            )}
        </div>
    );
}
