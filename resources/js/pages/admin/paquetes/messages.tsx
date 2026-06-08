import { useEcho, useEchoPublic } from '@laravel/echo-react';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, MessageSquare } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { index as paquetesIndex, edit as paqueteEdit } from '@/routes/admin/paquetes';
import {
    show as messageShow,
    store as messageStore,
} from '@/routes/admin/paquetes/messages';

type Session = {
    session_id: string;
    count: number;
    last_at: string | null;
    last_human: string | null;
    name: string | null;
    email: string | null;
    preview: string;
};

type Message = {
    id: number;
    session_id: string;
    name?: string | null;
    email?: string | null;
    message: string;
    direction: 'incoming' | 'outgoing';
    created_at: string;
    created_human?: string;
};

type PageProps = {
    package: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        version: string;
        author: string | null;
        category_label: string;
        enabled: boolean;
    };
    sessions: Session[];
    messages: Message[];
    activeSessionId: string | null;
};

function formatTime(value: string): string {
    return new Date(value).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function PaquetesMessages({
    package: pkg,
    sessions,
    messages,
    activeSessionId,
}: PageProps) {
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const scrollerRef = useRef<HTMLDivElement | null>(null);
    const [list, setList] = useState<Message[]>(messages);
    const [sessionList, setSessionList] = useState<Session[]>(sessions);

    useEffect(() => {
        setList(messages);
    }, [messages]);

    useEffect(() => {
        setSessionList(sessions);
    }, [sessions]);

    useEffect(() => {
        const el = scrollerRef.current;
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    }, [list.length, activeSessionId]);

    useEcho<Message>(
        'chat-widget.admin',
        '.message.received',
        (message) => {
            setList((prev) => {
                if (prev.some((m) => m.id === message.id)) {
                    return prev;
                }
                if (message.session_id === activeSessionId) {
                    return [...prev, message];
                }
                return prev;
            });

            setSessionList((prev) => {
                const exists = prev.some((s) => s.session_id === message.session_id);
                if (exists) {
                    return prev.map((s) =>
                        s.session_id === message.session_id
                            ? {
                                ...s,
                                count: s.count + 1,
                                preview: message.message.slice(0, 60),
                                last_at: message.created_at,
                                name: message.name ?? s.name,
                                email: message.email ?? s.email,
                            }
                            : s,
                    );
                }

                return [
                    {
                        session_id: message.session_id,
                        count: 1,
                        last_at: message.created_at,
                        last_human: message.created_human ?? null,
                        name: message.name ?? null,
                        email: message.email ?? null,
                        preview: message.message.slice(0, 60),
                    },
                    ...prev,
                ];
            });
        },
    );

    useEchoPublic<Message>(
        `chat-widget.${activeSessionId ?? ''}`,
        '.message.received',
        (message) => {
            setList((prev) => {
                if (prev.some((m) => m.id === message.id)) {
                    return prev;
                }
                return [...prev, message];
            });
        },
        [activeSessionId],
    );

    function loadSession(sessionId: string): void {
        router.visit(
            messageShow({ package: pkg.id, sessionId }).url,
            { preserveState: true },
        );
    }

    async function sendReply(e: React.FormEvent): Promise<void> {
        e.preventDefault();
        if (draft.trim() === '' || ! activeSessionId || sending) {
            return;
        }

        setSending(true);
        try {
            router.post(
                messageStore({ package: pkg.id }).url,
                {
                    session_id: activeSessionId,
                    message: draft,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setDraft('');
                    },
                    onFinish: () => setSending(false),
                },
            );
        } catch (err) {
            setSending(false);
        }
    }

    return (
        <>
            <Head title={`Mensajes · ${pkg.name}`} />

            <div className="space-y-4 p-4">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <a
                        href={paqueteEdit({ package: pkg.id }).url}
                        className="inline-flex items-center gap-1 hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                        Volver a {pkg.name}
                    </a>
                </div>

                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Mensajes de {pkg.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Conversaciones recibidas desde el frontend.
                        </p>
                    </div>
                    <Badge variant="secondary">
                        {sessionList.length} conversación
                        {sessionList.length === 1 ? '' : 'es'}
                    </Badge>
                </div>

                <div className="grid gap-4 lg:grid-cols-[280px,1fr]">
                    <Card className="lg:max-h-[calc(100vh-220px)] lg:overflow-y-auto">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm">Conversaciones</CardTitle>
                            <CardDescription className="text-xs">
                                Más recientes primero
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-1 p-2">
                            {sessionList.length === 0 ? (
                                <p className="px-2 py-6 text-center text-xs text-muted-foreground">
                                    Aún no hay conversaciones.
                                </p>
                            ) : (
                                sessionList.map((s) => {
                                    const isActive = s.session_id === activeSessionId;
                                    const displayName = s.name ?? 'Anónimo';

                                    return (
                                        <button
                                            key={s.session_id}
                                            type="button"
                                            onClick={() => loadSession(s.session_id)}
                                            className={cn(
                                                'flex w-full flex-col gap-0.5 rounded-md p-2 text-left text-xs transition-colors',
                                                isActive
                                                    ? 'bg-accent text-accent-foreground'
                                                    : 'hover:bg-muted/60',
                                            )}
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="truncate font-medium">
                                                    {displayName}
                                                </span>
                                                <span className="shrink-0 text-[10px] text-muted-foreground">
                                                    {s.last_human}
                                                </span>
                                            </div>
                                            <p className="line-clamp-1 text-[11px] text-muted-foreground">
                                                {s.preview}
                                            </p>
                                            <p className="text-[10px] text-muted-foreground">
                                                {s.count} mensaje
                                                {s.count === 1 ? '' : 's'}
                                            </p>
                                        </button>
                                    );
                                })
                            )}
                        </CardContent>
                    </Card>

                    <Card className="flex flex-col lg:max-h-[calc(100vh-220px)]">
                        {activeSessionId ? (
                            <>
                                <CardHeader className="border-b pb-3">
                                    <CardTitle className="text-sm">
                                        Sesión {activeSessionId.slice(0, 8)}
                                    </CardTitle>
                                </CardHeader>
                                <div
                                    ref={scrollerRef}
                                    className="flex-1 space-y-2 overflow-y-auto bg-muted/20 p-4"
                                >
                                    {list.map((m) => (
                                        <AdminMessageBubble key={m.id} message={m} />
                                    ))}
                                </div>
                                <form
                                    onSubmit={sendReply}
                                    className="flex items-center gap-2 border-t p-3"
                                >
                                    <Input
                                        value={draft}
                                        onChange={(e) => setDraft(e.target.value)}
                                        placeholder="Escribe una respuesta…"
                                        disabled={sending}
                                    />
                                    <Button type="submit" disabled={sending || draft.trim() === ''}>
                                        Enviar
                                    </Button>
                                </form>
                            </>
                        ) : (
                            <CardContent className="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                                <div className="text-center">
                                    <MessageSquare className="mx-auto size-8 text-muted-foreground/40" />
                                    <p className="mt-2">Selecciona una conversación.</p>
                                </div>
                            </CardContent>
                        )}
                    </Card>
                </div>
            </div>
        </>
    );
}

function AdminMessageBubble({ message }: { message: Message }) {
    const isIncoming = message.direction === 'incoming';

    return (
        <div
            className={cn(
                'flex',
                isIncoming ? 'justify-start' : 'justify-end',
            )}
        >
            <div
                className={cn(
                    'max-w-[80%] rounded-2xl px-3 py-2 text-sm shadow-sm',
                    isIncoming
                        ? 'rounded-bl-sm bg-background text-foreground ring-1 ring-border'
                        : 'rounded-br-sm bg-primary text-primary-foreground',
                )}
            >
                <p className="whitespace-pre-wrap break-words">{message.message}</p>
                <p
                    className={cn(
                        'mt-1 text-[10px]',
                        isIncoming ? 'text-muted-foreground' : 'text-primary-foreground/70',
                    )}
                >
                    {message.created_at ? formatTime(message.created_at) : ''} ·{' '}
                    {isIncoming ? 'Visitante' : 'Tú'}
                </p>
            </div>
        </div>
    );
}

PaquetesMessages.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Paquetes', href: paquetesIndex().url },
        { title: 'Mensajes', href: '#' },
    ],
};
