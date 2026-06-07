import { router } from '@inertiajs/react';
import { ImageIcon, Loader2, Search, UploadIcon, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import MediaUploadController from '@/actions/App/Http/Controllers/Media/MediaUploadController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type MediaItem = {
    id: number;
    name: string;
    url: string;
    mime_type: string;
    is_image: boolean;
    is_video: boolean;
};

type PickerCustomFieldProps = {
    name: string;
    value: string;
    onChange: (value: string) => void;
    field: { label?: string };
};

type MediaResponse = {
    media?: { data: MediaItem[] };
    max_size?: number;
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

export function MediaPickerField({
    name,
    value,
    onChange,
    field,
}: PickerCustomFieldProps) {
    const currentUrl = value ?? '';
    const [open, setOpen] = useState(false);
    const [tab, setTab] = useState<'library' | 'upload'>('library');
    const [search, setSearch] = useState('');
    const [items, setItems] = useState<MediaItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [selectedName, setSelectedName] = useState<string>('');
    const [maxSize, setMaxSize] = useState<number>(50 * 1024 * 1024);
    const [staged, setStaged] = useState<File | null>(null);
    const [stagedPreview, setStagedPreview] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const stagedUrlRef = useRef<string | null>(null);

    const isVideo =
        name === 'src' &&
        (field.label?.toLowerCase().includes('video') ?? false);
    const type = isVideo ? 'video' : 'image';

    const loadMedia = useCallback(async (): Promise<void> => {
        setLoading(true);
        try {
            const url = `/admin/media?type=${type}&per_page=60`;
            const res = await fetch(url, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }
            const data = (await res.json()) as MediaResponse;
            setItems(data.media?.data ?? []);
            if (data.max_size) {
                setMaxSize(data.max_size);
            }
        } catch (err) {
            console.error('MediaPickerField: failed to load media', err);
            setItems([]);
        } finally {
            setLoading(false);
        }
    }, [type]);

    useEffect(() => {
        if (open) {
            void loadMedia();
        }
    }, [open, loadMedia]);

    useEffect(() => {
        return () => {
            if (stagedUrlRef.current) {
                URL.revokeObjectURL(stagedUrlRef.current);
            }
        };
    }, []);

    const onDrop = useCallback(
        (accepted: File[]) => {
            const file = accepted[0];
            if (!file) {
                return;
            }
            setUploadError(null);
            if (file.size > maxSize) {
                setUploadError(
                    `El archivo (${formatBytes(file.size)}) supera el límite de ${formatBytes(maxSize)}.`,
                );
                return;
            }
            if (stagedUrlRef.current) {
                URL.revokeObjectURL(stagedUrlRef.current);
            }
            const preview = URL.createObjectURL(file);
            stagedUrlRef.current = preview;
            setStaged(file);
            setStagedPreview(preview);
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

    function handleSelect(item: MediaItem): void {
        setSelectedName(item.name);
        onChange(item.url);
        setOpen(false);
    }

    function handleClear(): void {
        setSelectedName('');
        onChange('');
    }

    function handleOpenChange(next: boolean): void {
        setOpen(next);
        if (!next) {
            setTab('library');
            setStaged(null);
            setStagedPreview(null);
            setUploadError(null);
            if (stagedUrlRef.current) {
                URL.revokeObjectURL(stagedUrlRef.current);
                stagedUrlRef.current = null;
            }
        }
    }

    function handleUpload(): void {
        if (!staged) {
            return;
        }
        setUploading(true);
        setUploadError(null);
        router.post(
            MediaUploadController.store.url(),
            { file: staged, forceFormData: true } as never,
            {
                forceFormData: true,
                onSuccess: () => {
                    setUploading(false);
                    setStaged(null);
                    if (stagedUrlRef.current) {
                        URL.revokeObjectURL(stagedUrlRef.current);
                        stagedUrlRef.current = null;
                    }
                    setStagedPreview(null);
                    setTab('library');
                    void loadMedia();
                },
                onError: (errors) => {
                    setUploading(false);
                    const first = Object.values(errors)[0];
                    setUploadError(
                        Array.isArray(first) ? first[0] : String(first),
                    );
                },
            },
        );
    }

    const filtered = items.filter((item) =>
        item.name.toLowerCase().includes(search.toLowerCase()),
    );

    return (
        <div className="space-y-2">
            <div className="text-xs font-medium text-muted-foreground">
                {field.label ?? 'Medio'}
            </div>

            {currentUrl ? (
                <div className="flex items-center gap-3 rounded-md border bg-muted/30 p-2">
                    <div className="h-12 w-12 shrink-0 overflow-hidden rounded bg-muted">
                        <img
                            src={currentUrl}
                            alt={selectedName || 'preview'}
                            className="h-full w-full object-cover"
                        />
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium">
                            {selectedName || 'Medio seleccionado'}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {currentUrl}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={handleClear}
                        className="size-7"
                    >
                        <X className="size-4" />
                    </Button>
                </div>
            ) : null}

            <div className="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setOpen(true)}
                    className="flex-1"
                >
                    <ImageIcon className="mr-1 size-4" />
                    {currentUrl ? 'Cambiar' : 'Seleccionar de Medios'}
                </Button>
            </div>

            <Dialog open={open} onOpenChange={handleOpenChange}>
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Seleccionar medio</DialogTitle>
                        <DialogDescription>
                            Elige una imagen o video de tu biblioteca o sube
                            uno nuevo.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex gap-1 border-b">
                        <button
                            type="button"
                            onClick={() => setTab('library')}
                            className={cn(
                                'border-b-2 px-3 py-1.5 text-sm font-medium transition-colors',
                                tab === 'library'
                                    ? 'border-primary text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            Biblioteca
                        </button>
                        <button
                            type="button"
                            onClick={() => setTab('upload')}
                            className={cn(
                                'border-b-2 px-3 py-1.5 text-sm font-medium transition-colors',
                                tab === 'upload'
                                    ? 'border-primary text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            Subir
                        </button>
                    </div>

                    {tab === 'library' ? (
                        <>
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Buscar medios..."
                                    value={search}
                                    onChange={(e) =>
                                        setSearch(e.target.value)
                                    }
                                    className="pl-9"
                                />
                            </div>

                            <div className="max-h-[400px] overflow-y-auto">
                                {loading ? (
                                    <div className="flex items-center justify-center py-12">
                                        <Loader2 className="size-6 animate-spin text-muted-foreground" />
                                    </div>
                                ) : filtered.length === 0 ? (
                                    <div className="py-12 text-center text-sm text-muted-foreground">
                                        No hay medios disponibles. Cambia a la
                                        pestaña &quot;Subir&quot; para añadir
                                        uno.
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5">
                                        {filtered.map((item) => (
                                            <button
                                                key={item.id}
                                                type="button"
                                                onClick={() =>
                                                    handleSelect(item)
                                                }
                                                className={cn(
                                                    'group relative aspect-square overflow-hidden rounded-md border-2 transition-colors',
                                                    currentUrl === item.url
                                                        ? 'border-primary'
                                                        : 'border-transparent hover:border-muted-foreground/50',
                                                )}
                                            >
                                                {item.is_image ? (
                                                    <img
                                                        src={item.url}
                                                        alt={item.name}
                                                        className="h-full w-full object-cover"
                                                    />
                                                ) : item.is_video ? (
                                                    <video
                                                        src={item.url}
                                                        className="h-full w-full object-cover"
                                                        muted
                                                    />
                                                ) : (
                                                    <div className="flex h-full w-full items-center justify-center bg-muted">
                                                        <ImageIcon className="size-8 text-muted-foreground" />
                                                    </div>
                                                )}
                                                <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-1.5 opacity-0 transition-opacity group-hover:opacity-100">
                                                    <p className="truncate text-xs text-white">
                                                        {item.name}
                                                    </p>
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </>
                    ) : (
                        <div className="space-y-3">
                            <div
                                {...getRootProps({
                                    onClick: (e) => e.preventDefault(),
                                })}
                                className={cn(
                                    'rounded-md border-2 border-dashed border-sidebar-border/70 p-6 text-center text-sm transition-colors',
                                    isDragActive &&
                                        'border-primary bg-primary/5',
                                )}
                            >
                                <input {...getInputProps()} />
                                <UploadIcon className="mx-auto size-6 text-muted-foreground" />
                                <p className="mt-2 text-muted-foreground">
                                    {isDragActive
                                        ? 'Suelta el archivo aquí…'
                                        : 'Arrastra un archivo o'}
                                </p>
                                <button
                                    type="button"
                                    onClick={openPicker}
                                    className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                                >
                                    selecciónalo desde tu equipo
                                </button>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Tamaño máximo: {formatBytes(maxSize)}
                                </p>
                            </div>

                            {stagedPreview && staged ? (
                                <div className="rounded-md border bg-muted/30 p-3">
                                    <div className="flex items-center gap-3">
                                        <div className="h-12 w-12 shrink-0 overflow-hidden rounded bg-muted">
                                            {staged.type.startsWith('image/') ? (
                                                <img
                                                    src={stagedPreview}
                                                    alt={staged.name}
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-full w-full items-center justify-center text-xs text-muted-foreground">
                                                    {staged.type || 'archivo'}
                                                </div>
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {staged.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatBytes(staged.size)}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            onClick={handleUpload}
                                            disabled={uploading}
                                            size="sm"
                                        >
                                            {uploading ? (
                                                <Loader2 className="mr-1 size-4 animate-spin" />
                                            ) : (
                                                <UploadIcon className="mr-1 size-4" />
                                            )}
                                            {uploading
                                                ? 'Subiendo…'
                                                : 'Subir y seleccionar'}
                                        </Button>
                                    </div>
                                </div>
                            ) : null}

                            {uploadError ? (
                                <p className="text-sm text-destructive">
                                    {uploadError}
                                </p>
                            ) : null}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}

export default MediaPickerField;
