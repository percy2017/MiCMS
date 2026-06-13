import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Globe, Plus } from 'lucide-react';
import { DataTableToolbar, type ToolbarFilter } from '@/components/data-table-toolbar';
import { Card, CardTitle } from '@/components/ui/card';
import { useClientTableSearch } from '@/hooks/use-client-table-search';
import { admin } from '@/routes';

type Widget = {
    id: number;
    name: string;
    enabled: boolean;
    title: string;
    public_key: string | null;
    allowed_domains: string[];
    conversations_count: number;
};

type PageProps = { widgets: Widget[] };

export default function WidgetIndex({ widgets }: PageProps) {
    const table = useClientTableSearch<Widget>({
        initialData: widgets,
        searchFields: ['name', 'title', 'public_key'],
        perPage: 50,
        initialFilters: { enabled: '' },
    });

    const filters: ToolbarFilter[] = [
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

    return (
        <>
            <Head title="Widgets Web" />
            <div className="h-full min-h-0 space-y-4 overflow-y-auto p-4">
                <DataTableToolbar
                    search={table.search}
                    onSearchChange={table.setSearch}
                    searchPlaceholder="Buscar widget..."
                    total={table.total}
                    totalLabel={`widget${table.total !== 1 ? 's' : ''}`}
                    filters={filters}
                />

                <div>
                    <h2 className="mb-3 text-base font-medium">Añadir widget</h2>
                    <button
                        type="button"
                        onClick={() => router.visit('/admin/canales/web-widget/nuevo')}
                        className="group block w-full text-left"
                    >
                        <Card className="flex min-h-[80px] cursor-pointer flex-row items-center gap-3 border-2 border-dashed border-[#2563eb]/40 bg-[#2563eb]/5 px-4 transition hover:border-[#2563eb] hover:bg-[#2563eb]/10 hover:shadow-sm">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-[#2563eb]/60 text-[#2563eb]">
                                <Plus className="size-4" />
                            </div>
                            <div className="text-left">
                                <div className="flex items-center gap-2">
                                    <Globe className="size-4 text-[#2563eb]" />
                                    <CardTitle className="text-sm">Nuevo Widget Web</CardTitle>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Crea un inbox de chat embebido para un dominio específico
                                </p>
                            </div>
                        </Card>
                    </button>
                </div>

                {widgets.length > 0 && (
                    <div>
                        <h2 className="mb-3 text-base font-medium">Widgets configurados</h2>
                        {table.data.length === 0 ? (
                            <div className="rounded-lg border border-dashed bg-muted/10 px-6 py-12 text-center">
                                <p className="text-sm text-muted-foreground">Sin resultados para la búsqueda.</p>
                            </div>
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {table.data.map((w) => (
                                    <Link
                                        key={w.id}
                                        href={`/admin/canales/web-widget/${w.id}`}
                                        className="group block"
                                    >
                                        <Card className="relative overflow-hidden border-l-4 border-[#2563eb] transition hover:shadow-md">
                                            <div className="flex items-start gap-4 p-5">
                                                <div className="flex size-14 shrink-0 items-center justify-center rounded-lg bg-[#2563eb]/10 text-[#2563eb]">
                                                    <Globe className="size-6" />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center justify-between gap-2">
                                                        <CardTitle className="truncate text-base">{w.name}</CardTitle>
                                                        <ArrowRight className="size-4 shrink-0 text-muted-foreground opacity-0 transition group-hover:opacity-100" />
                                                    </div>
                                                    <p className="truncate text-sm text-muted-foreground">{w.title}</p>
                                                    {w.public_key && (
                                                        <p className="truncate font-mono text-[11px] text-muted-foreground/80">
                                                            key: {w.public_key}
                                                        </p>
                                                    )}
                                                    {w.allowed_domains.length > 0 ? (
                                                        <p className="truncate text-xs text-muted-foreground">
                                                            {w.allowed_domains.length} dominio{w.allowed_domains.length !== 1 ? 's' : ''}
                                                        </p>
                                                    ) : (
                                                        <p className="truncate text-xs text-amber-600">Todos los dominios</p>
                                                    )}
                                                    <div className="mt-2 flex items-center gap-2">
                                                        <span
                                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                                w.enabled
                                                                    ? 'bg-green-100 text-green-700'
                                                                    : 'bg-muted text-muted-foreground'
                                                            }`}
                                                        >
                                                            {w.enabled ? 'Activo' : 'Inactivo'}
                                                        </span>
                                                        <span className="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                                            {w.conversations_count} conversación{w.conversations_count !== 1 ? 'es' : ''}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </Card>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {widgets.length === 0 && (
                    <div className="rounded-lg border border-dashed bg-muted/10 px-6 py-12 text-center">
                        <Globe className="mx-auto mb-3 size-10 text-muted-foreground/50" />
                        <p className="text-base font-medium text-muted-foreground">No hay widgets configurados</p>
                        <p className="mt-1 text-sm text-muted-foreground/70">
                            Crea un widget arriba para empezar a recibir chats desde tu sitio web.
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}

WidgetIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Canales', href: '/admin/canales' },
        { title: 'Widgets Web', href: '/admin/canales/web-widget' },
    ],
};
