import { Head, router } from '@inertiajs/react';
import { Loader2, Power, ShoppingCart } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { toggle } from '@/routes/admin/paquetes';

type ModuleItem = {
    slug: string;
    name: string;
    description: string;
    version: string;
    enabled: boolean;
    installed: boolean;
    icon: string | null;
};

type PageProps = {
    package: ModuleItem;
};

export default function PaquetesEdit({ package: pkg }: PageProps) {
    const [toggling, setToggling] = useState(false);

    function handleToggle(): void {
        setToggling(true);
        router.patch(toggle({ slug: pkg.slug }).url, undefined, {
            preserveScroll: true,
            onFinish: () => setToggling(false),
        });
    }

    return (
        <>
            <Head title={`${pkg.name} · Paquetes`} />

            <div className="space-y-6 p-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-lg bg-muted">
                                    <ShoppingCart className="size-5 text-foreground" />
                                </div>
                                <div>
                                    <CardTitle>{pkg.name}</CardTitle>
                                    <CardDescription>{pkg.description}</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3 text-sm">
                                <div className="flex flex-wrap items-center gap-2 text-xs">
                                    <Badge variant="outline">v{pkg.version}</Badge>
                                    <code className="rounded bg-muted px-1.5 py-0.5 text-muted-foreground">
                                        {pkg.slug}
                                    </code>
                                    <Badge variant={pkg.enabled ? 'default' : 'secondary'}>
                                        {pkg.enabled ? 'Activo' : 'Inactivo'}
                                    </Badge>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Los módulos se configuran en su archivo <code>module.json</code> dentro de <code>Modules/{pkg.slug === 'poswoo' ? 'PosWoo' : pkg.slug}/</code>.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Estado</CardTitle>
                            <CardDescription>
                                Activa o desactiva este módulo en el CMS.
                                {pkg.enabled
                                    ? ' Al desactivarlo, su menú desaparecerá del sidebar.'
                                    : ' Al activarlo, su menú aparecerá en el sidebar.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button
                                type="button"
                                variant={pkg.enabled ? 'destructive' : 'default'}
                                className="w-full"
                                disabled={toggling}
                                onClick={handleToggle}
                            >
                                {toggling ? (
                                    <Loader2 className="mr-1 size-4 animate-spin" />
                                ) : (
                                    <Power className="mr-1 size-4" />
                                )}
                                {pkg.enabled ? 'Desactivar módulo' : 'Activar módulo'}
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

PaquetesEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Paquetes', href: '/admin/paquetes' },
        { title: 'Configurar', href: '#' },
    ],
};