import { Head, Link, router } from '@inertiajs/react';
import { BadgeCheck, Building2, Loader2, MessageCircle, Pencil, Phone, Shield, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTableToolbar, type ToolbarFilter } from '@/components/data-table-toolbar';
import { TablePagination } from '@/components/table-pagination';
import { describeCountry } from '@/lib/country';
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
    country_code: string | null;
    is_whatsapp_business: boolean;
    email_verified_at: string | null;
    avatar_url: string | null;
    roles: string[];
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
    filters: {
        search: string;
        country_code: string;
        is_whatsapp_business: string;
        role: string;
        verified: string;
    };
    availableCountries: string[];
    availableRoles: Array<{ id: number; name: string }>;
};

const BUSINESS_OPTIONS = [
    { value: '1', label: 'Sí' },
    { value: '0', label: 'No' },
];

const VERIFIED_OPTIONS = [
    { value: '1', label: 'Verificados' },
    { value: '0', label: 'Sin verificar' },
];

export default function UsuariosIndex({ users: initialUsers, filters, availableCountries, availableRoles }: PageProps) {
    const [pendingId, setPendingId] = useState<number | null>(null);

    const canCreate = useCan('create users');
    const canUpdate = useCan('update users');
    const canDelete = useCan('delete users');
    const getInitials = useInitials();

    const table = useTableSearch<UserItem>({
        endpoint: '/admin/usuarios/search',
        initialData: initialUsers,
        perPage: 10,
        initialFilters: {
            search: filters.search ?? '',
            country_code: filters.country_code ?? '',
            is_whatsapp_business: filters.is_whatsapp_business ?? '',
            role: filters.role ?? '',
            verified: filters.verified ?? '',
        },
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

    const countryOptions = (availableCountries ?? []).map((code) => {
        const c = describeCountry(code);
        return { value: code, label: c ? `${code} · ${c.name}` : code };
    });

    const roleOptions = (availableRoles ?? []).map((r) => ({ value: r.name, label: r.name }));

    const toolbarFilters: ToolbarFilter[] = [
        {
            key: 'country_code',
            label: 'País',
            placeholder: 'Todos los países',
            value: table.filters.country_code ?? '',
            onChange: (v) => table.setFilter('country_code', v),
            options: countryOptions,
        },
        {
            key: 'role',
            label: 'Rol',
            placeholder: 'Todos los roles',
            value: table.filters.role ?? '',
            onChange: (v) => table.setFilter('role', v),
            options: roleOptions,
        },
        {
            key: 'is_whatsapp_business',
            label: 'Business',
            placeholder: 'Todos',
            value: table.filters.is_whatsapp_business ?? '',
            onChange: (v) => table.setFilter('is_whatsapp_business', v),
            options: BUSINESS_OPTIONS,
        },
        {
            key: 'verified',
            label: 'Verificado',
            placeholder: 'Todos',
            value: table.filters.verified ?? '',
            onChange: (v) => table.setFilter('verified', v),
            options: VERIFIED_OPTIONS,
        },
    ];

    const hasActiveFilters = (table.filters.country_code && table.filters.country_code !== '')
        || (table.filters.role && table.filters.role !== '')
        || (table.filters.is_whatsapp_business && table.filters.is_whatsapp_business !== '')
        || (table.filters.verified && table.filters.verified !== '');

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
                    filters={toolbarFilters}
                />

                {hasActiveFilters && (
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                        <span>Filtros activos:</span>
                        {toolbarFilters.map((f) => {
                            const v = table.filters[f.key];
                            if (!v || v === '') {
                                return null;
                            }
                            const opt = f.options.find((o) => o.value === v);
                            return (
                                <span key={f.key} className="inline-flex items-center gap-1 rounded-md border bg-muted/40 px-2 py-0.5">
                                    <span className="font-medium">{f.label}:</span>
                                    <span>{opt?.label ?? v}</span>
                                    <button
                                        type="button"
                                        onClick={() => table.setFilter(f.key, '')}
                                        className="ml-0.5 rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                        title="Quitar filtro"
                                    >
                                        ×
                                    </button>
                                </span>
                            );
                        })}
                    </div>
                )}

                {table.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <Shield className="size-12 text-muted-foreground/50" />
                            <p className="text-sm text-muted-foreground">
                                {table.search || hasActiveFilters ? 'Sin resultados para los filtros activos' : 'No hay usuarios.'}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Usuario</th>
                                    <th className="px-4 py-3 font-medium">Teléfono</th>
                                    <th className="px-4 py-3 font-medium">País</th>
                                    <th className="px-4 py-3 font-medium">Roles</th>
                                    <th className="px-4 py-3 font-medium">Business</th>
                                    <th className="px-4 py-3 font-medium">Verificado</th>
                                    <th className="px-4 py-3 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {table.data.map((user) => {
                                    const isPending = pendingId === user.id;
                                    const phone = (user.phone ?? '').trim();
                                    const country = describeCountry(user.country_code);
                                    const canOpenChat = !!phone || !!user.chat_conversation_id;
                                    const canEdit = canUpdate;
                                    const canRemove = canDelete;

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
                                            <td className="px-4 py-3">
                                                {phone ? (
                                                    <div className="flex items-center gap-2 text-foreground">
                                                        <Phone className="size-3.5 shrink-0 text-muted-foreground" />
                                                        <span className="font-mono text-xs">{phone}</span>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground/60">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {country ? (
                                                    <span
                                                        className="inline-flex items-center gap-1.5 rounded-md border bg-muted/40 px-2 py-0.5 text-xs"
                                                        title={country.dialCode ? `${country.name} ${country.dialCode}` : country.name}
                                                    >
                                                        <img
                                                            src={`https://flagcdn.com/${country.code.toLowerCase()}.svg`}
                                                            alt={country.name}
                                                            width={16}
                                                            height={12}
                                                            className="size-4 shrink-0 rounded-sm object-cover"
                                                            onError={(e) => {
                                                                const t = e.currentTarget;
                                                                t.style.display = 'none';
                                                                const sib = t.nextElementSibling as HTMLElement | null;
                                                                if (sib) sib.style.display = 'inline-flex';
                                                            }}
                                                        />
                                                        <span
                                                            className="hidden size-4 shrink-0 items-center justify-center rounded-sm bg-primary/10 font-mono text-[9px] font-semibold uppercase text-primary"
                                                            aria-hidden="true"
                                                        >
                                                            {country.code}
                                                        </span>
                                                        <span className="font-medium">{country.name}</span>
                                                        {country.dialCode && (
                                                            <span className="font-mono text-[10px] text-muted-foreground">
                                                                {country.dialCode}
                                                            </span>
                                                        )}
                                                    </span>
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
                                                {user.is_whatsapp_business ? (
                                                    <span
                                                        className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-600 dark:text-emerald-400"
                                                        title="Cuenta verificada como empresa"
                                                    >
                                                        <Building2 className="size-3" />
                                                        Sí
                                                    </span>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground/60">No</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {user.email_verified_at ? (
                                                    <span
                                                        className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-600 dark:text-emerald-400"
                                                        title={`Verificado el ${new Date(user.email_verified_at).toLocaleDateString()}`}
                                                    >
                                                        <BadgeCheck className="size-3" />
                                                        Sí
                                                    </span>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground/60">No</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-0.5">
                                                    {canEdit && (
                                                        <Button
                                                            asChild
                                                            size="icon"
                                                            variant="ghost"
                                                            title={`Editar ${user.name}`}
                                                        >
                                                            <Link href={`/admin/usuarios/${user.id}/editar`}>
                                                                <Pencil className="size-4" />
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    {canOpenChat && (
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
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
                                                    )}
                                                    {canRemove && (
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            onClick={() => handleDestroy(user)}
                                                            disabled={isPending}
                                                            title="Eliminar usuario"
                                                            className="text-muted-foreground hover:text-destructive"
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
