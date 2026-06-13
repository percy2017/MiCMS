import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, FileImage, Loader2, Plus, Search, Zap } from 'lucide-react';
import { useState } from 'react';
import { DataTableToolbar, type ToolbarFilter } from '@/components/data-table-toolbar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardTitle } from '@/components/ui/card';
import { useClientTableSearch } from '@/hooks/use-client-table-search';
import { csrfHeaders } from '@/lib/chat-utils';
import { admin } from '@/routes';

type QuickReplyItem = {
    id: number;
    shortcut: string;
    title: string;
    content: string | null;
    category: string | null;
    media_id: number | null;
    media_url: string | null;
    media_mime: string | null;
    media_name: string | null;
    sort: number;
    enabled: boolean;
};

type PageProps = {
    replies: QuickReplyItem[];
    categories: string[];
    filters: { search: string; category: string; enabled: string };
};

function categoryBadge(category: string | null): React.ReactElement {
    if (!category) return <span className="text-xs text-muted-foreground">—</span>;
    return <Badge variant="secondary">{category}</Badge>;
}

export default function QuickRepliesIndex({ replies, categories }: PageProps) {
    const table = useClientTableSearch<QuickReplyItem>({
        initialData: replies,
        searchFields: ['shortcut', 'title', 'content', 'category'],
        perPage: 50,
        initialFilters: { category: '', enabled: '' },
    });

    const filters: ToolbarFilter[] = [
        {
            key: 'category',
            label: 'Categoría',
            value: table.filters.category ?? '',
            onChange: (v) => table.setFilter('category', v),
            placeholder: 'Todas',
            options: categories.map((c) => ({ value: c, label: c })),
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

    return (
        <>
            <Head title="Respuestas rápidas" />
            <div className="h-full min-h-0 space-y-4 overflow-y-auto p-4">
                <DataTableToolbar
                    search={table.search}
                    onSearchChange={table.setSearch}
                    searchPlaceholder="Buscar por shortcut, título o contenido..."
                    total={table.total}
                    totalLabel={`respuesta${table.total !== 1 ? 's' : ''} rápida${table.total !== 1 ? 's' : ''}`}
                    filters={filters}
                />

                <div>
                    <h2 className="mb-3 text-base font-medium">Añadir respuesta rápida</h2>
                    <button
                        type="button"
                        onClick={() => router.visit('/admin/canales/respuestas-rapidas/nueva')}
                        className="group block w-full text-left"
                    >
                        <Card className="flex min-h-[80px] cursor-pointer flex-row items-center gap-3 border-2 border-dashed border-yellow-500/40 bg-yellow-500/5 px-4 transition hover:border-yellow-500 hover:bg-yellow-500/10 hover:shadow-sm">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-yellow-500/60 text-yellow-600">
                                <Plus className="size-4" />
                            </div>
                            <div className="text-left">
                                <div className="flex items-center gap-2">
                                    <Zap className="size-4 text-yellow-600" />
                                    <CardTitle className="text-sm">Nueva respuesta rápida</CardTitle>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Crea un shortcut <code className="rounded bg-muted px-1">/saludo</code> con texto y/o archivos adjuntos
                                </p>
                            </div>
                        </Card>
                    </button>
                </div>

                {replies.length > 0 && (
                    <div>
                        <h2 className="mb-3 text-base font-medium">Respuestas configuradas</h2>
                        {table.data.length === 0 ? (
                            <div className="rounded-lg border border-dashed bg-muted/10 px-6 py-12 text-center">
                                <Search className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                                <p className="text-sm text-muted-foreground">Sin resultados para la búsqueda.</p>
                            </div>
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {table.data.map((r) => (
                                    <Link
                                        key={r.id}
                                        href={`/admin/canales/respuestas-rapidas/${r.id}/edit`}
                                        className="group block"
                                    >
                                        <Card className="relative overflow-hidden border-l-4 border-yellow-500 transition hover:shadow-md">
                                            <div className="flex items-start gap-3 p-4">
                                                <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-yellow-500/10 text-yellow-600">
                                                    <Zap className="size-5" />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center justify-between gap-2">
                                                        <code className="truncate rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                                                            /{r.shortcut}
                                                        </code>
                                                        <ArrowRight className="size-3.5 shrink-0 text-muted-foreground opacity-0 transition group-hover:opacity-100" />
                                                    </div>
                                                    <p className="mt-1 truncate text-sm font-medium">{r.title}</p>
                                                    {r.content && (
                                                        <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                                            {r.content}
                                                        </p>
                                                    )}
                                                    <div className="mt-2 flex items-center gap-2">
                                                        {categoryBadge(r.category)}
                                                        {r.media_id && (
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-700">
                                                                <FileImage className="size-3" />
                                                                media
                                                            </span>
                                                        )}
                                                        <span
                                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ${
                                                                r.enabled
                                                                    ? 'bg-green-100 text-green-700'
                                                                    : 'bg-muted text-muted-foreground'
                                                            }`}
                                                        >
                                                            {r.enabled ? 'Activo' : 'Inactivo'}
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

                {replies.length === 0 && (
                    <div className="rounded-lg border border-dashed bg-muted/10 px-6 py-12 text-center">
                        <Zap className="mx-auto mb-3 size-10 text-muted-foreground/50" />
                        <p className="text-base font-medium text-muted-foreground">No hay respuestas rápidas configuradas</p>
                        <p className="mt-1 text-sm text-muted-foreground/70">
                            Crea una arriba. Luego en cualquier chat podrás usar <code className="rounded bg-muted px-1">/shortcut</code> para autocompletar.
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}

QuickRepliesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Canales', href: '/admin/canales' },
        { title: 'Respuestas rápidas', href: '/admin/canales/respuestas-rapidas' },
    ],
};
