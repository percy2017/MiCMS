import { Download, ImageOff } from 'lucide-react';
import type { ChatMessage } from '@/types/chat';
import { formatBytes } from '@/lib/chat-utils';
import { cn } from '@/lib/utils';

type ImageBubbleProps = {
    m: ChatMessage;
    isMine: boolean;
    onLightbox: (url: string) => void;
};

export function ImageBubble({ m, isMine, onLightbox }: ImageBubbleProps) {
    const url = m.attachment_url ?? '';
    const hasUrl = url !== '';

    if (!hasUrl) {
        return (
            <div className="flex items-center gap-2 px-3 py-2 text-muted-foreground">
                <ImageOff className="size-4" />
                <span className="text-xs">Imagen no disponible</span>
            </div>
        );
    }

    return (
        <>
            <img
                src={url}
                alt={m.attachment_name ?? 'imagen'}
                className="max-h-48 max-w-64 cursor-zoom-in rounded object-cover"
                loading="lazy"
                onClick={() => onLightbox(url)}
                onError={(e) => {
                    (e.currentTarget as HTMLImageElement).style.opacity = '0.3';
                }}
            />
            {m.attachment_name && (
                <div className={cn('flex items-center justify-between gap-2 px-3 py-1.5 text-[10px] text-muted-foreground')}>
                    <span className="truncate">{m.attachment_name}</span>
                    <span className="flex items-center gap-1.5">
                        {m.attachment_size ? <span>{formatBytes(m.attachment_size)}</span> : null}
                        <a href={url} target="_blank" rel="noopener noreferrer" download className="hover:text-foreground" title="Descargar">
                            <Download className="size-3" />
                        </a>
                    </span>
                </div>
            )}
        </>
    );
}
