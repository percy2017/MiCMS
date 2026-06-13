import { Bold, Code, Eye, EyeOff, Italic, Link as LinkIcon, Strikethrough } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import { renderWhatsAppMarkdown } from '@/lib/whatsapp-markdown';

type Props = {
    id?: string;
    value: string;
    onChange: (v: string) => void;
    placeholder?: string;
    rows?: number;
    maxLength?: number;
    error?: string;
    label?: string;
    help?: string;
    className?: string;
    disabled?: boolean;
};

/**
 * Insert a snippet at the current cursor position of a textarea.
 * Returns the new value and the new cursor position.
 */
function insertAtCursor(
    textarea: HTMLTextAreaElement,
    current: string,
    snippet: string,
    selectInside = true,
): { value: string; selectionStart: number; selectionEnd: number } {
    const start = textarea.selectionStart ?? current.length;
    const end = textarea.selectionEnd ?? current.length;
    const before = current.slice(0, start);
    const after = current.slice(end);
    const next = `${before}${snippet}${after}`;
    const cursor = start + snippet.length;
    return {
        value: next,
        selectionStart: selectInside ? start : cursor,
        selectionEnd: selectInside ? cursor : cursor,
    };
}

export function WhatsAppEditor({
    id,
    value,
    onChange,
    placeholder = 'Escribe el mensaje...',
    rows = 6,
    maxLength = 5000,
    error,
    label,
    help,
    className,
    disabled,
}: Props): React.ReactElement {
    const [showPreview, setShowPreview] = useState(true);

    function wrapOrInsert(before: string, after: string, fallback: string): void {
        const ta = document.getElementById(id ?? 'whatsapp-editor') as HTMLTextAreaElement | null;
        if (!ta) return;
        const start = ta.selectionStart ?? value.length;
        const end = ta.selectionEnd ?? value.length;
        const selected = value.slice(start, end);
        const inner = selected || fallback;
        const next = value.slice(0, start) + before + inner + after + value.slice(end);
        onChange(next);
        requestAnimationFrame(() => {
            ta.focus();
            const newStart = start + before.length;
            const newEnd = newStart + inner.length;
            ta.setSelectionRange(newStart, newEnd);
        });
    }

    function insertLink(): void {
        const ta = document.getElementById(id ?? 'whatsapp-editor') as HTMLTextAreaElement | null;
        if (!ta) return;
        const start = ta.selectionStart ?? value.length;
        const end = ta.selectionEnd ?? value.length;
        const selected = value.slice(start, end) || 'texto';
        const url = window.prompt('URL del enlace:', 'https://');
        if (!url) return;
        const next = value.slice(0, start) + `[${selected}](${url})` + value.slice(end);
        onChange(next);
        requestAnimationFrame(() => {
            ta.focus();
            const newCursor = start + `[${selected}](${url})`.length;
            ta.setSelectionRange(newCursor, newCursor);
        });
    }

    return (
        <div className={cn('space-y-2', className)}>
            {label && (
                <div className="flex items-center justify-between">
                    <label htmlFor={id} className="text-sm font-medium leading-none">
                        {label}
                    </label>
                    <button
                        type="button"
                        onClick={() => setShowPreview((v) => !v)}
                        className="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        title={showPreview ? 'Ocultar preview' : 'Mostrar preview'}
                    >
                        {showPreview ? (
                            <>
                                <EyeOff className="size-3" /> Ocultar
                            </>
                        ) : (
                            <>
                                <Eye className="size-3" /> Preview
                            </>
                        )}
                    </button>
                </div>
            )}

            <div className={cn('grid gap-2', showPreview ? 'md:grid-cols-2' : 'grid-cols-1')}>
                <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-0.5 rounded-md border bg-muted/30 p-0.5">
                        <button
                            type="button"
                            onClick={() => wrapOrInsert('*', '*', 'negrita')}
                            disabled={disabled}
                            className="inline-flex size-7 items-center justify-center rounded text-muted-foreground transition hover:bg-background hover:text-foreground disabled:opacity-50"
                            title="Negrita (Ctrl+B)"
                        >
                            <Bold className="size-3.5" />
                        </button>
                        <button
                            type="button"
                            onClick={() => wrapOrInsert('_', '_', 'cursiva')}
                            disabled={disabled}
                            className="inline-flex size-7 items-center justify-center rounded text-muted-foreground transition hover:bg-background hover:text-foreground disabled:opacity-50"
                            title="Cursiva (Ctrl+I)"
                        >
                            <Italic className="size-3.5" />
                        </button>
                        <button
                            type="button"
                            onClick={() => wrapOrInsert('~', '~', 'tachado')}
                            disabled={disabled}
                            className="inline-flex size-7 items-center justify-center rounded text-muted-foreground transition hover:bg-background hover:text-foreground disabled:opacity-50"
                            title="Tachado"
                        >
                            <Strikethrough className="size-3.5" />
                        </button>
                        <button
                            type="button"
                            onClick={() => wrapOrInsert('```', '```', 'código')}
                            disabled={disabled}
                            className="inline-flex size-7 items-center justify-center rounded text-muted-foreground transition hover:bg-background hover:text-foreground disabled:opacity-50"
                            title="Código"
                        >
                            <Code className="size-3.5" />
                        </button>
                        <span className="mx-1 h-4 w-px bg-border" />
                        <button
                            type="button"
                            onClick={insertLink}
                            disabled={disabled}
                            className="inline-flex size-7 items-center justify-center rounded text-muted-foreground transition hover:bg-background hover:text-foreground disabled:opacity-50"
                            title="Insertar enlace"
                        >
                            <LinkIcon className="size-3.5" />
                        </button>
                    </div>
                    <textarea
                        id={id ?? 'whatsapp-editor'}
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        placeholder={placeholder}
                        rows={rows}
                        maxLength={maxLength}
                        disabled={disabled}
                        className={cn(
                            'flex w-full resize-none rounded-md border border-input bg-background px-3 py-2 font-mono text-sm leading-6 shadow-sm transition placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
                            error && 'border-destructive',
                        )}
                    />
                </div>

                {showPreview && (
                    <div className="flex min-h-0 flex-col">
                        <div className="rounded-md border bg-[#ECE5DD] p-3">
                            <div className="ml-auto max-w-[85%] rounded-lg bg-[#DCF8C6] px-3 py-2 text-sm text-[#111] shadow-sm">
                                {value.trim() ? (
                                    <div
                                        className="wa-preview break-words"
                                        dangerouslySetInnerHTML={{ __html: renderWhatsAppMarkdown(value) }}
                                    />
                                ) : (
                                    <span className="text-muted-foreground italic">El preview aparecerá aquí...</span>
                                )}
                            </div>
                            <p className="mt-2 text-right text-[10px] text-muted-foreground/70">
                                ~ Vista previa tipo WhatsApp ~
                            </p>
                        </div>
                        <p className="mt-1 text-[10px] text-muted-foreground">
                            *negrita* _cursiva_ ~tachado~ ```código``` [link](https://...)
                        </p>
                    </div>
                )}
            </div>

            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span>{value.length}/{maxLength}</span>
                {help && <span>{help}</span>}
            </div>

            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
