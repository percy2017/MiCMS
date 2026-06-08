import { Head, Link, router } from '@inertiajs/react';
import { Loader2, Lock, Plus, Shield, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import { admin } from '@/routes';
import { destroy, index as indexRoute } from '@/routes/admin/roles';

type RoleItem = {
    id: number;
    name: string;
    users_count: number;
    permissions_count: number;
    is_protected: boolean;
};

export default function RolesIndex({ roles }: { roles: RoleItem[] }) {
    const [pendingId, setPendingId] = useState<number | null>(null);
    const canCreate = useCan('create roles');
    const canUpdate = useCan('update roles');
    const canDelete = useCan('delete roles');

    function handleDestroy(role: RoleItem): void {
        if (! confirm(`¿Eliminar el rol "${role.name}"?`)) {
            return;
        }
        setPendingId(role.id);
        router.delete(destroy({ role: role.id }).url, {
            preserveScroll: true,
            onFinish: () => setPendingId(null),
        });
    }

    return (
        <>
            <Head title="Roles" />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading title="Roles" description="Administra roles y sus permisos." />
                    {canCreate && (
                        <Button asChild>
                            <Link href="/admin/roles/crear">
                                <Plus className="mr-2 size-4" />
                                Nuevo rol
                            </Link>
                        </Button>
                    )}
                </div>

                {roles.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <Shield className="size-12 text-muted-foreground/50" />
                            <p className="text-sm text-muted-foreground">No hay roles.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Nombre</th>
                                    <th className="px-4 py-3 font-medium">Usuarios</th>
                                    <th className="px-4 py-3 font-medium">Permisos</th>
                                    <th className="px-4 py-3 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {roles.map((role) => {
                                    const isPending = pendingId === role.id;
                                    return (
                                        <tr key={role.id} className="hover:bg-muted/30">
                                            <td className="px-4 py-3 font-medium">
                                                <div className="flex items-center gap-2">
                                                    {role.name}
                                                    {role.is_protected && <Lock className="size-3.5 text-muted-foreground" />}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant="secondary">{role.users_count}</Badge>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant="outline">{role.permissions_count}</Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    {canUpdate && ! role.is_protected && (
                                                        <Button asChild size="sm" variant="outline">
                                                            <Link href={`/admin/roles/${role.id}/editar`}>
                                                                Editar
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    {canDelete && ! role.is_protected && role.users_count === 0 && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="destructive"
                                                            disabled={isPending}
                                                            onClick={() => handleDestroy(role)}
                                                        >
                                                            {isPending ? (
                                                                <Loader2 className="size-4 animate-spin" />
                                                            ) : (
                                                                <Trash2 className="size-4" />
                                                            )}
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Roles', href: indexRoute() },
    ],
};
