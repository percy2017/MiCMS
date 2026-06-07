import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    GripVertical,
    Link2,
    Loader2,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { update } from '@/routes/admin/menus';
import { store, destroy, reorder } from '@/routes/admin/menus/items';

type PageItem = {
    id: number;
    title: string;
    slug: string;
    is_home: boolean;
    status: string;
};

type MenuItemNode = {
    id: number;
    menu_id: number;
    parent_id: number | null;
    label: string;
    url: string | null;
    resolved_url: string;
    type: 'custom' | 'page';
    page_id: number | null;
    order: number;
    target: '_self' | '_blank';
    is_external: boolean;
    children?: MenuItemNode[];
};

type MenuData = {
    id: number;
    name: string;
    location: string;
    location_label: string | null;
    items: MenuItemNode[];
};

type LocationOption = {
    value: string;
    label: string;
};

type PageProps = {
    menu: MenuData;
    locations: LocationOption[];
    pages: PageItem[];
};

type FlatItem = {
    id: number;
    label: string;
    type: 'custom' | 'page';
    url: string | null;
    resolved_url: string;
    target: '_self' | '_blank';
    is_external: boolean;
    parent_id: number | null;
    depth: number;
    order: number;
};

function flatten(items: MenuItemNode[], depth = 0, parentId: number | null = null): FlatItem[] {
    const result: FlatItem[] = [];
    for (const item of items) {
        result.push({
            id: item.id,
            label: item.label,
            type: item.type,
            url: item.url,
            resolved_url: item.resolved_url,
            target: item.target,
            is_external: item.is_external,
            parent_id: item.parent_id,
            depth,
            order: item.order,
        });
        if (item.children && item.children.length > 0) {
            result.push(...flatten(item.children, depth + 1, item.id));
        }
    }
    return result;
}

