import { Head, router, useForm } from '@inertiajs/react';
import { FilePlus, Home, Loader2, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { DataTableToolbar, type ToolbarFilter } from '@/components/data-table-toolbar';
import { TablePagination } from '@/components/table-pagination';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTableSearch } from '@/hooks/use-table-search';
import { index as paginasIndex, destroy, forceDestroy, restore, setHome, store, unsetHome } from '@/routes/admin/paginas';

type PageItem = {
    id: number;
    title: string;
    slug: string;
    status: 'draft' | 'published';
    is_published: boolean;
    is_draft: boolean;
    is_home: boolean;
    created_at: string;
    updated_at: string;
    created_at_diff: string;
    updated_at_diff: string;
    uploader: { id: number; name: string } | null;
    public_url: string;
};

type PageProps = {
    pages: {
        data: PageItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search: string;
        status: string | null;
        trashed: boolean;
    };
};

const STATUS_OPTIONS = [
    { value: '', label: 'Todos los estados' },
    { value: 'draft', label: 'Borrador' },
    { value: 'published', label: 'Publicado' },
];

function slugify(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 255);
}

export default function PaginasIndex({ pages, filters }: PageProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<PageItem | null>(null);

    const table = useTableSearch<PageItem>({
        endpoint: '/admin/paginas/search',
        initialData: pages,
        perPage: 10,
        initialFilters: {
            search: filters.search ?? '',
            status: filters.status ?? '',
            trashed: filters.trashed ? '1' : '',
        },
        extraParams: () => ({ trashed: filters.trashed ? '1' : '' }),
    });

    const statusFilters: ToolbarFilter[] = [
        {
            key: 'status',
            label: 'Estado',
            value: table.filters.status ?? '',
            onChange: (v) => table.setFilter('status', v),
            placeholder: 'Todos los estados',
            options: [
                { value: 'draft', label: 'Borrador' },
                { value: 'published', label: 'Publicado' },
            ],
        },
    ];

    const form = useForm({
        title: '',
        slug: '',
    });

    function openCreate(): void {
        form.reset();
        form.clearErrors();
        setCreateOpen(true);
    }

    function onTitleChange(value: string): void {
        form.setData((data) => ({
            ...data,
            title: value,
            slug: data.slug && data.slug !== slugify(data.title) ? data.slug : slugify(value),
        }));
    }

    function submitCreate(e: React.FormEvent): void {
        e.preventDefault();
        form.post(store.url(), {
            onSuccess: () => setCreateOpen(false),
        });
    }

    function confirmDelete(): void {
        if (!deleteTarget) return;
        router.delete(destroy.url({ page: deleteTarget.id }), {
            onSuccess: () => setDeleteTarget(null),
        });
    }

    function confirmRestore(): void {
        if (!deleteTarget) return;
        router.post(restore.url({ page: deleteTarget.id }), undefined, {
            onSuccess: () => setDeleteTarget(null),
        });
    }

    function confirmForceDelete(): void {
        if (!deleteTarget) return;
        router.delete(forceDestroy.url({ page: deleteTarget.id }), {
            onSuccess: () => setDeleteTarget(null),
        });
    }

    function toggleHome(page: PageItem): void {
        if (page.is_home) {
            router.delete(unsetHome.url({ page: page.id }));
        } else {
            router.post(setHome.url({ page: page.id }));
        }
    }

    function toggleTrash(): void {
        router.get('/admin/paginas', {
            search: table.search,
            status: filters.status,
            trashed: filters.trashed ? null : 1,
        }, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title="Páginas" />

            <div className="space-y-4 p-4">
                <Heading title="Páginas" description="Administra las páginas del sitio, su contenido y su visibilidad." />

                <DataTableToolbar
                    search={table.search}
                    onSearchChange={table.setSearch}
                    searchPlaceholder="Buscar páginas..."
                    loading={table.loading}
                    total={table.total}
                    totalLabel={`página${table.total !== 1 ? 's' : ''}`}
                    filters={statusFilters}
                    actions={
                        <Button onClick={openCreate}>
                            <FilePlus className="mr-1 size-4" />
                            Nueva página
                        </Button>
                    }
                />

                <div className="flex items-center justify-between rounded-md border bg-muted/30 px-3 py-2">
                    <p className="text-xs text-muted-foreground">
                        {filters.trashed
                            ? 'Mostrando páginas eliminadas (papelera).'
                            : 'Mostrando páginas activas.'}
                    </p>
                    <Button type="button" variant="ghost" size="sm" onClick={toggleTrash}>
                        {filters.trashed ? 'Ver activas' : 'Ver papelera'}
                    </Button>
                </div>

                <div className="overflow-hidden rounded-lg border bg-card">
                    {table.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
                            <p className="text-sm text-muted-foreground">
                                {table.search || table.filters.status
                                    ? 'No se encontraron páginas con esos filtros.'
                                    : 'Aún no hay páginas. Crea la primera.'}
                            </p>
                            {!table.search && !table.filters.status ? (
                                <Button variant="outline" onClick={openCreate}>
                                    <FilePlus className="mr-1 size-4" />
                                    Crear página
                                </Button>
                            ) : null}
                        </div>
                    ) : (
                        <div className="divide-y">
                            {table.data.map((page) => (
                                <div
                                    key={page.id}
                                    className="flex items-center gap-4 p-4 transition-colors hover:bg-muted/30"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <a
                                                href={`/admin/paginas/${page.id}/editar`}
                                                className="truncate font-medium hover:underline"
                                            >
                                                {page.title}
                                            </a>
                                            <span
                                                className={
                                                    'rounded-full px-2 py-0.5 text-xs font-medium ' +
                                                    (page.is_published
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                                        : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300')
                                                }
                                            >
                                                {page.is_published ? 'Publicado' : 'Borrador'}
                                            </span>
                                            {page.is_home ? (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                    <Home className="size-3" />
                                                    Inicio
                                                </span>
                                            ) : null}
                                        </div>
                                        <div className="mt-1 flex items-center gap-3 text-xs text-muted-foreground">
                                            <code className="rounded bg-muted px-1.5 py-0.5">
                                                {page.is_home ? '/' : `/${page.slug}`}
                                            </code>
                                            <span>·</span>
                                            <span>Actualizada {page.updated_at_diff}</span>
                                            {page.uploader ? (
                                                <>
                                                    <span>·</span>
                                                    <span>por {page.uploader.name}</span>
                                                </>
                                            ) : null}
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant={page.is_home ? 'default' : 'outline'}
                                            size="sm"
                                            disabled={filters.trashed}
                                            onClick={() => toggleHome(page)}
                                        >
                                            <Home className="mr-1 size-4" />
                                            {page.is_home ? 'Es inicio' : 'Hacer inicio'}
                                        </Button>
                                        <Button variant="outline" size="sm" asChild>
                                            <a href={`/admin/paginas/${page.id}/editar`}>
                                                Editar
                                            </a>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => setDeleteTarget(page)}
                                            aria-label={filters.trashed ? 'Restaurar o eliminar permanentemente' : 'Eliminar página'}
                                        >
                                            {filters.trashed ? (
                                                <RotateCcw className="size-4 text-primary" />
                                            ) : (
                                                <Trash2 className="size-4 text-destructive" />
                                            )}
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <TablePagination
                    currentPage={table.currentPage}
                    lastPage={table.lastPage}
                    onPageChange={table.goPage}
                    total={table.total}
                    perPage={10}
                    itemLabel={`página${table.total !== 1 ? 's' : ''}`}
                />
            </div>

            <Dialog
                open={createOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        form.reset();
                        form.clearErrors();
                    }
                    setCreateOpen(open);
                }}
            >
                <DialogContent>
                    <form onSubmit={submitCreate}>
                        <DialogHeader>
                            <DialogTitle>Nueva página</DialogTitle>
                            <DialogDescription>
                                Crea una página vacía. Después podrás diseñarla con Puck.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4 py-4">
                            <div className="space-y-2">
                                <Label htmlFor="title">Título</Label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(e) => onTitleChange(e.target.value)}
                                    autoFocus
                                    required
                                />
                                {form.errors.title ? (
                                    <p className="text-xs text-destructive">{form.errors.title}</p>
                                ) : null}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="slug">Slug (URL)</Label>
                                <div className="flex items-center gap-1 text-sm text-muted-foreground">
                                    <span>/</span>
                                    <Input
                                        id="slug"
                                        value={form.data.slug}
                                        onChange={(e) =>
                                            form.setData('slug', slugify(e.target.value))
                                        }
                                        required
                                    />
                                </div>
                                {form.errors.slug ? (
                                    <p className="text-xs text-destructive">{form.errors.slug}</p>
                                ) : null}
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCreateOpen(false)}
                                disabled={form.processing}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? (
                                    <Loader2 className="mr-1 size-4 animate-spin" />
                                ) : null}
                                Crear y editar
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {filters.trashed ? 'Página en papelera' : 'Eliminar página'}
                        </DialogTitle>
                        <DialogDescription>
                            {filters.trashed
                                ? `"${deleteTarget?.title}" está eliminada. Puedes restaurarla o borrarla permanentemente.`
                                : `¿Eliminar "${deleteTarget?.title}"? Esta acción se puede deshacer desde la papelera.`}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setDeleteTarget(null)}
                        >
                            Cancelar
                        </Button>
                        {filters.trashed ? (
                            <>
                                <Button
                                    variant="destructive"
                                    onClick={confirmForceDelete}
                                >
                                    <Trash2 className="mr-1 size-4" />
                                    Eliminar permanente
                                </Button>
                                <Button onClick={confirmRestore}>
                                    <RotateCcw className="mr-1 size-4" />
                                    Restaurar
                                </Button>
                            </>
                        ) : (
                            <Button variant="destructive" onClick={confirmDelete}>
                                <Trash2 className="mr-1 size-4" />
                                Eliminar
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PaginasIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Paginas', href: paginasIndex().url },
    ],
};
