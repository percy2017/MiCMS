import { useEchoPublic } from '@laravel/echo-react';
import { router, usePage } from '@inertiajs/react';
import { MessageCircle, Send, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { store as storeRoute } from '@/routes/chat-widget';

type ChatMessage = {
    id: number;
    session_id: string;
    name?: string | null;
    email?: string | null;
    message: string;
    direction: 'incoming' | 'outgoing';
    created_at: string;
    created_human?: string;
};

type PagePropsWithCsrf = {
    csrf_token?: string;
};

function getCsrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
    return meta?.content ?? '';
}

function generateSessionId(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return Math.random().toString(36).slice(2) + Date.now().toString(36);
}

const SESSION_KEY = 'chat-widget.session_id';

function loadSessionId(): string {
    if (typeof window === 'undefined') {
        return generateSessionId();
    }

    const existing = window.localStorage.getItem(SESSION_KEY);
    if (existing) {
        return existing;
    }

    const fresh = generateSessionId();
    window.localStorage.setItem(SESSION_KEY, fresh);

    return fresh;
}

function formatTime(value: string): string {
    const d = new Date(value);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

export function ChatWidget() {
    const [open, setOpen] = useState(false);
    const [sessionId, setSessionId] = useState<string>('');
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const lastIdRef = useRef<number>(0);
    const scrollerRef = useRef<HTMLDivElement | null>(null);
    const { props } = usePage<PagePropsWithCsrf>();

    useEffect(() => {
        setSessionId(loadSessionId());
    }, []);

    useEffect(() => {
        if (sessionId === '') {
            return;
        }

        const url = new URL(window.location.href);
        url.pathname = '/chat-widget/history';
        url.searchParams.set('session_id', sessionId);

        fetch(url.toString(), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : null))
            .then((data: { messages?: ChatMessage[] } | null) => {
                if (! data) {
                    return;
                }
                setMessages(data.messages ?? []);
            })
            .catch(() => {});
    }, [sessionId]);

    useEchoPublic<ChatMessage>(
        `chat-widget.${sessionId}`,
        '.message.received',
        (message) => {
            appendMessage(message);
        },
        [sessionId],
    );

    useEffect(() => {
        if (!open) {
            return;
        }

        const el = scrollerRef.current;
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    }, [open, messages.length]);

    function appendMessage(message: ChatMessage): void {
        setMessages((prev) => {
            if (prev.some((m) => m.id === message.id)) {
                return prev;
            }

            if (message.id > lastIdRef.current) {
                lastIdRef.current = message.id;
            }

            return [...prev, message];
        });
    }

    async function sendMessage(): Promise<void> {
        const text = draft.trim();

        if (text === '' || sending) {
            return;
        }

        setSending(true);
        setError(null);

        try {
            const response = await fetch(storeRoute.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    message: text,
                }),
            });

            if (!response.ok) {
                throw new Error('Error al enviar el mensaje.');
            }

            const data: { session_id: string; message: ChatMessage } = await response.json();

            setSessionId(data.session_id);
            if (typeof window !== 'undefined') {
                window.localStorage.setItem(SESSION_KEY, data.session_id);
            }
            appendMessage(data.message);
            setDraft('');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Error desconocido.');
        } finally {
            setSending(false);
        }
    }

    function handleSubmit(e: React.FormEvent): void {
        e.preventDefault();
        void sendMessage();
    }

    return (
        <>
            <Button
                type="button"
                onClick={() => setOpen(true)}
                size="icon"
                className={cn(
                    'fixed bottom-6 right-6 z-50 size-14 rounded-full shadow-lg',
                    'transition-transform hover:scale-105',
                )}
                aria-label="Abrir chat"
            >
                <MessageCircle className="size-6" />
            </Button>

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent
                    side="right"
                    className="flex w-full flex-col gap-0 p-0 sm:max-w-md"
                >
                    <SheetHeader className="border-b px-4 py-3">
                        <div className="flex items-center justify-between">
                            <SheetTitle>Chat en vivo</SheetTitle>
                            <button
                                type="button"
                                onClick={() => setOpen(false)}
                                className="rounded p-1 text-muted-foreground hover:bg-muted"
                                aria-label="Cerrar"
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                        <SheetDescription>
                            Déjanos tu mensaje y te responderemos pronto.
                        </SheetDescription>
                    </SheetHeader>

                    <div
                        ref={scrollerRef}
                        className="flex-1 space-y-3 overflow-y-auto bg-muted/30 p-4"
                    >
                        {messages.length === 0 ? (
                            <div className="flex h-full flex-col items-center justify-center gap-2 py-12 text-center text-sm text-muted-foreground">
                                <MessageCircle className="size-8 text-muted-foreground/50" />
                                <p>Escribe tu primer mensaje para iniciar la conversación.</p>
                            </div>
                        ) : (
                            messages.map((m) => (
                                <MessageBubble key={m.id} message={m} />
                            ))
                        )}
                    </div>

                    <form
                        onSubmit={handleSubmit}
                        className="space-y-2 border-t bg-background p-3"
                    >
                        {error ? (
                            <p className="text-xs text-destructive">{error}</p>
                        ) : null}
                        <div className="flex items-end gap-2">
                            <Textarea
                                value={draft}
                                onChange={(e) => setDraft(e.target.value)}
                                placeholder="Escribe un mensaje…"
                                className="min-h-12 max-h-32 resize-none"
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && ! e.shiftKey) {
                                        e.preventDefault();
                                        void sendMessage();
                                    }
                                }}
                                disabled={sending}
                            />
                            <Button
                                type="submit"
                                size="icon"
                                disabled={sending || draft.trim() === ''}
                                aria-label="Enviar"
                            >
                                <Send className="size-4" />
                            </Button>
                        </div>
                        <p className="text-[10px] text-muted-foreground">
                            {props.csrf_token ? 'Sesión segura' : ''}
                            {props.csrf_token ? ' · ' : ''}
                            ID: {sessionId.slice(0, 8)}
                        </p>
                    </form>
                </SheetContent>
            </Sheet>
        </>
    );
}

function MessageBubble({ message }: { message: ChatMessage }) {
    const isVisitor = message.direction === 'incoming';

    return (
        <div
            className={cn(
                'flex',
                isVisitor ? 'justify-end' : 'justify-start',
            )}
        >
            <div
                className={cn(
                    'max-w-[80%] rounded-2xl px-3 py-2 text-sm shadow-sm',
                    isVisitor
                        ? 'rounded-br-sm bg-primary text-primary-foreground'
                        : 'rounded-bl-sm bg-background text-foreground ring-1 ring-border',
                )}
            >
                <p className="whitespace-pre-wrap break-words">{message.message}</p>
                <p
                    className={cn(
                        'mt-1 text-[10px]',
                        isVisitor
                            ? 'text-primary-foreground/70'
                            : 'text-muted-foreground',
                    )}
                >
                    {message.created_at ? formatTime(message.created_at) : ''}
                </p>
            </div>
        </div>
    );
}
