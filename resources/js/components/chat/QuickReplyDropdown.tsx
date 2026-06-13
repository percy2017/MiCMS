import { FileImage, Search } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

export type QuickReply = {
    id: number;
    shortcut: string;
    title: string;
    content: string | null;
    category: string | null;
    media_id: number | null;
    media_url: string | null;
    media_mime: string | null;
    media_name: string | null;
};

type Props = {
    open: boolean;
    query: string;
    replies: QuickReply[];
    selectedIndex: number;
    onSelect: (reply: QuickReply) => void;
    onHover: (index: number) => void;
    onClose: () => void;
    loading?: boolean;
};

export function QuickReplyDropdown({ open, query, replies, selectedIndex, onSelect, onHover, onClose, loading }: Props): React.ReactElement | null {
    const listRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open || !listRef.current) return;
        const item = listRef.current.querySelector<HTMLButtonElement>(`[data-index="${selectedIndex}"]`);
        if (item) {
            item.scrollIntoView({ block: 'nearest' });
        }
    }, [selectedIndex, open]);

    if (!open) return null;

    const filtered = filterReplies(replies, query);
    const hasResults = filtered.length > 0;

    return (
        <div
            className="absolute bottom-full left-0 right-0 z-50 mb-1 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-lg"
            onMouseDown={(e) => e.preventDefault()}
        >
            <div className="flex items-center gap-2 border-b bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                <Search className="size-3" />
                {query ? (
                    <span>
                        Respuestas que coinciden con <code className="rounded bg-background px-1 font-mono">/{query}</code>
                    </span>
                ) : (
                    <span>Escribe el shortcut (ej: <code className="rounded bg-background px-1 font-mono">/saludo</code>)</span>
                )}
                <span className="ml-auto text-[10px]">
                    {hasResults ? `${filtered.length} resultado${filtered.length !== 1 ? 's' : ''}` : 'sin resultados'}
                </span>
            </div>

            {loading ? (
                <div className="px-3 py-4 text-center text-xs text-muted-foreground">Cargando respuestas...</div>
            ) : !hasResults ? (
                <div className="px-3 py-4 text-center text-xs text-muted-foreground">
                    No hay respuestas que coincidan. <kbd className="ml-1 rounded bg-muted px-1 text-[10px]">Esc</kbd> para cerrar.
                </div>
            ) : (
                <div ref={listRef} className="max-h-64 overflow-y-auto">
                    {filtered.map((reply, idx) => {
                        const isSelected = idx === selectedIndex;
                        return (
                            <button
                                key={reply.id}
                                data-index={idx}
                                type="button"
                                onClick={() => onSelect(reply)}
                                onMouseEnter={() => onHover(idx)}
                                className={cn(
                                    'flex w-full items-start gap-3 border-b px-3 py-2 text-left text-sm transition last:border-b-0',
                                    isSelected ? 'bg-accent text-accent-foreground' : 'hover:bg-muted/50',
                                )}
                            >
                                <code
                                    className={cn(
                                        'mt-0.5 shrink-0 rounded px-1.5 py-0.5 font-mono text-[11px]',
                                        isSelected ? 'bg-background text-foreground' : 'bg-muted text-foreground',
                                    )}
                                >
                                    /{reply.shortcut}
                                </code>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <span className="truncate font-medium">{reply.title}</span>
                                        {reply.media_id && (
                                            <span className="inline-flex shrink-0 items-center gap-0.5 rounded-full bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-700">
                                                <FileImage className="size-2.5" />
                                                media
                                            </span>
                                        )}
                                        {reply.category && (
                                            <span className="shrink-0 rounded-full bg-secondary px-1.5 py-0.5 text-[10px] text-secondary-foreground">
                                                {reply.category}
                                            </span>
                                        )}
                                    </div>
                                    {reply.content && (
                                        <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">{reply.content}</p>
                                    )}
                                </div>
                            </button>
                        );
                    })}
                </div>
            )}

            <div className="flex items-center gap-3 border-t bg-muted/40 px-3 py-1.5 text-[10px] text-muted-foreground">
                <span>
                    <kbd className="rounded bg-background px-1">↑↓</kbd> navegar
                </span>
                <span>
                    <kbd className="rounded bg-background px-1">Tab</kbd> / <kbd className="rounded bg-background px-1">Enter</kbd> seleccionar
                </span>
                <span>
                    <kbd className="rounded bg-background px-1">Esc</kbd> cerrar
                </span>
            </div>
        </div>
    );
}

export function filterReplies(replies: QuickReply[], query: string): QuickReply[] {
    if (!query) return replies;
    const q = query.toLowerCase();
    return replies.filter((r) => {
        if (r.shortcut.toLowerCase().includes(q)) return true;
        if (r.title.toLowerCase().includes(q)) return true;
        if (r.content && r.content.toLowerCase().includes(q)) return true;
        if (r.category && r.category.toLowerCase().includes(q)) return true;
        return false;
    });
}
