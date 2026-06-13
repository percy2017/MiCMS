import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, FileImage, Loader2, RotateCcw, Save, Trash2, Upload, X, Zap } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import AppLayout from '@/layouts/app-layout';
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
import { Switch } from '@/components/ui/switch';
import { WhatsAppEditor } from '@/components/ui/whatsapp-editor';
import { slugifyShortcut } from '@/lib/whatsapp-markdown';
import { admin } from '@/routes';

type Reply = {
    id: number | null;
    is_new: boolean;
    shortcut: string;
    title: string;
    content: string | null;
    category: string | null;
    media_id: number | null;
    media_url: string | null;
    media_mime: string | null;
    media_name: string | null;
    sort: number;
    enabled: boolean;
};

type PageProps = { reply: Reply };

function csrfHeaders(): Record<string, string> {
    const match = document.cookie.match(new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]*)'));
    const token = match ? decodeURIComponent(match[2]) : null;
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': token ?? '',
    };
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export default function QuickRepliesEdit({ reply }: PageProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [uploadPreview, setUploadPreview] = useState<string | null>(reply.media_url);
    const [shortcutTouched, setShortcutTouched] = useState<boolean>(!reply.is_new);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const { data, setData, processing, errors } = useForm({
        shortcut: reply.shortcut,
        title: reply.title,
        content: reply.content ?? '',
        category: reply.category ?? '',
        media_id: reply.media_id,
        sort: reply.sort,
        enabled: reply.enabled,
    });

    // Auto-slugify shortcut from title when user hasn't touched it
    useEffect(() => {
        if (shortcutTouched) return;
        const next = slugifyShortcut(data.title);
        if (next !== data.shortcut) {
            setData('shortcut', next);
        }
    }, [data.title, shortcutTouched]);

    function pickFile(): void {
        fileInputRef.current?.click();
    }

    async function onFileChange(e: React.ChangeEvent<HTMLInputElement>): Promise<void> {
        const file = e.target.files?.[0];
        if (!file) return;
        setUploading(true);

        try {
            const formData = new FormData();
            formData.append('file', file);

            const res = await fetch('/admin/media', {
                method: 'POST',
                headers: { ...csrfHeaders(), 'X-CSRF-TOKEN': csrfToken() },
                body: formData,
                credentials: 'same-origin',
            });

            const json = await res.json();
            if (!res.ok || !json?.media?.id) {
                throw new Error(json?.message ?? 'Error al subir archivo');
            }

            const m = json.media;
            setData('media_id', m.id);
            setUploadPreview(m.url ?? URL.createObjectURL(file));
            toast.success('Archivo subido');
        } catch (err) {
            const message = err instanceof Error ? err.message : 'No se pudo subir el archivo';
            toast.error(message);
        } finally {
            setUploading(false);
            if (fileInputRef.current) fileInputRef.current.value = '';
        }
    }

    function clearMedia(): void {
        setData('media_id', null);
        setUploadPreview(null);
    }

    function handleSubmit(e: React.FormEvent): void {
        e.preventDefault();
        if (reply.is_new) {
            router.post('/admin/canales/respuestas-rapidas', data, { preserveScroll: true });
        } else {
            router.patch(`/admin/canales/respuestas-rapidas/${reply.id}`, data, { preserveScroll: true });
        }
    }

    function confirmDelete(): void {
        if (!reply.id) return;
        setDeleting(true);
        router.delete(`/admin/canales/respuestas-rapidas/${reply.id}`, {
            preserveScroll: true,
            onFinish: () => setDeleting(false),
        });
    }

    const isFormValid =
        data.shortcut.trim().length > 0 && data.title.trim().length > 0 && (data.content.trim().length > 0 || data.media_id !== null);

    return (
        <>
            <Head title={reply.is_new ? 'Nueva respuesta rápida' : `/${reply.shortcut}`} />
            <div className="h-full min-h-0 space-y-4 overflow-y-auto p-4">
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Zap className="size-4 text-yellow-600" />
                                    Datos básicos
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="title">Título descriptivo</Label>
                                    <Input
                                        id="title"
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                        placeholder="Saludo inicial"
                                        maxLength={100}
                                        required
                                    />
                                    {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="shortcut">
                                            Shortcut
                                            <span className="ml-1 text-xs text-muted-foreground">(sin la barra)</span>
                                        </Label>
                                        {!shortcutTouched && data.title && (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-700">
                                                Auto desde título
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <span className="text-sm text-muted-foreground">/</span>
                                        <Input
                                            id="shortcut"
                                            value={data.shortcut}
                                            onChange={(e) => {
                                                setShortcutTouched(true);
                                                setData('shortcut', slugifyShortcut(e.target.value));
                                            }}
                                            placeholder="saludo"
                                            maxLength={50}
                                            required
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => {
                                                setShortcutTouched(false);
                                                setData('shortcut', slugifyShortcut(data.title));
                                            }}
                                            disabled={!data.title}
                                            title="Regenerar desde el título"
                                            className="shrink-0"
                                        >
                                            <RotateCcw className="size-4" />
                                        </Button>
                                    </div>
                                    {errors.shortcut && <p className="text-sm text-destructive">{errors.shortcut}</p>}
                                    <p className="text-xs text-muted-foreground">
                                        Los admins escribirán <code className="rounded bg-muted px-1">/{data.shortcut || 'shortcut'}</code> en el chat para invocar esta respuesta.
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="category">Categoría</Label>
                                    <Input
                                        id="category"
                                        value={data.category}
                                        onChange={(e) => setData('category', e.target.value)}
                                        placeholder="saludos, soporte, precios..."
                                        maxLength={50}
                                    />
                                    <p className="text-xs text-muted-foreground">Opcional. Útil para filtrar y agrupar.</p>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Archivo adjunto</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        onChange={onFileChange}
                                        accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                                        className="hidden"
                                    />

                                    {uploadPreview ? (
                                        <div className="flex items-center gap-3 rounded-md border bg-muted/30 p-3">
                                            {uploadPreview.startsWith('data:image') || /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(uploadPreview) ? (
                                                <img src={uploadPreview} alt="" className="size-12 shrink-0 rounded object-cover" />
                                            ) : (
                                                <div className="flex size-12 shrink-0 items-center justify-center rounded bg-muted text-muted-foreground">
                                                    <FileImage className="size-5" />
                                                </div>
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">{reply.media_name ?? 'archivo'}</p>
                                                {reply.media_mime && (
                                                    <p className="truncate text-xs text-muted-foreground">{reply.media_mime}</p>
                                                )}
                                            </div>
                                            <Button type="button" size="icon" variant="ghost" onClick={clearMedia} title="Quitar">
                                                <X className="size-4" />
                                            </Button>
                                        </div>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="w-full"
                                            onClick={pickFile}
                                            disabled={uploading}
                                        >
                                            {uploading ? (
                                                <Loader2 className="mr-2 size-4 animate-spin" />
                                            ) : (
                                                <Upload className="mr-2 size-4" />
                                            )}
                                            {uploading ? 'Subiendo...' : 'Subir archivo'}
                                        </Button>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Imagen, video, audio, PDF, documento. Opcional: si no subes archivo, debes escribir contenido.
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Estado</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <label className="flex cursor-pointer items-center justify-between gap-3 rounded-md border bg-card px-3 py-2.5 transition hover:bg-muted/30">
                                        <div className="grid gap-0.5">
                                            <span className="text-sm font-medium">Activo</span>
                                            <span className="text-xs text-muted-foreground">Si está inactivo no aparece en el dropdown</span>
                                        </div>
                                        <Switch checked={data.enabled} onCheckedChange={(v) => setData('enabled', v)} />
                                    </label>
                                </CardContent>
                            </Card>

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing || !isFormValid}>
                                    {processing ? <Loader2 className="mr-2 size-4 animate-spin" /> : <Save className="mr-2 size-4" />}
                                    {processing ? 'Guardando…' : reply.is_new ? 'Crear respuesta' : 'Guardar'}
                                </Button>
                                {!reply.is_new && (
                                    <Button type="button" variant="destructive" onClick={() => setDeleteDialogOpen(true)}>
                                        <Trash2 className="mr-2 size-4" />
                                        Eliminar
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle>Contenido del mensaje</CardTitle>
                            <p className="text-xs text-muted-foreground">
                                Usa el formato de WhatsApp: <code className="rounded bg-muted px-1">*negrita*</code>{' '}
                                <code className="rounded bg-muted px-1">_cursiva_</code>{' '}
                                <code className="rounded bg-muted px-1">~tachado~</code>{' '}
                                <code className="rounded bg-muted px-1">```código```</code>{' '}
                                <code className="rounded bg-muted px-1">[link](https://...)</code>
                            </p>
                        </CardHeader>
                        <CardContent>
                            <WhatsAppEditor
                                id="quick-reply-content"
                                value={data.content}
                                onChange={(v) => setData('content', v)}
                                placeholder="Hola, *bienvenido* a nuestro servicio. ¿En qué podemos ayudarte?"
                                rows={8}
                                maxLength={5000}
                                error={errors.content}
                                help="Deja vacío si la respuesta es solo un archivo adjunto."
                            />
                        </CardContent>
                    </Card>
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
                                <DialogTitle>Eliminar respuesta rápida</DialogTitle>
                                <DialogDescription>Esta acción no se puede deshacer.</DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="space-y-2 text-sm text-muted-foreground">
                        <p>
                            ¿Eliminar <code className="rounded bg-muted px-1">/{reply.shortcut}</code> "{reply.title}"?
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

function buildBreadcrumbs(reply: Reply | undefined): { title: string; href: string }[] {
    return [
        { title: 'Admin', href: admin() },
        { title: 'Canales', href: '/admin/canales' },
        { title: 'Respuestas rápidas', href: '/admin/canales/respuestas-rapidas' },
        ...(reply?.is_new
            ? [{ title: 'Nueva', href: '/admin/canales/respuestas-rapidas/nueva' }]
            : [{ title: `/${reply?.shortcut ?? 'editar'}`, href: `/admin/canales/respuestas-rapidas/${reply?.id ?? ''}/edit` }]),
    ];
}

function QuickRepliesEditLayout({ children }: { children: React.ReactNode }): React.ReactElement {
    const { props } = usePage<{ reply: Reply }>();
    return <AppLayout breadcrumbs={buildBreadcrumbs(props.reply)}>{children}</AppLayout>;
}

QuickRepliesEdit.layout = (page: React.ReactNode): React.ReactElement => <QuickRepliesEditLayout>{page}</QuickRepliesEditLayout>;
