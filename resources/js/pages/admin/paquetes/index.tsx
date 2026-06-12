import { Head, router } from '@inertiajs/react';
import { Loader2, Package, PackageOpen, Power, ShoppingCart } from 'lucide-react';
import { useState } from 'react';
import { DataTableToolbar, type ToolbarFilter } from '@/components/data-table-toolbar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import Heading from '@/components/heading';
import { useClientTableSearch } from '@/hooks/use-client-table-search';
import { toggle } from '@/routes/admin/paquetes';

type PackageItem = {
    slug: string;
    name: string;
    description: string;
    version: string;
    enabled: boolean;
    installed: boolean;
    icon: string | null;
};

type PageProps = {
    packages: PackageItem[];
};

const ICON_MAP: Record<string, React.ComponentType<{ className?: string }>> = {
    ShoppingCart,
    Package,
};

function PackageIcon({ name, className }: { name: string | null; className?: string }) {
    const Icon = (name && ICON_MAP[name]) || Package;
    return <Icon className={className} />;
}

export default function PaquetesIndex({ packages }: PageProps) {
    const [pendingSlug, setPendingSlug] = useState<string | null>(null);

    const table = useClientTableSearch<PackageItem>({
        initialData: packages,
        searchFields: ['name', 'description', 'slug'],
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

    function handleToggle(pkg: PackageItem): void {
        setPendingSlug(pkg.slug);
        router.patch(toggle({ slug: pkg.slug }).url, undefined, {
            preserveScroll: true,
            onFinish: () => setPendingSlug(null),
        });
    }

    return (
        <>
            <Head title="Paquetes" />

            <div className="space-y-4 p-4">
                <Heading title="Paquetes" description="Administra los módulos instalados en el sistema." />

                <DataTableToolbar
                    search={table.search}
                    onSearchChange={table.setSearch}
                    searchPlaceholder="Buscar paquete..."
                    total={table.total}
                    totalLabel={`paquete${table.total !== 1 ? 's' : ''}`}
                    filters={filters}
                />

                {table.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <PackageOpen className="size-12 text-muted-foreground/50" />
                            <p className="text-sm text-muted-foreground">
                                {table.search ? 'Sin resultados para la búsqueda' : 'No hay paquetes instalados todavía.'}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {table.data.map((pkg) => {
                            const isPending = pendingSlug === pkg.slug;

                            return (
                                <Card key={pkg.slug} className="flex flex-col">
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex items-center gap-3">
                                                <div className="flex size-10 items-center justify-center rounded-lg bg-muted">
                                                    <PackageIcon
                                                        name={pkg.icon}
                                                        className="size-5 text-foreground"
                                                    />
                                                </div>
                                                <div className="min-w-0">
                                                    <CardTitle className="truncate text-base">
                                                        {pkg.name}
                                                    </CardTitle>
                                                    <p className="text-xs text-muted-foreground">
                                                        v{pkg.version}
                                                    </p>
                                                </div>
                                            </div>
                                            <Badge
                                                variant={pkg.enabled ? 'default' : 'secondary'}
                                                className="shrink-0"
                                            >
                                                {pkg.enabled ? 'Activo' : 'Inactivo'}
                                            </Badge>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="flex-1 space-y-3">
                                        {pkg.description ? (
                                            <CardDescription className="line-clamp-3">
                                                {pkg.description}
                                            </CardDescription>
                                        ) : null}
                                        <div className="flex flex-wrap items-center gap-2 text-xs">
                                            <code className="rounded bg-muted px-1.5 py-0.5 text-muted-foreground">
                                                {pkg.slug}
                                            </code>
                                        </div>
                                    </CardContent>

                                    <CardFooter className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant={pkg.enabled ? 'destructive' : 'default'}
                                            size="sm"
                                            className="flex-1"
                                            disabled={isPending}
                                            onClick={() => handleToggle(pkg)}
                                        >
                                            {isPending ? (
                                                <Loader2 className="mr-1 size-4 animate-spin" />
                                            ) : (
                                                <Power className="mr-1 size-4" />
                                            )}
                                            {pkg.enabled ? 'Desactivar' : 'Activar'}
                                        </Button>
                                    </CardFooter>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

PaquetesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Paquetes', href: '/admin/paquetes' },
    ],
};
