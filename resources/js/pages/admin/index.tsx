import { Head, Link } from '@inertiajs/react';
import { Inbox, MessageCircle, ShoppingCart, User as UserIcon, Users, ExternalLink, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { admin } from '@/routes';
import { countryFlag, countryName } from '@/lib/country';
import { formatMoney } from '@/lib/money';
import type { Currency } from '@/lib/currency';

type Currency = { code: string; symbol: string; decimals: number; position: string };

type Chat = {
    id: number;
    name: string;
    avatar_url: string | null;
    channel_name: string | null;
    unread_by_admin: number;
    last_message_at: string | null;
    last_message_at_diff: string | null;
    last_message_preview: string | null;
    status: string;
};

type Order = {
    id: number;
    status: string;
    total: string;
    date_created: string;
    customer_name: string;
    customer_email?: string;
    customer_phone?: string;
    user_id?: number | null;
    avatar_url?: string | null;
    currency: string;
};

type RecentUser = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    country_code: string | null;
    avatar_url: string | null;
    created_at: string | null;
    created_at_diff: string | null;
};

type RecentMessage = {
    id: number;
    conversation_id: number;
    role: string;
    type: string | null;
    content: string;
    created_at: string | null;
    created_at_diff: string | null;
    user_name: string;
    user_avatar_url: string | null;
    channel_name: string | null;
    preview: string;
};

type Props = {
    chats: { metrics: { open: number; unread: number; today: number }; recent: Chat[] };
    sales: { currency: Currency; metrics: { total: number; this_month: number; this_month_sum: number; today: number; today_sum: number; subscriptions: number }; recent: Order[]; error: string | null };
    users: { metrics: { total: number; today: number; countries: number }; by_country: Record<string, number>; recent: RecentUser[] };
    recent_messages: RecentMessage[];
};

function Avatar({ url, name, size = 8 }: { url: string | null; name: string; size?: number }) {
    const sizeClass = size === 10 ? 'size-10' : size === 12 ? 'size-12' : 'size-8';
    const initial = (name?.charAt(0) ?? '?').toUpperCase();
    if (url) {
        return <img src={url} alt="" className={`${sizeClass} shrink-0 rounded-full object-cover`} />;
    }
    return (
        <div className={`flex ${sizeClass} shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary`}>
            {initial}
        </div>
    );
}

function MetricCard({
    label,
    value,
    sub,
    icon: Icon,
    tone = 'default',
}: {
    label: string;
    value: React.ReactNode;
    sub?: string;
    icon: React.ComponentType<{ className?: string }>;
    tone?: 'default' | 'success' | 'warn' | 'info';
}) {
    const toneClass = {
        default: 'text-foreground',
        success: 'text-emerald-600 dark:text-emerald-400',
        warn: 'text-amber-600 dark:text-amber-400',
        info: 'text-blue-600 dark:text-blue-400',
    }[tone];

    return (
        <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">{label}</p>
                    <p className={`mt-2 text-3xl font-bold tabular-nums ${toneClass}`}>{value}</p>
                    {sub && <p className="mt-1 text-xs text-muted-foreground">{sub}</p>}
                </div>
                <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icon className="size-5" />
                </div>
            </div>
            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/5 dark:stroke-neutral-100/5" />
        </div>
    );
}

function SectionHeader({ title, href, icon: Icon }: { title: string; href: string; icon: React.ComponentType<{ className?: string }> }) {
    return (
        <div className="mb-3 flex items-center justify-between">
            <div className="flex items-center gap-2">
                <Icon className="size-4 text-muted-foreground" />
                <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">{title}</h2>
            </div>
            <Button asChild variant="ghost" size="sm" className="gap-1 text-xs">
                <Link href={href}>
                    Ver todos
                    <ExternalLink className="size-3" />
                </Link>
            </Button>
        </div>
    );
}

