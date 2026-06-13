import { Download, File as FileIconDefault, FileArchive, FileAudio, FileImage, FileText, FileVideo, Sticker } from 'lucide-react';
import type { ChatMessage } from '@/types/chat';
import { formatBytes } from '@/lib/chat-utils';

type FileBubbleProps = {
    m: ChatMessage;
    isMine: boolean;
    onLightbox?: (url: string) => void;
};

function FileIcon({ mime }: { mime: string | null }) {
    if (!mime) {
        return <FileIconDefault className="size-5" />;
    }
    if (mime.startsWith('image/')) {
        return <FileImage className="size-5" />;
    }
    if (mime.startsWith('video/')) {
        return <FileVideo className="size-5" />;
    }
    if (mime.startsWith('audio/')) {
        return <FileAudio className="size-5" />;
    }
    if (mime.includes('pdf') || mime.includes('word') || mime.includes('excel') || mime.includes('text') || mime.includes('csv')) {
        return <FileText className="size-5" />;
    }
    if (mime.includes('zip') || mime.includes('rar') || mime.includes('7z') || mime.includes('tar') || mime.includes('gzip')) {
        return <FileArchive className="size-5" />;
    }
    return <FileIconDefault className="size-5" />;
}

export function FileBubble({ m, isSticker = false }: FileBubbleProps & { isSticker?: boolean }) {
    const url = m.attachment_url ?? '';
    const displayName = m.attachment_name ?? m.content ?? 'Archivo';
    const displayMime = m.attachment_mime ?? 'archivo';

    if (isSticker && url) {
        return (
            <img
                src={url}
                alt="sticker"
                className="size-24 cursor-pointer object-contain"
                loading="lazy"
                onClick={() => window.open(url, '_blank')}
            />
        );
    }

    if (!url) {
        return (
            <div className="flex items-center gap-3 p-3 text-foreground">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted text-primary">
                    <Sticker className="size-5" />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{displayName}</p>
                    <p className="text-xs text-muted-foreground">Archivo no disponible</p>
                </div>
            </div>
        );
    }

    return (
        <a
            href={url}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-3 p-3 text-foreground transition hover:bg-muted/50"
        >
            <div className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted text-primary">
                <FileIcon mime={m.attachment_mime ?? null} />
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">{displayName}</p>
                <p className="text-xs text-muted-foreground">
                    {displayMime}
                    {m.attachment_size ? ` · ${formatBytes(m.attachment_size)}` : ''}
                </p>
            </div>
            <Download className="size-4 shrink-0 opacity-70" />
        </a>
    );
}
