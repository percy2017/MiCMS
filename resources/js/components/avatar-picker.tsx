import { router } from '@inertiajs/react';
import { Camera, Loader2, Upload, X } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import AvatarUploadController from '@/actions/App/Http/Controllers/Media/AvatarUploadController';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type AvatarPickerProps = {
    value: number | null;
    previewUrl: string | null;
    name: string;
    onChange: (mediaId: number | null) => void;
    error?: string;
    disabled?: boolean;
    maxSizeKb?: number;
};

function readCsrfToken(): string | null {
    const match = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[2]) : null;
}

function getInitials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) {
        return '?';
    }
    if (parts.length === 1) {
        return parts[0].charAt(0).toUpperCase();
    }
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

export function AvatarPicker({
    value,
    previewUrl,
    name,
    onChange,
    error,
    disabled = false,
    maxSizeKb = 4096,
}: AvatarPickerProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);
    const [localPreview, setLocalPreview] = useState<string | null>(null);

    const displayUrl = localPreview ?? previewUrl;
    const displayError = localError ?? error;

    const onPick = useCallback(() => {
        if (disabled || uploading) {
            return;
        }
        inputRef.current?.click();
    }, [disabled, uploading]);

    const onFileChange = useCallback(
        async (e: React.ChangeEvent<HTMLInputElement>) => {
            const file = e.target.files?.[0];
            if (!file) {
                return;
            }

            if (!file.type.startsWith('image/')) {
                setLocalError('El archivo debe ser una imagen.');
                return;
            }

            if (file.size > maxSizeKb * 1024) {
                setLocalError(`La imagen no puede superar ${maxSizeKb} KB.`);
                return;
            }

            setLocalError(null);
            const tempPreview = URL.createObjectURL(file);
            setLocalPreview(tempPreview);
            setUploading(true);

            const formData = new FormData();
            formData.append('file', file);

            const csrf = readCsrfToken();

            try {
                const response = await fetch(AvatarUploadController.store.url(), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrf ? { 'X-XSRF-TOKEN': csrf } : {}),
                    },
                    body: formData,
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    const firstError = Object.values(data.errors ?? data ?? {}).flat()[0];
                    throw new Error(typeof firstError === 'string' ? firstError : 'Error al subir la imagen.');
                }

                const data = await response.json();
                onChange(data.id);
            } catch (err) {
                setLocalError(err instanceof Error ? err.message : 'Error desconocido.');
                setLocalPreview(null);
            } finally {
                setUploading(false);
                if (inputRef.current) {
                    inputRef.current.value = '';
                }
            }
        },
        [maxSizeKb, onChange],
    );

    const onClear = useCallback(() => {
        onChange(null);
        setLocalPreview(null);
        setLocalError(null);
        if (inputRef.current) {
            inputRef.current.value = '';
        }
    }, [onChange]);

    return (
        <div className="flex flex-col items-center gap-3 sm:flex-row sm:items-start">
            <div className="relative shrink-0">
                <Avatar className={cn('size-24 border-2', displayError ? 'border-destructive' : 'border-border')}>
                    {displayUrl ? (
                        <AvatarImage src={displayUrl} alt={name} />
                    ) : null}
                    <AvatarFallback className="bg-muted text-2xl text-muted-foreground">
                        {getInitials(name || '?')}
                    </AvatarFallback>
                </Avatar>
                {uploading && (
                    <div className="absolute inset-0 flex items-center justify-center rounded-full bg-background/80">
                        <Loader2 className="size-6 animate-spin text-primary" />
                    </div>
                )}
            </div>

            <div className="flex flex-1 flex-col gap-2">
                <input
                    ref={inputRef}
                    type="file"
                    accept="image/png,image/jpeg,image/jpg,image/webp,image/gif"
                    onChange={onFileChange}
                    className="hidden"
                    disabled={disabled || uploading}
                />
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onPick}
                        disabled={disabled || uploading}
                    >
                        <Upload className="mr-2 size-4" />
                        {value || localPreview ? 'Cambiar' : 'Subir'} avatar
                    </Button>
                    {(value || localPreview) && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={onClear}
                            disabled={disabled || uploading}
                        >
                            <X className="mr-2 size-4" />
                            Quitar
                        </Button>
                    )}
                </div>
                <p className="text-xs text-muted-foreground">
                    PNG, JPG, WebP o GIF. Tamaño máximo {maxSizeKb} KB.
                </p>
                {displayError && (
                    <p className="text-xs text-destructive">{displayError}</p>
                )}
            </div>
        </div>
    );
}

export default AvatarPicker;
