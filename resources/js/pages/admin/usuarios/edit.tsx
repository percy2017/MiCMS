import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Loader2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { admin } from '@/routes';
import { index as indexRoute, update } from '@/routes/admin/usuarios';

type Role = { id: number; name: string };
type User = { id: number; name: string; email: string; roles: string[] };
type PageProps = { user: User; roles: Role[] };

export default function UsuariosEdit({ user, roles }: PageProps) {
    const { data, setData, patch, processing, errors } = useForm({
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
        roles: user.roles,
    });

    function toggleRole(name: string): void {
        setData('roles', data.roles.includes(name)
            ? data.roles.filter((r) => r !== name)
            : [...data.roles, name],
        );
    }

    function handleSubmit(e: React.FormEvent): void {
        e.preventDefault();
        patch(update({ user: user.id }).url);
    }

    return (
        <>
            <Head title={`Editar ${user.name}`} />

            <div className="space-y-6 p-4">
                <Button asChild variant="ghost" size="sm">
                    <Link href={indexRoute()}>
                        <ArrowLeft className="mr-1 size-4" /> Volver
                    </Link>
                </Button>

                <Heading title={`Editar ${user.name}`} description="Modifica los datos y roles del usuario." />

                <Card>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
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

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    required
                                />
                                {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="password">Nueva contraseña</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="(dejar vacío para no cambiar)"
                                    />
                                    {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">Confirmar contraseña</Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label>Roles</Label>
                                <div className="flex flex-wrap gap-2">
                                    {roles.map((role) => {
                                        const checked = data.roles.includes(role.name);
                                        return (
                                            <label
                                                key={role.id}
                                                className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition ${checked ? 'border-primary bg-primary/10' : 'hover:bg-muted/50'}`}
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={checked}
                                                    onChange={() => toggleRole(role.name)}
                                                />
                                                {role.name}
                                            </label>
                                        );
                                    })}
                                </div>
                                {errors.roles && <p className="text-sm text-destructive">{errors.roles}</p>}
                            </div>

                            <div className="flex items-center gap-3 pt-2">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2 className="mr-2 size-4 animate-spin" />}
                                    Guardar cambios
                                </Button>
                                <Button type="button" variant="outline" asChild>
                                    <Link href={indexRoute()}>Cancelar</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

UsuariosEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Usuarios', href: indexRoute() },
        { title: user.name, href: `/admin/usuarios/${user.id}/editar` },
    ],
};
