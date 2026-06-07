import { router } from '@inertiajs/react';
import { UploadIcon } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import MediaUploadController from '@/actions/App/Http/Controllers/Media/MediaUploadController';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

type MediaUploaderProps = {
    className?: string;
    maxSize: number;
};

type StagedFile = {
    file: File;
    preview: string;
};

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function MediaUploader({ className, maxSize }: MediaUploaderProps) {
    const [open, setOpen] = useState(false);
    const [staged, setStaged] = useState<StagedFile | null>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const onDrop = useCallback(
        (accepted: File[]) => {
            setError(null);
            const file = accepted[0];

            if (!file) {
                return;
            }

            if (file.size > maxSize) {
                setError(
                    `El archivo (${formatBytes(file.size)}) supera el límite permitido de ${formatBytes(maxSize)}.`,
                );

                return;
            }

            setStaged({
                file,
                preview: URL.createObjectURL(file),
            });
            setOpen(true);
        },
        [maxSize],
    );

    const {
        getRootProps,
        getInputProps,
        isDragActive,
        open: openPicker,
    } = useDropzone({
        onDrop,
        multiple: false,
        noClick: true,
        noKeyboard: true,
    });

    const handleUpload = () => {
        if (!staged) {
            return;
        }

        setUploading(true);
        setError(null);

        router.post(
            MediaUploadController.store.url(),
            {
                file: staged.file,
                forceFormData: true,
            } as never,
            {
                forceFormData: true,
                onSuccess: () => {
                    setUploading(false);
                    setOpen(false);
                    setStaged(null);
                },
                onError: (errors) => {
                    setUploading(false);
                    const firstError = Object.values(errors)[0];
                    setError(
                        Array.isArray(firstError)
                            ? firstError[0]
                            : String(firstError),
                    );
                },
            },
        );
    };

    const handleCancel = () => {
        setOpen(false);
        setStaged(null);
        setError(null);
    };

    return (
        <>
            <div
                {...getRootProps({
                    onClick: (e) => e.preventDefault(),
                })}
                className={cn(
                    'relative rounded-lg border-2 border-dashed border-sidebar-border/70 transition-colors',
                    isDragActive && 'border-primary bg-primary/5',
                    className,
                )}
            >
                <input {...getInputProps()} />
                <div className="flex flex-col items-center justify-center gap-2 px-4 py-10 text-center">
                    <UploadIcon className="size-8 text-muted-foreground" />
                    <p className="text-sm text-muted-foreground">
                        {isDragActive
                            ? 'Suelta el archivo aquí…'
                            : 'Arrastra un archivo aquí o'}
                    </p>
                    <button
                        type="button"
                        onClick={openPicker}
                        className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                    >
                        selecciónalo desde tu equipo
                    </button>
                    <p className="text-xs text-muted-foreground">
                        Tamaño máximo: {formatBytes(maxSize)}
                    </p>
                    {error && (
                        <p className="text-sm text-destructive">{error}</p>
                    )}
                </div>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Subir archivo</DialogTitle>
                        <DialogDescription>
                            {staged?.file.name} —{' '}
                            {(staged?.file.size ?? 0) / 1024 < 1024
                                ? `${((staged?.file.size ?? 0) / 1024).toFixed(1)} KB`
                                : `${((staged?.file.size ?? 0) / 1024 / 1024).toFixed(1)} MB`}
                        </DialogDescription>
                    </DialogHeader>

                    {staged && (
                        <div className="max-h-64 overflow-hidden rounded-md border bg-muted">
                            {staged.file.type.startsWith('image/') ? (
                                <img
                                    src={staged.preview}
                                    alt={staged.file.name}
                                    className="mx-auto max-h-64 object-contain"
                                />
                            ) : (
                                <div className="flex h-32 items-center justify-center text-sm text-muted-foreground">
                                    {staged.file.type || 'archivo'}
                                </div>
                            )}
                        </div>
                    )}

                    {error && (
                        <p className="text-sm text-destructive">{error}</p>
                    )}

                    <DialogFooter>
                        <button
                            type="button"
                            onClick={handleCancel}
                            disabled={uploading}
                            className="rounded-md border px-4 py-2 text-sm font-medium hover:bg-accent disabled:opacity-50"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            onClick={handleUpload}
                            disabled={uploading}
                            className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                        >
                            {uploading ? 'Subiendo…' : 'Subir'}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
