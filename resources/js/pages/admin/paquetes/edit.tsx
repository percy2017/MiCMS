import { Head, router, useForm } from '@inertiajs/react';
import { Loader2, Power } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toggle, update } from '@/routes/admin/paquetes';

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
    package: PackageItem;
};

export default function PaquetesEdit({ package: pkg }: PageProps) {
    const form = useForm({
        name: pkg.name,
    });

    const [toggling, setToggling] = useState(false);

    function submit(e: React.FormEvent): void {
        e.preventDefault();
        form.patch(update({ package: pkg.id }).url, {
            preserveScroll: true,
        });
    }

    function handleToggle(): void {
        setToggling(true);
        router.patch(toggle({ package: pkg.id }).url, undefined, {
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
                            <CardTitle>Información</CardTitle>
                            <CardDescription>
                                Cambia el nombre visible del paquete.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Nombre</Label>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        required
                                    />
                                    {form.errors.name ? (
                                        <p className="text-xs text-destructive">
                                            {form.errors.name}
                                        </p>
                                    ) : null}
                                </div>

                                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                    <Badge variant="outline">{pkg.category_label}</Badge>
                                    <code className="rounded bg-muted px-1.5 py-0.5">
                                        {pkg.slug}
                                    </code>
                                    <span>v{pkg.version}</span>
                                    {pkg.author ? <span>· {pkg.author}</span> : null}
                                </div>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={form.processing}>
                                        {form.processing ? (
                                            <Loader2 className="mr-1 size-4 animate-spin" />
                                        ) : null}
                                        Guardar
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Estado</CardTitle>
                            <CardDescription>
                                Activa o desactiva este paquete en el CMS.
                                {pkg.enabled
                                    ? ' Al desactivarlo, su sub-menú desaparecerá del sidebar.'
                                    : ' Al activarlo, su sub-menú aparecerá en el sidebar.'}
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
                                {pkg.enabled ? 'Desactivar paquete' : 'Activar paquete'}
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
