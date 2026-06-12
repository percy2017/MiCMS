import { Head, router, useForm } from '@inertiajs/react';
import { Check, Key, Loader2, Minus, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { DataTableToolbar } from '@/components/data-table-toolbar';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useClientTableSearch } from '@/hooks/use-client-table-search';
import { admin } from '@/routes';
import { destroy, index as indexRoute, store } from '@/routes/admin/permisos';

type Row = { id: number; permission: string; [role: string]: unknown };
type Role = { id: number; name: string };
type PageProps = { matrix: Row[]; roles: Role[] };

export default function PermisosIndex({ matrix, roles }: PageProps) {
    const canCreate = useCan('create permissions');
    const canDelete = useCan('delete permissions');
    const [pendingId, setPendingId] = useState<number | null>(null);
    const { data, setData, post, processing, errors, reset } = useForm({ name: '' });

    const table = useClientTableSearch<Row>({
        initialData: matrix,
        searchFields: ['permission'],
        perPage: 20,
    });

    function handleCreate(e: React.FormEvent): void {
        e.preventDefault();
        post(store.url(), { onSuccess: () => reset() });
    }

    function handleDelete(row: Row): void {
        if (!confirm(`¿Eliminar el permiso "${row.permission}"?`)) return;
        setPendingId(row.id);
        router.delete(destroy({ permission: row.id }).url, {
            preserveScroll: true,
            onFinish: () => setPendingId(null),
        });
    }

    return (
        <>
            <Head title="Permisos" />

            <div className="space-y-4 p-4">
                <Heading title="Permisos" description="Catálogo de permisos del sistema y qué roles los tienen asignados." />

                {canCreate && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium">Crear permiso</CardTitle>
                            <CardDescription>
                                Usa el formato <code className="rounded bg-muted px-1">verbo recurso</code> (ej. <code className="rounded bg-muted px-1">export pages</code>).
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleCreate} className="flex items-end gap-2">
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="perm-name">Nombre</Label>
                                    <Input
                                        id="perm-name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="ej. export pages"
                                        required
                                    />
                                    {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                                </div>
                                <Button type="submit" disabled={processing}>
                                    {processing ? <Loader2 className="size-4 animate-spin" /> : <Plus className="size-4" />}
                                    Crear
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between gap-2">
                            <div>
                                <CardTitle className="text-sm font-medium">Matriz de permisos</CardTitle>
                                <CardDescription>
                                    {table.total} permiso{table.total === 1 ? '' : 's'} · {roles.length} rol{roles.length === 1 ? '' : 'es'}
                                </CardDescription>
                            </div>
                            <div className="w-64">
                                <DataTableToolbar
                                    search={table.search}
                                    onSearchChange={table.setSearch}
                                    searchPlaceholder="Buscar permiso..."
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        {table.data.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 py-12 text-center">
                                <Key className="size-10 text-muted-foreground/50" />
                                <p className="text-sm text-muted-foreground">
                                    {table.search ? 'Sin resultados para la búsqueda' : 'No hay permisos.'}
                                </p>
                            </div>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">Permiso</th>
                                        {roles.map((r) => (
                                            <th key={r.id} className="px-3 py-2 text-center font-medium capitalize">
                                                {r.name}
                                            </th>
                                        ))}
                                        {canDelete && <th className="px-3 py-2 text-right font-medium">Acciones</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {table.data.map((row) => {
                                        const isPending = pendingId === row.id;
                                        return (
                                            <tr key={row.permission} className="hover:bg-muted/30">
                                                <td className="px-3 py-2 font-mono text-xs">{row.permission}</td>
                                                {roles.map((r) => {
                                                    const has = Boolean(row[r.name]);
                                                    return (
                                                        <td key={r.id} className="px-3 py-2 text-center">
                                                            {has ? (
                                                                <Check className="mx-auto size-4 text-primary" />
                                                            ) : (
                                                                <Minus className="mx-auto size-4 text-muted-foreground/30" />
                                                            )}
                                                        </td>
                                                    );
                                                })}
                                                {canDelete && (
                                                    <td className="px-3 py-2 text-right">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            disabled={isPending}
                                                            onClick={() => handleDelete(row)}
                                                        >
                                                            {isPending ? (
                                                                <Loader2 className="size-4 animate-spin" />
                                                            ) : (
                                                                <X className="size-4" />
                                                            )}
                                                        </Button>
                                                    </td>
                                                )}
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PermisosIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Permisos', href: indexRoute() },
    ],
};
