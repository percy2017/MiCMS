import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Globe, MessageCircle, Plus, Wifi, WifiOff } from 'lucide-react';
import { DataTableToolbar, type ToolbarFilter } from '@/components/data-table-toolbar';
import { Card, CardTitle } from '@/components/ui/card';
import { useClientTableSearch } from '@/hooks/use-client-table-search';
import { admin } from '@/routes';

type ChannelItem = {
    id: number;
    type: string;
    name: string;
    enabled: boolean;
    sort: number;
    url: string;
    instance_name?: string | null;
    instance_id?: string | null;
    profile_name?: string | null;
    profile_picture_url?: string | null;
    owner_jid?: string | null;
    connection_status?: string;
    widget_title?: string | null;
};

type PageProps = { channels: ChannelItem[] };

const CHANNEL_TYPES: { type: string; icon: typeof Globe; color: string; name: string; description: string }[] = [
    { type: 'web_widget', icon: Globe, color: '#2563eb', name: 'Widget Web', description: 'Chat widget integrado en el sitio web' },
    { type: 'evolution', icon: MessageCircle, color: '#25D366', name: 'WhatsApp', description: 'WhatsApp mediante Evolution API' },
];

const CHANNEL_META = Object.fromEntries(CHANNEL_TYPES.map((c) => [c.type, c]));

