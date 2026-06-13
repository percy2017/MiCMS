import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Copy, Globe, Loader2, Save, Trash2, Webhook } from 'lucide-react';
import { useState } from 'react';
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
    allowed_domain: string;
    public_key: string | null;
    webhook_token: string | null;
    webhook_url: string | null;
};

type PageProps = { widget: Widget };

function embedSnippet(publicKey: string | null, webhookUrl: string | null): string {
    if (! publicKey || ! webhookUrl) return '';
    const origin = typeof window !== 'undefined' ? window.location.origin : 'https://hostbol.lat';
    return `<script src="${origin}/embed/widget/${publicKey}.js" data-channel="${publicKey}" data-webhook="${webhookUrl}" async></script>`;
}

function CopyButton({ value, label = 'Copiar' }: { value: string; label?: string }): JSX.Element {
    const [copied, setCopied] = useState(false);
    async function copy(): Promise<void> {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // ignore
        }
    }
    return (
        <Button type="button" size="sm" variant="outline" onClick={copy}>
            <Copy className="mr-1 size-3" />
            {copied ? 'Copiado' : label}
        </Button>
    );
}

export default function WidgetEdit({ widget }: PageProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

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
        allowed_domain: widget.allowed_domain ?? '',
    });

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

    const isFormValid = data.name.trim().length > 0 && data.title.trim().length > 0 && data.allowed_domain.trim().length > 0;

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

                <form onSubmit={handleSubmit}>
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

                                    <div className="grid gap-2">
                                        <Label htmlFor="allowed_domain">
                                            Dominio
                                            <span className="ml-1 text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="allowed_domain"
                                            value={data.allowed_domain}
                                            onChange={(e) => setData('allowed_domain', e.target.value)}
                                            placeholder="mitienda.com"
                                            required
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Solo este dominio podrá cargar el widget y enviar mensajes. Usa <code>*.mitienda.com</code> para permitir subdominios.
                                        </p>
                                        {errors.allowed_domain && (
                                            <p className="text-sm text-destructive">{errors.allowed_domain}</p>
                                        )}
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
                                    <CardTitle>Avatar</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
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
                                        <span>Requerir login al recargar (no al primer mensaje)</span>
                                    </label>

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

                            {! widget.is_new && widget.public_key && widget.webhook_url && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <Webhook className="size-4" />
                                            Integración
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        <div>
                                            <p className="mb-1 text-xs font-medium">Snippet de embed</p>
                                            <p className="mb-2 text-xs text-muted-foreground">
                                                Pega esto en el HTML de <span className="font-mono">{widget.allowed_domain}</span> antes de <code>&lt;/body&gt;</code>:
                                            </p>
                                            <div className="relative">
                                                <pre className="overflow-x-auto rounded-md bg-slate-950 px-3 py-2 pr-24 text-xs text-slate-100">
                                                    {embedSnippet(widget.public_key, widget.webhook_url)}
                                                </pre>
                                                <div className="absolute right-2 top-2">
                                                    <CopyButton value={embedSnippet(widget.public_key, widget.webhook_url)} />
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                            <p className="mb-1 text-xs font-medium">Webhook URL</p>
                                            <div className="flex items-center gap-2">
                                                <code className="flex-1 truncate rounded bg-muted px-2 py-1 text-xs">
                                                    {widget.webhook_url}
                                                </code>
                                                <CopyButton value={widget.webhook_url} />
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 gap-2 text-xs">
                                            <div>
                                                <p className="font-medium">Public key</p>
                                                <p className="font-mono text-muted-foreground">{widget.public_key}</p>
                                            </div>
                                            <div>
                                                <p className="font-medium">Webhook token</p>
                                                <p className="font-mono text-muted-foreground">{widget.webhook_token}</p>
                                            </div>
                                        </div>
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
