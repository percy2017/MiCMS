import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Loader2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { admin } from '@/routes';
import { index as indexRoute, update } from '@/routes/admin/roles';

type PermOption = { value: string; label: string };
type Grouped = Record<string, PermOption[]>;
type Role = { id: number; name: string; permissions: string[] };
type PageProps = { role: Role; permissions: Grouped };

export default function RolesEdit({ role, permissions }: PageProps) {
    const { data, setData, patch, processing, errors } = useForm({
        name: role.name,
        permissions: role.permissions,
    });

    function toggle(perm: string): void {
        setData('permissions', data.permissions.includes(perm)
            ? data.permissions.filter((p) => p !== perm)
            : [...data.permissions, perm],
        );
    }

    function toggleGroup(values: string[]): void {
        const allSelected = values.every((v) => data.permissions.includes(v));
        setData('permissions', allSelected
            ? data.permissions.filter((p) => ! values.includes(p))
            : Array.from(new Set([...data.permissions, ...values])),
        );
    }

    function handleSubmit(e: React.FormEvent): void {
        e.preventDefault();
        patch(update({ role: role.id }).url);
    }

    return (
        <>
            <Head title={`Editar ${role.name}`} />

            <div className="space-y-6 p-4">
                <Button asChild variant="ghost" size="sm">
                    <Link href={indexRoute()}>
                        <ArrowLeft className="mr-1 size-4" /> Volver
                    </Link>
                </Button>

                <Heading title={`Editar ${role.name}`} description="Modifica el nombre y los permisos del rol." />

                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium">Datos del rol</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                />
                                {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                            </div>
                        </CardContent>
                    </Card>

                    {Object.entries(permissions).map(([resource, perms]) => {
                        const values = perms.map((p) => p.value);
                        const allSelected = values.every((v) => data.permissions.includes(v));
                        return (
                            <Card key={resource}>
                                <CardHeader className="flex flex-row items-center justify-between gap-2 space-y-0">
                                    <div>
                                        <CardTitle className="text-sm font-medium capitalize">{resource}</CardTitle>
                                        <CardDescription>
                                            {perms.length} permiso{perms.length === 1 ? '' : 's'}
                                        </CardDescription>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => toggleGroup(values)}
                                    >
                                        {allSelected ? 'Ninguno' : 'Todos'}
                                    </Button>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-3">
                                        {perms.map((p) => {
                                            const checked = data.permissions.includes(p.value);
                                            return (
                                                <label
                                                    key={p.value}
                                                    className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition ${checked ? 'border-primary bg-primary/10' : 'hover:bg-muted/50'}`}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={checked}
                                                        onChange={() => toggle(p.value)}
                                                    />
                                                    {p.label}
                                                </label>
                                            );
                                        })}
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}

                    {errors.permissions && <p className="text-sm text-destructive">{errors.permissions}</p>}

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing && <Loader2 className="mr-2 size-4 animate-spin" />}
                            Guardar cambios
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={indexRoute()}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

RolesEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Roles', href: indexRoute() },
        { title: role.name, href: `/admin/roles/${role.id}/editar` },
    ],
};