function initials(name: string): string {
    return name
        .split(' ')
        .map((w) => w.charAt(0))
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

function formatPhone(jid: string): string {
    const phone = jid.replace(/@.+$/, '');
    return `+${phone}`;
}

function connectionLabel(status: string): string {
    switch (status) {
        case 'open':
            return 'Conectado';
        case 'connecting':
            return 'Conectando';
        case 'close':
            return 'Desconectado';
        default:
            return status;
    }
}

export default function CanalesIndex({ channels }: PageProps) {
    const table = useClientTableSearch<ChannelItem>({
        initialData: channels,
        searchFields: ['name', 'profile_name', 'instance_name', 'widget_title', 'owner_jid'],
        perPage: 50,
        initialFilters: { type: '', enabled: '' },
    });

    const filters: ToolbarFilter[] = [
        {
            key: 'type',
            label: 'Tipo',
            value: table.filters.type ?? '',
            onChange: (v) => table.setFilter('type', v),
            placeholder: 'Todos los tipos',
            options: [
                { value: 'evolution', label: 'WhatsApp' },
                { value: 'web_widget', label: 'Widget Web' },
            ],
        },
        {
            key: 'enabled',
            label: 'Estado',
            value: table.filters.enabled ?? '',
            onChange: (v) => table.setFilter('enabled', v),
            placeholder: 'Todos',
            options: [
                { value: 'true', label: 'Activos' },
                { value: 'false', label: 'Inactivos' },
            ],
        },
    ];

    function createChannel(type: string): void {
        const url = type === 'web_widget' ? '/admin/canales/web-widget' : `/admin/canales/${type}`;
        router.post(url);
    }

    return (
        <>
            <Head title="Canales" />
            <div className="space-y-4 p-4">
                <DataTableToolbar
                    search={table.search}
                    onSearchChange={table.setSearch}
                    searchPlaceholder="Buscar canal..."
                    total={table.total}
                    totalLabel={`canal${table.total !== 1 ? 'es' : ''}`}
                    filters={filters}
                />

                <div>
                    <h2 className="mb-3 text-base font-medium">Añadir canal</h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {CHANNEL_TYPES.map((a) => {
                            const Icon = a.icon;
                            return (
                                <button
                                    key={a.type}
                                    type="button"
                                    onClick={() => createChannel(a.type)}
                                    className="group block w-full text-left"
                                >
                                    <Card className="flex min-h-[80px] cursor-pointer flex-row items-center gap-3 border-2 border-dashed border-muted-300 bg-muted/10 px-4 text-center transition hover:border-primary hover:bg-primary/5 hover:shadow-sm">
                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-muted-300 text-muted-400 transition group-hover:border-primary group-hover:text-primary">
                                            <Plus className="size-4" />
                                        </div>
                                        <div className="text-left">
                                            <CardTitle className="text-sm">{a.name}</CardTitle>
                                            <p className="text-xs text-muted-foreground">{a.description}</p>
                                        </div>
                                    </Card>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {channels.length > 0 && (
                    <div>
                        <h2 className="mb-3 text-base font-medium">Canales configurados</h2>
                        {table.data.length === 0 ? (
                            <div className="rounded-lg border border-dashed bg-muted/10 px-6 py-12 text-center">
                                <p className="text-sm text-muted-foreground">Sin resultados para la búsqueda.</p>
                            </div>
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {table.data.map((c) => {
                                    const meta = CHANNEL_META[c.type];

                                    if (c.type === 'evolution') {
                                        return (
                                            <Link key={c.id} href={c.url} className="group block">
                                                <Card className="relative overflow-hidden border-l-4 transition hover:shadow-md" style={{ borderLeftColor: '#25D366' }}>
                                                    <div className="flex items-start gap-4 p-5">
                                                        {c.profile_picture_url ? (
                                                            <img src={c.profile_picture_url} alt="" className="size-14 shrink-0 rounded-full object-cover ring-2 ring-muted" />
                                                        ) : (
                                                            <div className="flex size-14 shrink-0 items-center justify-center rounded-full bg-[#25D366]/10 text-base font-semibold text-[#25D366] ring-2 ring-muted">
                                                                {c.profile_name ? initials(c.profile_name) : '?'}
                                                            </div>
                                                        )}

                                                        <div className="min-w-0 flex-1">
                                                            <div className="flex items-center justify-between gap-2">
                                                                <CardTitle className="truncate text-base">
                                                                    {c.profile_name || c.name}
                                                                </CardTitle>
                                                                <ArrowRight className="size-4 shrink-0 text-muted-foreground opacity-0 transition group-hover:opacity-100" />
                                                            </div>

                                                            {c.owner_jid && (
                                                                <p className="truncate text-sm text-muted-foreground">
                                                                    {formatPhone(c.owner_jid)}
                                                                </p>
                                                            )}

                                                            {c.instance_name && (
                                                                <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                                    Instancia: {c.instance_name}
                                                                </p>
                                                            )}
                                                            {c.instance_id && (
                                                                <p className="truncate text-xs text-muted-foreground/60">
                                                                    ID: {c.instance_id}
                                                                </p>
                                                            )}

                                                            <div className="mt-2 flex items-center gap-2">
                                                                <span
                                                                    className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${
                                                                        c.connection_status === 'open'
                                                                            ? 'bg-green-100 text-green-700'
                                                                            : c.connection_status === 'connecting'
                                                                                ? 'bg-yellow-100 text-yellow-700'
                                                                                : 'bg-muted text-muted-foreground'
                                                                    }`}
                                                                >
                                                                    {c.connection_status === 'open' ? (
                                                                        <Wifi className="size-3" />
                                                                    ) : (
                                                                        <WifiOff className="size-3" />
                                                                    )}
                                                                    {connectionLabel(c.connection_status ?? 'unknown')}
                                                                </span>

                                                                {!c.enabled && (
                                                                    <span className="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                                                        Inactivo
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </Card>
                                            </Link>
                                        );
                                    }

                                    return (
                                        <Link key={c.id} href={c.url} className="group block">
                                            <Card className="relative overflow-hidden border-l-4 transition hover:shadow-md" style={{ borderLeftColor: meta?.color ?? '#666' }}>
                                                <div className="flex items-start gap-4 p-5">
                                                    <div
                                                        className="flex size-14 shrink-0 items-center justify-center rounded-lg"
                                                        style={{ backgroundColor: `${meta?.color ?? '#666'}15`, color: meta?.color ?? '#666' }}
                                                    >
                                                        {meta ? <meta.icon className="size-6" /> : <Globe className="size-6" />}
                                                    </div>

                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-center justify-between gap-2">
                                                            <CardTitle className="truncate text-base">{c.name}</CardTitle>
                                                            <ArrowRight className="size-4 shrink-0 text-muted-foreground opacity-0 transition group-hover:opacity-100" />
                                                        </div>

                                                        {c.widget_title && (
                                                            <p className="truncate text-sm text-muted-foreground">
                                                                {c.widget_title}
                                                            </p>
                                                        )}

                                                        <div className="mt-2">
                                                            <span
                                                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                                    c.enabled
                                                                        ? 'bg-green-100 text-green-700'
                                                                        : 'bg-muted text-muted-foreground'
                                                                }`}
                                                            >
                                                                {c.enabled ? 'Activo' : 'Inactivo'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </Card>
                                        </Link>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                )}

                {channels.length === 0 && (
                    <div className="rounded-lg border border-dashed bg-muted/10 px-6 py-12 text-center">
                        <MessageCircle className="mx-auto mb-3 size-10 text-muted-foreground/50" />
                        <p className="text-base font-medium text-muted-foreground">No hay canales configurados</p>
                        <p className="mt-1 text-sm text-muted-foreground/70">
                            Crea un canal usando las opciones de arriba para empezar a recibir mensajes.
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}

CanalesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Canales', href: '/admin/canales' },
    ],
};