function EmptyState({ icon: Icon, message }: { icon: React.ComponentType<{ className?: string }>; message: string }) {
    return (
        <div className="flex flex-col items-center gap-2 py-8 text-center text-muted-foreground">
            <Icon className="size-8 text-muted-foreground/40" />
            <p className="text-sm">{message}</p>
        </div>
    );
}

export default function AdminDashboard({ chats, sales, users, recent_messages }: Props) {
    return (
        <>
            <Head title="Admin" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-y-auto rounded-xl p-4">
                <div className="grid gap-4 md:grid-cols-3">
                    <MetricCard
                        label="Chats"
                        value={chats.metrics.open}
                        sub={`${chats.metrics.unread} sin leer · ${chats.metrics.today} hoy`}
                        icon={MessageCircle}
                        tone={chats.metrics.unread > 0 ? 'warn' : 'default'}
                    />
                    <MetricCard
                        label="Ventas (mes)"
                        value={formatMoney(sales.metrics.this_month_sum, sales.currency)}
                        sub={`${sales.metrics.this_month} pedidos este mes · ${sales.metrics.today} hoy · ${sales.metrics.total} total histórico`}
                        icon={ShoppingCart}
                        tone="success"
                    />
                    <MetricCard
                        label="Usuarios"
                        value={users.metrics.total}
                        sub={`${users.metrics.today} hoy · ${users.metrics.countries} países`}
                        icon={Users}
                        tone="info"
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <SectionHeader title="Chats recientes" href="/admin/chats" icon={MessageCircle} />
                        {chats.recent.length === 0 ? (
                            <EmptyState icon={Inbox} message="Sin conversaciones" />
                        ) : (
                            <ul className="divide-y">
                                {chats.recent.map((c) => (
                                    <li key={c.id} className="flex items-center gap-3 py-2.5">
                                        <Avatar url={c.avatar_url} name={c.name} />
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <Link href={`/admin/chats/${c.id}`} className="truncate text-sm font-medium hover:underline">
                                                    {c.name}
                                                </Link>
                                                {c.unread_by_admin > 0 && (
                                                    <span className="shrink-0 rounded-full bg-primary px-1.5 text-[10px] font-semibold text-primary-foreground">
                                                        {c.unread_by_admin}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {c.last_message_preview ?? '—'}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-right">
                                            {c.channel_name && (
                                                <p className="text-[10px] text-muted-foreground">{c.channel_name}</p>
                                            )}
                                            <p className="text-[10px] text-muted-foreground">{c.last_message_at_diff}</p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <SectionHeader title="Ventas recientes" href="/admin/pos-woo/pedidos" icon={ShoppingCart} />
                        {sales.recent.length === 0 ? (
                            <EmptyState icon={ShoppingCart} message="Sin pedidos" />
                        ) : (
                            <ul className="divide-y">
                                {sales.recent.map((o) => (
                                    <li key={o.id} className="flex items-center gap-3 py-2.5">
                                        <Avatar url={o.avatar_url ?? null} name={o.customer_name || o.customer_email || o.customer_phone || `#${o.id}`} />
                                        <div className="min-w-0 flex-1">
                                            {o.user_id ? (
                                                <Link href={`/admin/usuarios/${o.user_id}/editar`} className="text-sm font-medium hover:underline">
                                                    {o.customer_name || o.customer_email || o.customer_phone}
                                                </Link>
                                            ) : (
                                                <Link href={`/admin/pos-woo/pedidos/${o.id}`} className="text-sm font-medium hover:underline">
                                                    {o.customer_name || o.customer_email || o.customer_phone}
                                                </Link>
                                            )}
                                            <p className="truncate text-[10px] text-muted-foreground">
                                                #{o.id}
                                                {o.customer_phone ? ` · ${o.customer_phone}` : ''}
                                                {` · ${new Date(o.date_created).toLocaleString('es-MX', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}`}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-right">
                                            <p className="text-sm font-semibold tabular-nums">{formatMoney(o.total, sales.currency, o.currency)}</p>
                                            <p className="text-[10px] capitalize text-muted-foreground">{o.status}</p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {sales.error && (
                            <p className="mt-2 text-xs text-destructive">No se pudo cargar el módulo de ventas.</p>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <SectionHeader title="Usuarios recientes" href="/admin/usuarios" icon={UserIcon} />
                    {users.recent.length === 0 ? (
                        <EmptyState icon={Users} message="Sin usuarios" />
                    ) : (
                        <div className="grid gap-4 lg:grid-cols-[1fr_280px]">
                            <ul className="divide-y">
                                {users.recent.map((u) => (
                                    <li key={u.id} className="flex items-center gap-3 py-2.5">
                                        <Avatar url={u.avatar_url} name={u.name} />
                                        <div className="min-w-0 flex-1">
                                            <Link href={`/admin/usuarios/${u.id}/editar`} className="text-sm font-medium hover:underline">
                                                {u.name || '(sin nombre)'}
                                            </Link>
                                            <p className="truncate text-xs text-muted-foreground">{u.email}</p>
                                        </div>
                                        <div className="shrink-0 text-right">
                                            {u.country_code && (
                                                <p className="text-sm" title={countryName(u.country_code)}>
                                                    {countryFlag(u.country_code)} <span className="text-[10px] text-muted-foreground">{u.country_code}</span>
                                                </p>
                                            )}
                                            <p className="text-[10px] text-muted-foreground">{u.created_at_diff}</p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                            <div>
                                <h3 className="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Por país</h3>
                                {Object.keys(users.by_country).length === 0 ? (
                                    <p className="text-xs text-muted-foreground">Sin datos de país.</p>
                                ) : (
                                    <ul className="space-y-1.5">
                                        {Object.entries(users.by_country).map(([code, count]) => {
                                            const total = Object.values(users.by_country).reduce((s, v) => s + v, 0);
                                            const pct = total > 0 ? (count / total) * 100 : 0;
                                            return (
                                                <li key={code} className="text-xs">
                                                    <div className="mb-1 flex items-center justify-between">
                                                        <span>
                                                            {countryFlag(code)} {countryName(code)} <span className="text-muted-foreground">({code})</span>
                                                        </span>
                                                        <span className="tabular-nums font-medium">{count}</span>
                                                    </div>
                                                    <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                                        <div className="h-full bg-primary" style={{ width: `${pct}%` }} />
                                                    </div>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <div className="flex items-center gap-2">
                            <Search className="size-4 text-muted-foreground" />
                            <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Últimos 5 mensajes</h2>
                        </div>
                        <Button asChild variant="ghost" size="sm" className="gap-1 text-xs">
                            <Link href="/admin/chats">
                                Ver todos
                                <ExternalLink className="size-3" />
                            </Link>
                        </Button>
                    </div>
                    {recent_messages.length === 0 ? (
                        <EmptyState icon={MessageCircle} message="Aún no hay mensajes" />
                    ) : (
                        <ul className="divide-y">
                            {recent_messages.map((m) => {
                                const isAdmin = m.role === 'admin' || m.role === 'bot' || m.role === 'system';
                                return (
                                    <li key={m.id} className="flex items-start gap-3 px-4 py-3">
                                        <Avatar url={m.user_avatar_url} name={m.user_name} />
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-baseline gap-2">
                                                <Link href={`/admin/chats/${m.conversation_id}`} className="text-sm font-medium hover:underline">
                                                    {m.user_name}
                                                </Link>
                                                {m.channel_name && (
                                                    <span className="text-[10px] text-muted-foreground">· {m.channel_name}</span>
                                                )}
                                                <span className="text-[10px] text-muted-foreground">· {m.created_at_diff}</span>
                                            </div>
                                            <p className="mt-0.5 line-clamp-2 text-sm text-muted-foreground">
                                                <span className={`mr-1.5 inline-block rounded px-1 py-0.5 text-[10px] font-medium ${isAdmin ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-muted text-foreground'}`}>
                                                    {m.role}
                                                </span>
                                                {m.preview}
                                            </p>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Admin',
            href: admin(),
        },
    ],
};