export default function MenuEdit({ menu, locations, pages }: PageProps) {
    const [flat, setFlat] = useState<FlatItem[]>(() => flatten(menu.items));
    const [addOpen, setAddOpen] = useState(false);

    const metaForm = useForm({
        name: menu.name,
        location: menu.location,
    });

    const itemForm = useForm({
        type: 'custom' as 'custom' | 'page',
        label: '',
        url: '',
        page_id: pages[0]?.id ? String(pages[0].id) : '',
        target: '_self' as '_self' | '_blank',
    });

    function submitMeta(e: React.FormEvent): void {
        e.preventDefault();
        metaForm.patch(update.url({ menu: menu.id }), {
            preserveScroll: true,
        });
    }

    function openAdd(): void {
        itemForm.reset();
        itemForm.clearErrors();
        itemForm.setData('type', 'custom');
        itemForm.setData('target', '_self');
        itemForm.setData('page_id', pages[0]?.id ? String(pages[0].id) : '');
        setAddOpen(true);
    }

    function submitAdd(e: React.FormEvent): void {
        e.preventDefault();
        const payload: {
            type: 'custom' | 'page';
            label: string;
            target: '_self' | '_blank';
            parent_id: null;
            page_id?: number;
            url?: string;
        } = {
            type: itemForm.data.type,
            label: itemForm.data.label,
            target: itemForm.data.target,
            parent_id: null,
        };
        if (itemForm.data.type === 'page') {
            payload.page_id = Number(itemForm.data.page_id);
        } else {
            payload.url = itemForm.data.url;
        }
        router.post(store.url({ menu: menu.id }), payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setAddOpen(false),
        });
    }

    function deleteItem(item: FlatItem): void {
        if (!confirm(`¿Eliminar "${item.label}"?`)) {
            return;
        }
        router.delete(destroy.url({ menu: menu.id, item: item.id }), {
            preserveScroll: true,
        });
    }

    function move(item: FlatItem, direction: -1 | 1): void {
        const idx = flat.findIndex((i) => i.id === item.id);
        if (idx === -1) {
            return;
        }
        const siblings = flat.filter((i) => i.parent_id === item.parent_id);
        const siblingIdx = siblings.findIndex((i) => i.id === item.id);
        const newIdx = siblingIdx + direction;
        if (newIdx < 0 || newIdx >= siblings.length) {
            return;
        }
        const reordered = [...siblings];
        const [moved] = reordered.splice(siblingIdx, 1);
        reordered.splice(newIdx, 0, moved);

        const newOrder = reordered.map((i, i2) => ({ ...i, order: i2 }));
        const next = flat.map((i) => {
            if (i.parent_id !== item.parent_id) {
                return i;
            }
            const found = newOrder.find((o) => o.id === i.id);
            return found ?? i;
        });
        setFlat(next);

        const payload = newOrder.map((i, i2) => ({
            id: i.id,
            parent_id: i.parent_id,
            order: i2,
        }));
        router.post(reorder.url({ menu: menu.id }), { items: payload }, {
            preserveScroll: true,
        });
    }

    function changeParent(item: FlatItem, newParentId: number | null): void {
        if (newParentId === item.id) {
            return;
        }
        // Prevent creating cycles: newParentId cannot be a descendant of item
        if (newParentId !== null) {
            const isDescendant = (parentId: number | null): boolean => {
                if (parentId === null) {
                    return false;
                }
                if (parentId === item.id) {
                    return true;
                }
                const parent = flat.find((i) => i.id === parentId);
                return parent ? isDescendant(parent.parent_id) : false;
            };
            if (isDescendant(newParentId)) {
                return;
            }
        }

        const next = flat.map((i) =>
            i.id === item.id ? { ...i, parent_id: newParentId, depth: newParentId === null ? 0 : (flat.find((p) => p.id === newParentId)?.depth ?? 0) + 1 } : i,
        );
        setFlat(next);

        router.post(reorder.url({ menu: menu.id }), {
            items: next.map((i, idx) => ({
                id: i.id,
                parent_id: i.parent_id,
                order: idx,
            })),
        }, {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title={`Menú: ${menu.name}`} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title={menu.name}
                        description={`Ubicación: ${menu.location_label ?? menu.location}`}
                    />
                    <Button onClick={openAdd} disabled={pages.length === 0 && itemForm.data.type === 'page'}>
                        <Link2 className="mr-1 size-4" />
                        Añadir elemento
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Configuración</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitMeta} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input
                                    id="name"
                                    value={metaForm.data.name}
                                    onChange={(e) => metaForm.setData('name', e.target.value)}
                                    required
                                />
                                {metaForm.errors.name ? (
                                    <p className="text-xs text-destructive">{metaForm.errors.name}</p>
                                ) : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="location">Ubicación</Label>
                                <Select
                                    value={metaForm.data.location}
                                    onValueChange={(value) => metaForm.setData('location', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {locations.map((loc) => (
                                            <SelectItem key={loc.value} value={loc.value}>
                                                {loc.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {metaForm.errors.location ? (
                                    <p className="text-xs text-destructive">{metaForm.errors.location}</p>
                                ) : null}
                            </div>
                            <div className="flex items-end">
                                <Button type="submit" disabled={metaForm.processing}>
                                    {metaForm.processing ? (
                                        <Loader2 className="mr-1 size-4 animate-spin" />
                                    ) : null}
                                    Guardar configuración
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Elementos del menú</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {flat.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                Aún no hay elementos. Añade el primero.
                            </p>
                        ) : (
                            <ul className="divide-y rounded-md border">
                                {flat.map((item, idx) => {
                                    const siblings = flat.filter((i) => i.parent_id === item.parent_id);
                                    const siblingIdx = siblings.findIndex((i) => i.id === item.id);
                                    const isFirst = siblingIdx === 0;
                                    const isLast = siblingIdx === siblings.length - 1;
                                    const parentOptions = flat.filter((i) => i.id !== item.id);

                                    return (
                                        <li
                                            key={item.id}
                                            className="flex items-center gap-2 p-3 transition-colors hover:bg-muted/30"
                                            style={{ paddingLeft: `${0.75 + item.depth * 1.5}rem` }}
                                        >
                                            <GripVertical className="size-4 text-muted-foreground" aria-hidden="true" />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="truncate font-medium">{item.label}</span>
                                                    <span className="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                                        {item.type === 'page' ? 'Página' : 'Enlace'}
                                                    </span>
                                                    {item.target === '_blank' ? (
                                                        <span className="rounded bg-blue-100 px-1.5 py-0.5 text-xs text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                            Nueva pestaña
                                                        </span>
                                                    ) : null}
                                                </div>
                                                <a
                                                    href={item.resolved_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="mt-0.5 inline-flex items-center gap-1 truncate text-xs text-muted-foreground hover:text-foreground"
                                                >
                                                    {item.resolved_url}
                                                    {item.is_external ? (
                                                        <ExternalLink className="size-3" />
                                                    ) : null}
                                                </a>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <Select
                                                    value={item.parent_id === null ? '__root__' : String(item.parent_id)}
                                                    onValueChange={(value) =>
                                                        changeParent(item, value === '__root__' ? null : Number(value))
                                                    }
                                                >
                                                    <SelectTrigger className="h-8 w-36 text-xs">
                                                        <SelectValue placeholder="Padre" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="__root__">— Raíz —</SelectItem>
                                                        {parentOptions.map((p) => (
                                                            <SelectItem key={p.id} value={String(p.id)}>
                                                                {p.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={isFirst}
                                                    onClick={() => move(item, -1)}
                                                    aria-label="Subir"
                                                >
                                                    <ArrowUp className="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={isLast}
                                                    onClick={() => move(item, 1)}
                                                    aria-label="Bajar"
                                                >
                                                    <ArrowDown className="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => deleteItem(item)}
                                                    aria-label="Eliminar"
                                                >
                                                    <Trash2 className="size-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={addOpen} onOpenChange={setAddOpen}>
                <DialogContent>
                    <form onSubmit={submitAdd}>
                        <DialogHeader>
                            <DialogTitle>Añadir elemento</DialogTitle>
                            <DialogDescription>
                                Añade un nuevo enlace al menú.
                            </DialogDescription>
                        </DialogHeader>

                        <Tabs
                            value={itemForm.data.type}
                            onValueChange={(value) => {
                                itemForm.setData('type', value as 'custom' | 'page');
                                itemForm.clearErrors();
                            }}
                            className="py-2"
                        >
                            <TabsList className="grid w-full grid-cols-2">
                                <TabsTrigger value="custom">Enlace personalizado</TabsTrigger>
                                <TabsTrigger value="page" disabled={pages.length === 0}>
                                    Página existente
                                </TabsTrigger>
                            </TabsList>

                            <div className="space-y-4 py-4">
                                <div className="space-y-2">
                                    <Label htmlFor="label">Etiqueta</Label>
                                    <Input
                                        id="label"
                                        value={itemForm.data.label}
                                        onChange={(e) => itemForm.setData('label', e.target.value)}
                                        placeholder="Inicio"
                                        autoFocus
                                        required
                                    />
                                    {itemForm.errors.label ? (
                                        <p className="text-xs text-destructive">{itemForm.errors.label}</p>
                                    ) : null}
                                </div>

                                <TabsContent value="custom" className="mt-0 space-y-2">
                                    <Label htmlFor="url">URL</Label>
                                    <Input
                                        id="url"
                                        value={itemForm.data.url}
                                        onChange={(e) => itemForm.setData('url', e.target.value)}
                                        placeholder="/contacto o https://..."
                                        required
                                    />
                                    {itemForm.errors.url ? (
                                        <p className="text-xs text-destructive">{itemForm.errors.url}</p>
                                    ) : null}
                                </TabsContent>

                                <TabsContent value="page" className="mt-0 space-y-2">
                                    <Label htmlFor="page_id">Página</Label>
                                    <Select
                                        value={itemForm.data.page_id}
                                        onValueChange={(value) => itemForm.setData('page_id', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Selecciona una página" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {pages.map((p) => (
                                                <SelectItem key={p.id} value={String(p.id)}>
                                                    {p.title} {p.is_home ? '(inicio)' : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {itemForm.errors.page_id ? (
                                        <p className="text-xs text-destructive">{itemForm.errors.page_id}</p>
                                    ) : null}
                                </TabsContent>

                                <div className="space-y-2">
                                    <Label>Abrir en</Label>
                                    <Select
                                        value={itemForm.data.target}
                                        onValueChange={(value) =>
                                            itemForm.setData('target', value as '_self' | '_blank')
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="_self">Misma pestaña</SelectItem>
                                            <SelectItem value="_blank">Nueva pestaña</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </Tabs>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setAddOpen(false)}
                                disabled={itemForm.processing}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={itemForm.processing}>
                                {itemForm.processing ? (
                                    <Loader2 className="mr-1 size-4 animate-spin" />
                                ) : null}
                                Añadir
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
