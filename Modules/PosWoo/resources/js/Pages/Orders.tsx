import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { admin } from '@/routes';

type OrderItem = {
    name: string;
    quantity: number;
    price: string;
    total: string;
};

type Order = {
    id: number;
    status: string;
    total: string;
    date_created: string;
    customer_name: string;
    customer_email: string;
    items: OrderItem[];
    payment_method_title: string;
};

type Props = {
    orders: Order[];
    total: number;
    totalPages: number;
    currentPage: number;
    perPage: number;
    search: string;
    error?: string | null;
};

const STATUS_COLORS: Record<string, string> = {
    completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    processing: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'on-hold': 'bg-slate-100 text-slate-700 dark:bg-slate-900/30 dark:text-slate-400',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    refunded: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    failed: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    trash: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-900/30 dark:text-neutral-400',
};

function statusBadge(status: string) {
    const color = STATUS_COLORS[status] ?? 'bg-neutral-100 text-neutral-700';
    const labels: Record<string, string> = {
        completed: 'Completado',
        processing: 'Procesando',
        pending: 'Pendiente',
        'on-hold': 'En espera',
        cancelled: 'Cancelado',
        refunded: 'Reembolsado',
        failed: 'Falló',
        trash: 'Papelera',
    };

    return (
        <span className={`inline-block rounded-full px-2.5 py-0.5 text-[11px] font-semibold ${color}`}>
            {labels[status] ?? status}
        </span>
    );
}

function Pagination({ currentPage, totalPages, search }: { currentPage: number; totalPages: number; search: string }) {
    if (totalPages <= 1) return null;

    function go(page: number) {
        router.get('/admin/pos-woo/pedidos', { page, search }, { preserveState: true, preserveScroll: true });
    }

    const pages: (number | 'ellipsis')[] = [];
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            pages.push(i);
        } else if (pages[pages.length - 1] !== 'ellipsis') {
            pages.push('ellipsis');
        }
    }

    return (
        <div className="flex items-center justify-center gap-1 text-sm">
            <button
                disabled={currentPage <= 1}
                onClick={() => go(currentPage - 1)}
                className="rounded-lg px-3 py-1.5 text-muted-foreground transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-30"
            >
                Anterior
            </button>
            {pages.map((p, i) =>
                p === 'ellipsis' ? (
                    <span key={`e-${i}`} className="px-1 text-muted-foreground">...</span>
                ) : (
                    <button
                        key={p}
                        onClick={() => go(p)}
                        className={`min-w-[32px] rounded-lg px-2 py-1.5 font-medium tabular-nums transition-colors ${
                            p === currentPage
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted'
                        }`}
                    >
                        {p}
                    </button>
                ),
            )}
            <button
                disabled={currentPage >= totalPages}
                onClick={() => go(currentPage + 1)}
                className="rounded-lg px-3 py-1.5 text-muted-foreground transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-30"
            >
                Siguiente
            </button>
        </div>
    );
}

export default function PosOrders({ orders, total, totalPages, currentPage, perPage, search, error }: Props) {
    const [expanded, setExpanded] = useState<number | null>(null);
    const [searchInput, setSearchInput] = useState(search);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>();

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            if (searchInput !== search) {
                                router.get('/admin/pos-woo/pedidos', { search: searchInput, page: 1 }, { preserveState: true, replace: true });
            }
        }, 400);
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current); };
    }, [searchInput]);

    const from = total > 0 ? (currentPage - 1) * perPage + 1 : 0;
    const to = Math.min(currentPage * perPage, total);

    return (
        <>
            <Head title="Pos Woo - Pedidos" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex gap-2">
                    <input
                        type="text"
                        value={searchInput}
                        onChange={(e) => setSearchInput(e.target.value)}
                        placeholder="Buscar por cliente, email, teléfono o ID..."
                        autoFocus
                        className="flex h-9 w-full max-w-sm rounded-lg border bg-background px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                    />
                    {searchInput && (
                        <button
                            type="button"
                            onClick={() => {
                                setSearchInput('');
                                router.get('/admin/pos-woo/pedidos', { search: '', page: 1 }, { preserveState: true, replace: true });
                            }}
                            className="rounded-lg border px-4 py-2 text-sm font-medium transition-colors hover:bg-muted"
                        >
                            Limpiar
                        </button>
                    )}
                    {total > 0 && (
                        <p className="text-md">
                            Mostrando {from}–{to} de {total} pedido{total !== 1 ? 's' : ''}
                        </p>
                    )}
                </div>

                {error && (
                    <div className="flex items-center gap-2 rounded-lg bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        <svg className="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {error}
                    </div>
                )}
            
                {orders.length > 0 && (
                    <div className="overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                                    <th className="px-4 py-3 font-medium">ID</th>
                                    <th className="px-4 py-3 font-medium">Fecha</th>
                                    <th className="px-4 py-3 font-medium">Cliente</th>
                                    <th className="px-4 py-3 font-medium">Total</th>
                                    <th className="px-4 py-3 font-medium">Pago</th>
                                    <th className="px-4 py-3 font-medium">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.map((order) => (
                                    <tr
                                        key={order.id}
                                        className="border-b last:border-0 hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 font-medium tabular-nums">
                                            #{order.id}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground tabular-nums">
                                            {new Date(order.date_created).toLocaleDateString('es-MX', {
                                                day: '2-digit',
                                                month: '2-digit',
                                                year: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </td>
                                        <td className="px-4 py-3">
                                            {order.customer_name || (
                                                <span className="text-muted-foreground italic">Invitado</span>
                                            )}
                                            {order.customer_email && (
                                                <div className="text-xs text-muted-foreground">{order.customer_email}</div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 font-semibold tabular-nums">
                                            ${parseFloat(order.total).toFixed(2)}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {order.payment_method_title || '-'}
                                        </td>
                                        <td className="px-4 py-3">{statusBadge(order.status)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
              
                <Pagination currentPage={currentPage} totalPages={totalPages} search={search} />
            </div>
        </>
    );
}

PosOrders.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Pos Woo', href: '/admin/pos-woo' },
        { title: 'Pedidos', href: '/admin/pos-woo/pedidos' },
    ],
};