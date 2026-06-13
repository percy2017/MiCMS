import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Copy, Globe, Loader2, Save, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
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
import { admin } from '@/routes';

type Widget = {
    id: number | null;
    is_new: boolean;
    enabled: boolean;
    name: string;
    title: string;
    subtitle: string | null;
    greeting: string | null;
    position: 'left' | 'right';
    avatar_media_id: number | null;
    avatar_url: string | null;
    require_auth: boolean;
    show_typing: boolean;
    offline_message: string | null;
    allowed_domains: string[];
    public_key: string | null;
};

type PageProps = { widget: Widget };

function normalize(d: string): string {
    return d.trim().replace(/^https?:\/\//i, '').replace(/\/+$/, '');
}

function embedSnippet(publicKey: string | null): string {
    if (! publicKey) return '';
    const origin = typeof window !== 'undefined' ? window.location.origin : 'https://hostbol.lat';
    return `<script src="${origin}/embed/widget/${publicKey}.js" data-channel="${publicKey}" async></script>`;
}

export default function WidgetEdit({ widget }: PageProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [domainInput, setDomainInput] = useState('');
    const [copied, setCopied] = useState(false);

    const { data, setData, processing, errors } = useForm({
        enabled: widget.enabled,
        name: widget.name,
        title: widget.title,
        subtitle: widget.subtitle ?? '',
        greeting: widget.greeting ?? '',
        position: widget.position,
        avatar_media_id: widget.avatar_media_id,
        require_auth: widget.require_auth,
        show_typing: widget.show_typing,
        offline_message: widget.offline_message ?? '',
        allowed_domains: widget.allowed_domains,
    });

    const domains = data.allowed_domains;

    function addDomain(): void {
        const d = normalize(domainInput);
        if (d === '') return;
        if (domains.includes(d)) {
            setDomainInput('');
            return;
        }
        setData('allowed_domains', [...domains, d]);
        setDomainInput('');
    }

    function removeDomain(d: string): void {
        setData(
            'allowed_domains',
            domains.filter((x) => x !== d),
        );
    }

    function handleSubmit(e: React.FormEvent): void {
        e.preventDefault();
        if (widget.is_new) {
            router.post('/admin/canales/web-widget', data, { preserveScroll: true });
        } else {
            router.patch(`/admin/canales/web-widget/${widget.id}`, data, { preserveScroll: true });
        }
    }

    function confirmDelete(): void {
        if (! widget.id) return;
        setDeleting(true);
        router.delete(`/admin/canales/web-widget/${widget.id}`, {
            preserveScroll: true,
            onFinish: () => setDeleting(false),
        });
    }

    async function copySnippet(): Promise<void> {
        if (! widget.public_key) return;
        try {
            await navigator.clipboard.writeText(embedSnippet(widget.public_key));
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // ignore
        }
    }

    const submitUrl = widget.is_new
        ? '/admin/canales/web-widget'
        : `/admin/canales/web-widget/${widget.id}`;

    const isFormValid = useMemo(() => data.name.trim().length > 0 && data.title.trim().length > 0, [data.name, data.title]);

    return (
        <>
            <Head title={widget.is_new ? 'Nuevo widget' : `Widget: ${widget.name}`} />
            <div className="h-full min-h-0 space-y-4 overflow-y-auto p-4">
                <div className="flex items-center gap-3">
                    <Button asChild variant="ghost" size="sm">
                        <Link href="/admin/canales/web-widget">
                            <ArrowLeft className="mr-1 size-4" />
                            Volver
                        </Link>
                    </Button>
                </div>

                <form onSubmit={handleSubmit} action={submitUrl} method={widget.is_new ? 'post' : 'patch'}>
                    <input type="hidden" name="_method" value={widget.is_new ? 'post' : 'patch'} />

                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Globe className="size-4 text-[#2563eb]" />
                                        General
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Nombre interno</Label>
                                        <Input
                                            id="name"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            placeholder="Ej. Tienda Principal"
                                            required
                                        />
                                        {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                                    </div>

                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={data.enabled}
                                            onChange={(e) => setData('enabled', e.target.checked)}
                                        />
                                        <span>Widget habilitado</span>
                                    </label>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Apariencia del chat</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="title">Título</Label>
                                        <Input
                                            id="title"
                                            value={data.title}
                                            onChange={(e) => setData('title', e.target.value)}
                                            required
                                        />
                                        {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="subtitle">Subtítulo</Label>
                                        <Input
                                            id="subtitle"
                                            value={data.subtitle}
                                            onChange={(e) => setData('subtitle', e.target.value)}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="greeting">Saludo inicial</Label>
                                        <textarea
                                            id="greeting"
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            value={data.greeting}
                                            onChange={(e) => setData('greeting', e.target.value)}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Posición</Label>
                                        <div className="flex gap-4 text-sm">
                                            <label className="flex items-center gap-2">
                                                <input
                                                    type="radio"
                                                    checked={data.position === 'left'}
                                                    onChange={() => setData('position', 'left')}
                                                />
                                                Inferior izquierda
                                            </label>
                                            <label className="flex items-center gap-2">
                                                <input
                                                    type="radio"
                                                    checked={data.position === 'right'}
                                                    onChange={() => setData('position', 'right')}
                                                />
                                                Inferior derecha
                                            </label>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Dominios permitidos</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <p className="text-xs text-muted-foreground">
                                        Lista de dominios donde el widget puede cargarse. Si está vacío, se permite en cualquier dominio.
                                    </p>

                                    <div className="flex gap-2">
                                        <Input
                                            value={domainInput}
                                            onChange={(e) => setDomainInput(e.target.value)}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    e.preventDefault();
                                                    addDomain();
                                                }
                                            }}
                                            placeholder="mitienda.com"
                                        />
                                        <Button type="button" variant="outline" onClick={addDomain}>
                                            Añadir
                                        </Button>
                                    </div>

                                    {domains.length === 0 ? (
                                        <p className="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                            ⚠ Sin dominios. El script podrá inyectarse en cualquier sitio (menos seguro).
                                        </p>
                                    ) : (
                                        <div className="flex flex-wrap gap-2">
                                            {domains.map((d) => (
                                                <span
                                                    key={d}
                                                    className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700"
                                                >
                                                    {d}
                                                    <button
                                                        type="button"
                                                        onClick={() => removeDomain(d)}
                                                        className="ml-1 text-blue-700/70 hover:text-blue-900"
                                                        aria-label={`Quitar ${d}`}
                                                    >
                                                        ×
                                                    </button>
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Apariencia</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label>Avatar</Label>
                                        {widget.avatar_url ? (
                                            <div className="flex items-center gap-3">
                                                <img
                                                    src={widget.avatar_url}
                                                    alt=""
                                                    className="size-12 rounded-full border object-cover"
                                                />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => setData('avatar_media_id', null)}
                                                >
                                                    Quitar
                                                </Button>
                                            </div>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                Para seleccionar un avatar, primero súbelo en{' '}
                                                <Link href="/admin/media" className="underline">
                                                    /admin/media
                                                </Link>{' '}
                                                y pega aquí su ID.
                                            </p>
                                        )}
                                        <Input
                                            type="number"
                                            placeholder="ID del media (opcional)"
                                            value={data.avatar_media_id ?? ''}
                                            onChange={(e) =>
                                                setData('avatar_media_id', e.target.value ? Number(e.target.value) : null)
                                            }
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Comportamiento</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={data.require_auth}
                                            onChange={(e) => setData('require_auth', e.target.checked)}
                                        />
                                        <span>Requerir inicio de sesión/registro para chatear</span>
                                    </label>
                                    {errors.require_auth && <p className="text-sm text-destructive">{errors.require_auth}</p>}

                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={data.show_typing}
                                            onChange={(e) => setData('show_typing', e.target.checked)}
                                        />
                                        <span>Mostrar indicador de "escribiendo..."</span>
                                    </label>

                                    <div className="grid gap-2">
                                        <Label htmlFor="offline_message">Mensaje fuera de horario</Label>
                                        <textarea
                                            id="offline_message"
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            value={data.offline_message}
                                            onChange={(e) => setData('offline_message', e.target.value)}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            {! widget.is_new && widget.public_key && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Snippet de embed</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-2">
                                        <p className="text-xs text-muted-foreground">
                                            Pega este snippet en el HTML de tu sitio (antes de <code>&lt;/body&gt;</code>):
                                        </p>
                                        <div className="relative">
                                            <pre className="overflow-x-auto rounded-md bg-slate-950 px-3 py-2 text-xs text-slate-100">
                                                {embedSnippet(widget.public_key)}
                                            </pre>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="absolute right-2 top-2"
                                                onClick={copySnippet}
                                            >
                                                <Copy className="mr-1 size-3" />
                                                {copied ? 'Copiado' : 'Copiar'}
                                            </Button>
                                        </div>
                                        <p className="text-[10px] text-muted-foreground">
                                            Public key: <span className="font-mono">{widget.public_key}</span>
                                        </p>
                                    </CardContent>
                                </Card>
                            )}

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing || ! isFormValid}>
                                    {processing ? <Loader2 className="mr-2 size-4 animate-spin" /> : <Save className="mr-2 size-4" />}
                                    {processing ? 'Guardando…' : widget.is_new ? 'Crear widget' : 'Guardar'}
                                </Button>
                                {! widget.is_new && (
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        onClick={() => setDeleteDialogOpen(true)}
                                    >
                                        <Trash2 className="mr-2 size-4" />
                                        Eliminar
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10">
                                <AlertTriangle className="size-5 text-destructive" />
                            </div>
                            <div>
                                <DialogTitle>Eliminar widget</DialogTitle>
                                <DialogDescription>Esta acción no se puede deshacer.</DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="space-y-2 text-sm text-muted-foreground">
                        <p>
                            ¿Estás seguro de eliminar <span className="font-medium text-foreground">"{widget.name}"</span>?
                        </p>
                        <p>
                            Se eliminarán <span className="font-medium text-foreground">permanentemente</span> todas las
                            conversaciones, mensajes y archivos adjuntos.
                        </p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setDeleteDialogOpen(false)} disabled={deleting}>
                            No, cancelar
                        </Button>
                        <Button type="button" variant="destructive" onClick={confirmDelete} disabled={deleting}>
                            {deleting ? <Loader2 className="mr-2 size-4 animate-spin" /> : null}
                            {deleting ? 'Eliminando...' : 'Sí, eliminar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

WidgetEdit.layout = (widget: Widget) => ({
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Canales', href: '/admin/canales' },
        { title: 'Widgets Web', href: '/admin/canales/web-widget' },
        ...(widget?.is_new
            ? [{ title: 'Nuevo', href: '/admin/canales/web-widget/nuevo' }]
            : [{ title: widget?.name ?? 'Editar', href: `/admin/canales/web-widget/${widget?.id}` }]),
    ],
});
