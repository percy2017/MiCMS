import { Head, router, useForm } from '@inertiajs/react';
import { FilePlus, Loader2, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { destroy, store } from '@/routes/admin/menus';

type MenuItem = {
    id: number;
    name: string;
    location: string;
    location_label: string | null;
    items_count: number;
    created_at: string;
    updated_at: string;
};

type PageProps = {
    menus: MenuItem[];
    locations: Record<string, string>;
};

export default function MenusIndex({ menus, locations }: PageProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<MenuItem | null>(null);

    const form = useForm({
        name: '',
        location: '',
    });

    const locationEntries = Object.entries(locations);
    const usedLocations = new Set(menus.map((m) => m.location));
    const availableLocations = locationEntries.filter(
        ([key]) => !usedLocations.has(key),
    );

    function openCreate(): void {
        form.reset();
        form.clearErrors();
        form.setData('location', availableLocations[0]?.[0] ?? '');
        setCreateOpen(true);
    }

    function submitCreate(e: React.FormEvent): void {
        e.preventDefault();
        form.post(store.url(), {
            onSuccess: () => setCreateOpen(false),
        });
    }

    function confirmDelete(): void {
        if (!deleteTarget) {
            return;
        }

        router.delete(destroy.url({ menu: deleteTarget.id }), {
            onSuccess: () => setDeleteTarget(null),
        });
    }

    return (
        <>
            <Head title="Menús" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Menús"
                        description="Crea y edita menús de navegación dinámicos"
                    />
                    <Button onClick={openCreate} disabled={availableLocations.length === 0}>
                        <FilePlus className="mr-1 size-4" />
                        Nuevo menú
                    </Button>
                </div>

                {availableLocations.length === 0 && menus.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No hay ubicaciones configuradas. Añade ubicaciones en
                        <code className="mx-1 rounded bg-muted px-1.5 py-0.5">
                            config/menus.php
                        </code>
                        .
                    </p>
                ) : null}

                <div className="overflow-hidden rounded-lg border bg-card">
                    {menus.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
                            <p className="text-sm text-muted-foreground">
                                Aún no hay menús. Crea el primero.
                            </p>
                            {availableLocations.length > 0 ? (
                                <Button variant="outline" onClick={openCreate}>
                                    <FilePlus className="mr-1 size-4" />
                                    Crear menú
                                </Button>
                            ) : null}
                        </div>
                    ) : (
                        <div className="divide-y">
                            {menus.map((menu) => (
                                <div
                                    key={menu.id}
                                    className="flex items-center gap-4 p-4 transition-colors hover:bg-muted/30"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <a
                                                href={`/admin/menus/${menu.id}/editar`}
                                                className="truncate font-medium hover:underline"
                                            >
                                                {menu.name}
                                            </a>
                                            <span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                {menu.location_label ?? menu.location}
                                            </span>
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                {menu.items_count}{' '}
                                                {menu.items_count === 1 ? 'elemento' : 'elementos'}
                                            </span>
                                        </div>
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            Actualizado {new Date(menu.updated_at).toLocaleDateString()}
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Button variant="outline" size="sm" asChild>
                                            <a
                                                href={`/admin/menus/${menu.id}/editar`}
                                            >
                                                Editar
                                            </a>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => setDeleteTarget(menu)}
                                            aria-label="Eliminar menú"
                                        >
                                            <Trash2 className="size-4 text-destructive" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <Dialog
                open={createOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        form.reset();
                        form.clearErrors();
                    }

                    setCreateOpen(open);
                }}
            >
                <DialogContent>
                    <form onSubmit={submitCreate}>
                        <DialogHeader>
                            <DialogTitle>Nuevo menú</DialogTitle>
                            <DialogDescription>
                                Crea un menú y asígnalo a una ubicación.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4 py-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    placeholder="Menú principal"
                                    autoFocus
                                    required
                                />
                                {form.errors.name ? (
                                    <p className="text-xs text-destructive">
                                        {form.errors.name}
                                    </p>
                                ) : null}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="location">Ubicación</Label>
                                <Select
                                    value={form.data.location}
                                    onValueChange={(value) =>
                                        form.setData('location', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona una ubicación" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableLocations.map(
                                            ([key, label]) => (
                                                <SelectItem key={key} value={key}>
                                                    {label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                {form.errors.location ? (
                                    <p className="text-xs text-destructive">
                                        {form.errors.location}
                                    </p>
                                ) : null}
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCreateOpen(false)}
                                disabled={form.processing}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={form.processing || !form.data.location}
                            >
                                {form.processing ? (
                                    <Loader2 className="mr-1 size-4 animate-spin" />
                                ) : null}
                                Crear menú
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Eliminar menú</DialogTitle>
                        <DialogDescription>
                            ¿Eliminar &quot;{deleteTarget?.name}&quot;? Esta acción
                            no se puede deshacer.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteTarget(null)}
                        >
                            Cancelar
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            <Trash2 className="mr-1 size-4" />
                            Eliminar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
