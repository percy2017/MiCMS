import { Head, Link, router, usePage } from '@inertiajs/react';
import { Loader2, Search, Shield, Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { admin } from '@/routes';
import { destroy, index as indexRoute } from '@/routes/admin/usuarios';

type UserItem = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    email_verified_at: string | null;
    created_at: string | null;
};

type PageProps = {
    users: {
        data: UserItem[];
    };
    filters: { search: string };
};

export default function UsuariosIndex({ users, filters }: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [pendingId, setPendingId] = useState<number | null>(null);
    const canCreate = useCan('create users');
    const canUpdate = useCan('update users');
    const canDelete = useCan('delete users');
    const auth = usePage().props.auth;
    const currentUserId = auth.user?.id;

    function applySearch(e: React.FormEvent): void {
        e.preventDefault();
        router.get(indexRoute({ query: { search } }).url, {}, { preserveState: true });
    }

    function handleDestroy(user: UserItem): void {
        if (! confirm(`¿Eliminar al usuario "${user.name}"?`)) {
            return;
        }
        setPendingId(user.id);
        router.delete(destroy({ user: user.id }).url, {
            preserveScroll: true,
            onFinish: () => setPendingId(null),
        });
    }

    return (
        <>
            <Head title="Usuarios" />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Usuarios"
                        description="Gestiona usuarios, roles y permisos."
                    />
                    {canCreate && (
                        <Button asChild>
                            <Link href="/admin/usuarios/crear">
                                <UserPlus className="mr-2 size-4" />
                                Nuevo usuario
                            </Link>
                        </Button>
                    )}
                </div>

                <form onSubmit={applySearch} className="flex gap-2">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por nombre o email..."
                            className="pl-9"
                        />
                    </div>
                    <Button type="submit" variant="outline">Buscar</Button>
                </form>

                {users.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <Shield className="size-12 text-muted-foreground/50" />
                            <p className="text-sm text-muted-foreground">No hay usuarios.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Nombre</th>
                                    <th className="px-4 py-3 font-medium">Email</th>
                                    <th className="px-4 py-3 font-medium">Roles</th>
                                    <th className="px-4 py-3 font-medium">Verificado</th>
                                    <th className="px-4 py-3 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {users.data.map((user) => {
                                    const isPending = pendingId === user.id;
                                    const isSelf = currentUserId === user.id;
                                    return (
                                        <tr key={user.id} className="hover:bg-muted/30">
                                            <td className="px-4 py-3 font-medium">{user.name}</td>
                                            <td className="px-4 py-3 text-muted-foreground">{user.email}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {user.roles.length === 0 ? (
                                                        <span className="text-xs text-muted-foreground">—</span>
                                                    ) : (
                                                        user.roles.map((r) => (
                                                            <Badge key={r} variant="secondary">{r}</Badge>
                                                        ))
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {user.email_verified_at ? (
                                                    <Badge variant="default">Sí</Badge>
                                                ) : (
                                                    <Badge variant="outline">No</Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    {canUpdate && (
                                                        <Button asChild size="sm" variant="outline">
                                                            <Link href={`/admin/usuarios/${user.id}/editar`}>
                                                                Editar
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    {canDelete && ! isSelf && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="destructive"
                                                            disabled={isPending}
                                                            onClick={() => handleDestroy(user)}
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

UsuariosIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Usuarios', href: indexRoute() },
    ],
};
