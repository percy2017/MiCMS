import { Head, router } from '@inertiajs/react';
import {
    Loader2,
    MessageCircle,
    Package,
    PackageOpen,
    Power,
    Settings,
    ShoppingCart,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { edit, toggle } from '@/routes/admin/paquetes';

type PackageItem = {
    id: number;
    slug: string;
    name: string;
    menu_label: string | null;
    version: string;
    description: string | null;
    author: string | null;
    category: string;
    category_label: string;
    icon: string | null;
    enabled: boolean;
    installed: boolean;
    config: Record<string, unknown>;
};

type PageProps = {
    packages: PackageItem[];
    categories: Record<string, string>;
};

const ICON_MAP: Record<string, React.ComponentType<{ className?: string }>> = {
    MessageCircle,
    Users,
    ShoppingCart,
    Package,
};

function PackageIcon({ name, className }: { name: string | null; className?: string }) {
    const Icon = (name && ICON_MAP[name]) || Package;
    return <Icon className={className} />;
}

export default function PaquetesIndex({ packages }: PageProps) {
    const [pendingId, setPendingId] = useState<number | null>(null);

    function handleToggle(pkg: PackageItem): void {
        setPendingId(pkg.id);
        router.patch(toggle({ package: pkg.id }).url, undefined, {
            preserveScroll: true,
            onFinish: () => setPendingId(null),
        });
    }

    return (
        <>
            <Head title="Paquetes" />

            <div className="space-y-6 p-4">
                
                {packages.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <PackageOpen className="size-12 text-muted-foreground/50" />
                            <p className="text-sm text-muted-foreground">
                                No hay paquetes instalados todavía.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {packages.map((pkg) => {
                            const isPending = pendingId === pkg.id;

                            return (
                                <Card key={pkg.id} className="flex flex-col">
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
                                                        {pkg.author ? ` · ${pkg.author}` : null}
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
                                            <Badge variant="outline">{pkg.category_label}</Badge>
                                            <code className="rounded bg-muted px-1.5 py-0.5 text-muted-foreground">
                                                {pkg.slug}
                                            </code>
                                        </div>
                                    </CardContent>

                                    <CardFooter className="flex items-center gap-2">
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="flex-1"
                                        >
                                            <a href={edit({ package: pkg.id }).url}>
                                                <Settings className="mr-1 size-4" />
                                                Configurar
                                            </a>
                                        </Button>
                                        <Button
                                            type="button"
                                            variant={pkg.enabled ? 'destructive' : 'default'}
                                            size="sm"
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
