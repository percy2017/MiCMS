import { router, useForm } from '@inertiajs/react';
import { CopyIcon, ExternalLinkIcon, TrashIcon } from 'lucide-react';
import { useState } from 'react';
import MediaController from '@/actions/App/Http/Controllers/Media/MediaController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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

type MediaItem = {
    id: number;
    name: string;
    title: string | null;
    alt_text: string | null;
    caption: string | null;
    description: string | null;
    mime_type: string;
    human_size: string;
    width: number | null;
    height: number | null;
    url: string;
    is_image: boolean;
    is_video: boolean;
    is_audio: boolean;
    created_at_diff: string;
};

type MediaPreviewProps = {
    media: MediaItem;
};

export function MediaPreview({ media }: MediaPreviewProps) {
    return (
        <div className="overflow-hidden rounded-lg border bg-muted">
            {media.is_image ? (
                <img
                    src={media.url}
                    alt={media.alt_text ?? media.name}
                    className="mx-auto max-h-[60vh] w-full object-contain"
                />
            ) : media.is_video ? (
                <video
                    src={media.url}
                    controls
                    className="mx-auto max-h-[60vh] w-full"
                />
            ) : media.is_audio ? (
                <div className="flex flex-col items-center gap-3 p-8">
                    <audio
                        src={media.url}
                        controls
                        className="w-full max-w-md"
                    />
                </div>
            ) : (
                <div className="flex flex-col items-center justify-center gap-2 p-12 text-sm text-muted-foreground">
                    <p className="font-medium">{media.mime_type}</p>
                    <p>{media.human_size}</p>
                    <a
                        href={media.url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 text-primary underline-offset-4 hover:underline"
                    >
                        Abrir en nueva pestaña
                        <ExternalLinkIcon className="size-3" />
                    </a>
                </div>
            )}
        </div>
    );
}

type MediaFormProps = {
    media: MediaItem;
};

export function MediaMetadataForm({ media }: MediaFormProps) {
    const form = useForm({
        title: media.title ?? '',
        alt_text: media.alt_text ?? '',
        caption: media.caption ?? '',
        description: media.description ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(MediaController.update.url(media.id), {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="title">Título</Label>
                <Input
                    id="title"
                    name="title"
                    value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)}
                    placeholder="Título descriptivo"
                />
                <InputError message={form.errors.title} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="alt_text">Texto alternativo</Label>
                <Input
                    id="alt_text"
                    name="alt_text"
                    value={form.data.alt_text}
                    onChange={(e) => form.setData('alt_text', e.target.value)}
                    placeholder="Descripción para accesibilidad"
                />
                <InputError message={form.errors.alt_text} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="caption">Leyenda</Label>
                <Input
                    id="caption"
                    name="caption"
                    value={form.data.caption}
                    onChange={(e) => form.setData('caption', e.target.value)}
                />
                <InputError message={form.errors.caption} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Descripción</Label>
                <textarea
                    id="description"
                    name="description"
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    rows={4}
                    className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="flex items-center gap-4">
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Guardando…' : 'Guardar'}
                </Button>
            </div>
        </form>
    );
}

type MediaSidebarProps = {
    media: MediaItem;
};

export function MediaSidebar({ media }: MediaSidebarProps) {
    const [copied, setCopied] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const copy = () => {
        navigator.clipboard.writeText(media.url).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

    const remove = () => {
        setDeleting(true);
        router.delete(MediaController.destroy.url(media.id), {
            onFinish: () => {
                setDeleting(false);
                setConfirmDelete(false);
            },
        });
    };

    return (
        <>
            <div className="space-y-4 rounded-lg border p-4 text-sm">
                <Heading variant="small" title="Detalles" />
                <dl className="space-y-2">
                     <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">Nombre</dt>
                        <dd className="truncate font-mono text-xs">
                            {media.name}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">Tipo</dt>
                        <dd className="truncate font-mono text-xs">
                            {media.mime_type}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">Tamaño</dt>
                        <dd>{media.human_size}</dd>
                    </div>
                    {media.width !== null && media.height !== null && (
                        <div className="flex justify-between gap-2">
                            <dt className="text-muted-foreground">
                                Dimensiones
                            </dt>
                            <dd>
                                {media.width} × {media.height}
                            </dd>
                        </div>
                    )}
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">Subido</dt>
                        <dd>{media.created_at_diff}</dd>
                    </div>
                    
                </dl>

                <div className="flex flex-col gap-2 pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={copy}
                        className="w-full"
                    >
                        <CopyIcon className="mr-2 size-4" />
                        {copied ? '¡Copiado!' : 'Copiar URL'}
                    </Button>
                    <Button
                        asChild
                        type="button"
                        variant="outline"
                        className="w-full"
                    >
                        <a href={media.url} target="_blank" rel="noreferrer">
                            <ExternalLinkIcon className="mr-2 size-4" />
                            Abrir original
                        </a>
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        className="w-full"
                        onClick={() => setConfirmDelete(true)}
                    >
                        <TrashIcon className="mr-2 size-4" />
                        Eliminar
                    </Button>
                </div>
            </div>

            <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>¿Eliminar este archivo?</DialogTitle>
                        <DialogDescription>
                            Se eliminará el archivo del disco y el registro de
                            la base de datos. Esta acción no se puede deshacer.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirmDelete(false)}
                            disabled={deleting}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={remove}
                            disabled={deleting}
                        >
                            {deleting ? 'Eliminando…' : 'Eliminar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
