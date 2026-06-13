import { Download, Music2 } from 'lucide-react';
import type { ChatMessage } from '@/types/chat';
import { formatBytes } from '@/lib/chat-utils';

type AudioBubbleProps = {
    m: ChatMessage;
    isMine: boolean;
};

export function AudioBubble({ m }: AudioBubbleProps) {
    const url = m.attachment_url ?? '';
    if (!url) {
        return (
            <div className="flex items-center gap-2 px-3 py-2 text-muted-foreground">
                <Music2 className="size-4" />
                <span className="text-xs">Audio no disponible</span>
            </div>
        );
    }
    const displayMime = m.attachment_mime ? m.attachment_mime.split(';')[0] : 'audio/ogg';

    return (
        <div className="flex min-w-[280px] flex-col gap-1.5 px-3 py-2">
            <div className="flex items-center justify-between gap-2 text-xs">
                <div className="flex min-w-0 items-center gap-1.5">
                    <Music2 className="size-3.5 shrink-0 text-primary" />
                    <span className="truncate font-medium text-foreground">
                        {m.attachment_name ?? 'Audio'}
                    </span>
                </div>
                <div className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                    {m.attachment_size ? <span>{formatBytes(m.attachment_size)}</span> : null}
                    <span>·</span>
                    <span>{displayMime}</span>
                    <a
                        href={url}
                        target="_blank"
                        rel="noopener noreferrer"
                        download
                        className="ml-1 rounded p-0.5 transition hover:bg-muted hover:text-foreground"
                        title="Descargar audio"
                    >
                        <Download className="size-3" />
                    </a>
                </div>
            </div>
            <audio
                src={url}
                controls
                preload="metadata"
                className="h-9 w-full"
            />
        </div>
    );
}
