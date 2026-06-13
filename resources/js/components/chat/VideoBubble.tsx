import { Download, Film } from 'lucide-react';
import type { ChatMessage } from '@/types/chat';
import { formatBytes } from '@/lib/chat-utils';

type VideoBubbleProps = {
    m: ChatMessage;
    isMine: boolean;
    onLightbox: (url: string) => void;
};

export function VideoBubble({ m, onLightbox }: VideoBubbleProps) {
    const url = m.attachment_url ?? '';
    if (!url) {
        return (
            <div className="flex items-center gap-2 px-3 py-2 text-muted-foreground">
                <Film className="size-4" />
                <span className="text-xs">Video no disponible</span>
            </div>
        );
    }
    return (
        <>
            <video
                src={url}
                controls
                preload="metadata"
                playsInline
                className="max-h-64 max-w-80 cursor-zoom-in rounded"
                onClick={(e) => {
                    if (e.currentTarget.paused) {
                        onLightbox(url);
                    }
                }}
            />
            <div className="flex items-center justify-between gap-2 px-3 py-1.5 text-[10px] text-muted-foreground">
                <span className="truncate">{m.attachment_name ?? 'Video'}</span>
                <span className="flex items-center gap-1.5">
                    {m.attachment_size ? <span>{formatBytes(m.attachment_size)}</span> : null}
                    <a href={url} target="_blank" rel="noopener noreferrer" download className="hover:text-foreground" title="Descargar">
                        <Download className="size-3" />
                    </a>
                </span>
            </div>
        </>
    );
}
