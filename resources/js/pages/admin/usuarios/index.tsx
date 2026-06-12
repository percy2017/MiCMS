import { Head, Link, router } from '@inertiajs/react';
import { Loader2, Mail, MessageCircle, Phone, Shield, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTableToolbar } from '@/components/data-table-toolbar';
import { TablePagination } from '@/components/table-pagination';
import { useCan } from '@/hooks/use-can';
import { useInitials } from '@/hooks/use-initials';
import { useTableSearch } from '@/hooks/use-table-search';
import { openPosWooChat } from '@/lib/pos-woo-chat';
import { admin } from '@/routes';
import { index as indexRoute } from '@/routes/admin/usuarios';

type UserItem = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    avatar_url: string | null;
    roles: string[];
    email_verified_at: string | null;
    created_at: string | null;
    chat_conversation_id?: number | null;
};

type PageProps = {
    users: {
        data: UserItem[];
        total?: number;
        current_page?: number;
        last_page?: number;
    };
    filters: { search: string };
};

export default function UsuariosIndex({ users: initialUsers, filters }: PageProps) {
    const [pendingId, setPendingId] = useState<number | null>(null);

    const canCreate = useCan('create users');
    const canUpdate = useCan('update users');
    const canDelete = useCan('delete users');
    const getInitials = useInitials();

    const table = useTableSearch<UserItem>({
        endpoint: '/admin/usuarios/search',
        initialData: initialUsers,
        perPage: 10,
        initialFilters: { search: filters.search ?? '' },
    });

    function handleDestroy(user: UserItem): void {
        if (!confirm(`¿Eliminar al usuario "${user.name}"?\n\nEsta acción no se puede deshacer.`)) return;
        setPendingId(user.id);
        router.delete(`/admin/usuarios/${user.id}`, {
            preserveScroll: true,
            onFinish: () => setPendingId(null),
            onSuccess: () => table.refresh(),
        });
    }

    return (
        <>
            <Head title="Usuarios" />

            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4">
                <DataTableToolbar
                    search={table.search}
                    onSearchChange={table.setSearch}
                    searchPlaceholder="Buscar por nombre, email o teléfono..."
                    loading={table.loading}
                    total={table.total}
                    totalLabel={`usuario${table.total !== 1 ? 's' : ''}`}
                    createHref={canCreate ? '/admin/usuarios/crear' : undefined}
                    createLabel="Nuevo"
                />

                {table.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <Shield className="size-12 text-muted-foreground/50" />
                            <p className="text-sm text-muted-foreground">
                                {table.search ? 'Sin resultados para la búsqueda' : 'No hay usuarios.'}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Usuario</th>
                                    <th className="px-4 py-3 font-medium">Email</th>
                                    <th className="px-4 py-3 font-medium">Teléfono</th>
                                    <th className="px-4 py-3 font-medium">Roles</th>
                                    <th className="px-4 py-3 font-medium">Verificado</th>
                                    <th className="px-4 py-3 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {table.data.map((user) => {
                                    const isPending = pendingId === user.id;
                                    return (
                                        <tr key={user.id} className="hover:bg-muted/30">
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="size-10 border">
                                                        {user.avatar_url ? (
                                                            <AvatarImage src={user.avatar_url} alt={user.name} />
                                                        ) : null}
                                                        <AvatarFallback className="bg-muted text-xs text-muted-foreground">
                                                            {getInitials(user.name)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="min-w-0">
                                                        <div className="flex items-center gap-1.5">
                                                            <p className="truncate font-medium">{user.name}</p>
                                                            <Badge variant="outline" className="text-[10px]">ID: {user.id}</Badge>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                <div className="flex items-center gap-2">
                                                    <Mail className="size-3.5 shrink-0" />
                                                    <span className="truncate">{user.email}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {user.phone ? (
                                                    <div className="flex items-center gap-2 text-foreground">
                                                        <Phone className="size-3.5 shrink-0 text-muted-foreground" />
                                                        <span className="font-mono text-xs">{user.phone}</span>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground/60">—</span>
                                                )}
                                            </td>
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
                                                    <Badge variant="default" className="bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                                                        Verificado
                                                    </Badge>
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
                                                    {(() => {
                                                        const phone = (user.phone ?? '').trim();
                                                        if (!phone && !user.chat_conversation_id) {
                                                            return null;
                                                        }
                                                        return (
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    openPosWooChat(
                                                                        user.chat_conversation_id ?? null,
                                                                        phone || null,
                                                                        user.name,
                                                                    )
                                                                }
                                                                title="Enviar mensaje"
                                                            >
                                                                <MessageCircle className="size-4" />
                                                            </Button>
                                                        );
                                                    })()}
                                                    {canDelete && (
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

                <TablePagination
                    currentPage={table.currentPage}
                    lastPage={table.lastPage}
                    onPageChange={table.goPage}
                    total={table.total}
                    perPage={10}
                    itemLabel={`usuario${table.total !== 1 ? 's' : ''}`}
                />
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
