import { Head, router } from '@inertiajs/react';
import { MessageCircle, Pencil, ChevronDown, ChevronRight, Search, X } from 'lucide-react';
import { useState } from 'react';
import { DataTableToolbar, type ToolbarFilter } from '@/components/data-table-toolbar';
import { TablePagination } from '@/components/table-pagination';
import { Button } from '@/components/ui/button';
import { useTableSearch } from '@/hooks/use-table-search';
import { admin } from '@/routes';
import { openPosWooChat } from '@/lib/pos-woo-chat';

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
    customer_phone?: string | null;
    items: OrderItem[];
    payment_method_title: string;
    user_id?: number | null;
    avatar_url?: string | null;
    chat_conversation_id?: number | null;
};

type Props = {
    initialOrders?: Order[];
    initialTotal?: number;
    initialTotalPages?: number;
    initialCurrentPage?: number;
    initialPerPage?: number;
    initialSearch?: string;
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

const STATUS_LABELS: Record<string, string> = {
    completed: 'Completado',
    processing: 'Procesando',
    pending: 'Pendiente',
    'on-hold': 'En espera',
    cancelled: 'Cancelado',
    refunded: 'Reembolsado',
    failed: 'Falló',
    trash: 'Papelera',
};

function statusBadge(status: string) {
    const color = STATUS_COLORS[status] ?? 'bg-neutral-100 text-neutral-700';
    return (
        <span className={`inline-block rounded-full px-2.5 py-0.5 text-[11px] font-semibold ${color}`}>
            {STATUS_LABELS[status] ?? status}
        </span>
    );
}

export default function PosOrders({ initialOrders, initialTotal, initialTotalPages, initialCurrentPage, initialPerPage, initialSearch, error: serverError }: Props) {
    const [expanded, setExpanded] = useState<number | null>(null);
    const initialData = initialOrders ? { data: initialOrders, total: initialTotal, current_page: initialCurrentPage, last_page: initialTotalPages } : undefined;

    const table = useTableSearch<Order>({
        endpoint: '/admin/pos-woo/orders',
        initialData,
        perPage: initialPerPage ?? 10,
        initialFilters: { search: initialSearch ?? '', status: '', type: '' },
    });

    const statusFilters: ToolbarFilter[] = [
        {
            key: 'status',
            label: 'Estado',
            value: table.filters.status ?? '',
            onChange: (v) => table.setFilter('status', v),
            placeholder: 'Todos los estados',
            options: Object.entries(STATUS_LABELS).map(([value, label]) => ({ value, label })),
        },
    ];

    return (
        <>
            <Head title="Pos Woo - Pedidos" />

            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4">
                <DataTableToolbar
                    search={table.search}
                    onSearchChange={table.setSearch}
                    searchPlaceholder="Buscar por cliente, email, teléfono o ID..."
                    loading={table.loading}
                    total={table.total}
                    totalLabel={`pedido${table.total !== 1 ? 's' : ''}`}
                    filters={statusFilters}
                />

                {table.data.length > 0 && (
                    <div className="rounded-xl border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                                    <th className="px-4 py-3 font-medium">Orden</th>
                                    <th className="px-4 py-3 font-medium">Cliente</th>
                                    <th className="px-4 py-3 font-medium">Productos</th>
                                    <th className="px-4 py-3 font-medium text-right">Total</th>
                                    <th className="px-4 py-3 font-medium">Pago</th>
                                    <th className="px-4 py-3 font-medium">Estado</th>
                                    <th className="px-4 py-3 font-medium text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {table.data.map((order) => (
                                    <tr key={order.id} className="border-b last:border-0 hover:bg-muted/30">
                                        <td className="px-4 py-3">
                                            <div className="font-medium tabular-nums">#{order.id}</div>
                                            <div className="text-xs text-muted-foreground tabular-nums">
                                                {new Date(order.date_created).toLocaleDateString('es-MX', {
                                                    day: '2-digit', month: '2-digit', year: 'numeric',
                                                })}
                                            </div>
                                            <div className="text-[10px] text-muted-foreground tabular-nums">
                                                {new Date(order.date_created).toLocaleTimeString('es-MX', {
                                                    hour: '2-digit', minute: '2-digit',
                                                })}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                {order.avatar_url ? (
                                                    <img src={order.avatar_url} alt="" className="size-8 shrink-0 rounded-full object-cover" />
                                                ) : (
                                                    <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                                        {(order.customer_name?.charAt(0) ?? '?').toUpperCase()}
                                                    </div>
                                                )}
                                                <div className="min-w-0">
                                                    {order.user_id ? (
                                                        <a
                                                            href={`/admin/usuarios/${order.user_id}/editar`}
                                                            className="font-medium hover:underline"
                                                        >
                                                            {order.customer_name || <span className="italic text-muted-foreground">Invitado</span>}
                                                        </a>
                                                    ) : (
                                                        <div className="font-medium">{order.customer_name || <span className="italic text-muted-foreground">Invitado</span>}</div>
                                                    )}
                                                    {order.customer_email && (
                                                        <div className="truncate max-w-[160px] text-xs text-muted-foreground">{order.customer_email}</div>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <button
                                                type="button"
                                                onClick={() => setExpanded(expanded === order.id ? null : order.id)}
                                                className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                                            >
                                                {expanded === order.id ? <ChevronDown className="size-3" /> : <ChevronRight className="size-3" />}
                                                {order.items.length} artículo{order.items.length !== 1 ? 's' : ''}
                                            </button>
                                            {expanded === order.id && (
                                                <div className="mt-1 space-y-0.5">
                                                    {order.items.map((item, i) => (
                                                        <div key={i} className="flex items-center justify-between gap-2 text-xs">
                                                            <span className="truncate max-w-[160px]">{item.name}</span>
                                                            <span className="shrink-0 text-muted-foreground tabular-nums">x{item.quantity} @ ${parseFloat(item.price).toFixed(2)}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right font-semibold tabular-nums">
                                            ${parseFloat(order.total).toFixed(2)}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {order.payment_method_title || '-'}
                                        </td>
                                        <td className="px-4 py-3">{statusBadge(order.status)}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => router.visit(`/admin/pos-woo/pedidos/${order.id}`)}
                                                title="Editar metadatos"
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            {(() => {
                                                const phone = (order.customer_phone ?? '').trim();
                                                if (!phone && !order.chat_conversation_id) {
                                                    return null;
                                                }
                                                return (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            openPosWooChat(
                                                                order.chat_conversation_id ?? null,
                                                                phone || null,
                                                                order.customer_name || 'Cliente',
                                                            )
                                                        }
                                                        title="Enviar mensaje"
                                                    >
                                                        <MessageCircle className="size-4" />
                                                    </Button>
                                                );
                                            })()}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {!table.loading && table.data.length === 0 && !serverError && (
                    <div className="flex flex-col items-center gap-2 py-20 text-center text-muted-foreground">
                        <Search className="size-10 text-muted-foreground/40" />
                        <p className="text-sm">
                            {table.search ? 'Sin resultados para la búsqueda' : 'Aún no hay pedidos'}
                        </p>
                    </div>
                )}

                {serverError && (
                    <div className="flex items-center gap-2 rounded-lg bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        <X className="size-4 shrink-0" />
                        {serverError}
                    </div>
                )}

                <TablePagination
                    currentPage={table.currentPage}
                    lastPage={table.lastPage}
                    onPageChange={table.goPage}
                    total={table.total}
                    perPage={initialPerPage ?? 10}
                    itemLabel={`pedido${table.total !== 1 ? 's' : ''}`}
                />
            </div>
        </>
    );
}

PosOrders.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'PosWoo', href: '/admin/pos-woo' },
        { title: 'Pedidos', href: '/admin/pos-woo/pedidos' },
    ],
};
