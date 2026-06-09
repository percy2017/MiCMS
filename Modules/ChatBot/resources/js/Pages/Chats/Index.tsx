import { Head, router, useForm } from '@inertiajs/react';
import { Loader2, MessageCircle, Search, Send, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { admin } from '@/routes';

type ConversationSummary = {
    id: number;
    visitor_name: string;
    visitor_email: string;
    status: 'open' | 'closed';
    unread_by_admin: number;
    messages_count: number;
    last_message_at: string | null;
    last_message_at_diff: string | null;
    last_message_preview: string | null;
    user: { id: number; name: string; email: string } | null;
};

type Message = {
    id: number;
    role: 'user' | 'admin' | 'system';
    content: string;
    attachment_url?: string | null;
    read_at?: string | null;
    created_at?: string | null;
};

type ConversationDetail = {
    id: number;
    visitor_name: string;
    visitor_email: string;
    page_url: string | null;
    status: 'open' | 'closed';
    user_id: number;
    last_message_at: string | null;
    messages: Message[];
};

type PageProps = {
    conversations: { data: ConversationSummary[] };
    stats: { open: number; unread: number; total: number };
    filters: { search: string; status: string | null };
    active: ConversationDetail | null;
};

function csrfHeaders(): Record<string, string> {
    const match = document.cookie.match(new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]*)'));
    const token = match ? decodeURIComponent(match[2]) : null;
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-XSRF-TOKEN': token } : {}),
    };
}

export default function ChatsIndex({ conversations, stats, filters, active }: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [activeId, setActiveId] = useState<number | null>(active?.id ?? null);
    const [activeConv, setActiveConv] = useState<ConversationDetail | null>(active);
    const [pendingId, setPendingId] = useState<number | null>(null);
    const [draft, setDraft] = useState('');
    const { post, processing } = useForm({ content: '' });
    const scrollRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
    }, [activeConv?.messages.length]);

    useEffect(() => {
        if (! activeId) {
            return;
        }

        fetch(`/admin/chatbot/chats/${activeId}`, {
            headers: csrfHeaders(),
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : null))
            .then((data) => {
                if (data?.conversation) {
                    setActiveConv(data.conversation);
                }
            })
            .catch(() => {});

        fetch(`/admin/chatbot/chats/${activeId}/read`, {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
        });
    }, [activeId]);

    useEffect(() => {
        if (! activeId || ! window.Echo) {
            return;
        }
        const channel = window.Echo.private('chatbot.admin');
        channel.listen('ChatBotMessageReceived', (e: { message: Message; conversation: ConversationSummary }) => {
            if (e.message.conversation_id === activeId) {
                setActiveConv((prev) => prev ? {
                    ...prev,
                    messages: [...prev.messages, e.message],
                    last_message_at: e.message.created_at ?? prev.last_message_at,
                } : prev);
            }
            router.reload({ only: ['conversations'] });
        });
        return () => {
            channel.stopListening('ChatBotMessageReceived');
        };
    }, [activeId]);

    function openConversation(id: number): void {
        setActiveId(id);
    }

    function applyFilters(next: { search?: string; status?: string | null }): void {
        router.get(
            '/admin/chatbot/chats',
            { search: next.search ?? search, status: next.status ?? filters.status },
            { preserveState: true, replace: true },
        );
    }

    function sendMessage(e: React.FormEvent): void {
        e.preventDefault();
        if (! activeId || ! draft.trim()) {
            return;
        }
        const content = draft;
        setDraft('');

        fetch(`/admin/chatbot/chats/${activeId}/reply`, {
            method: 'POST',
            headers: { ...csrfHeaders(), 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ content }),
        })
            .then((r) => (r.ok ? r.json() : null))
            .then(() => {
                if (activeId) {
                    return fetch(`/admin/chatbot/chats/${activeId}`, {
                        headers: csrfHeaders(),
                        credentials: 'same-origin',
                    });
                }
            })
            .then((r) => (r && r.ok ? r.json() : null))
            .then((data) => {
                if (data?.conversation) {
                    setActiveConv(data.conversation);
                    router.reload({ only: ['conversations'] });
                }
            })
            .catch(() => {
                setDraft(content);
            });
    }

    function closeConversation(): void {
        if (! activeId) {
            return;
        }
        fetch(`/admin/chatbot/chats/${activeId}/close`, {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
        }).then(() => {
            setActiveConv((prev) => prev ? { ...prev, status: 'closed' } : prev);
            router.reload({ only: ['conversations'] });
        });
    }

    function reopenConversation(): void {
        if (! activeId) {
            return;
        }
        fetch(`/admin/chatbot/chats/${activeId}/reopen`, {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
        }).then(() => {
            setActiveConv((prev) => prev ? { ...prev, status: 'open' } : prev);
            router.reload({ only: ['conversations'] });
        });
    }

    function deleteConversation(): void {
        if (! activeId) {
            return;
        }
        if (! confirm('¿Eliminar esta conversación?')) {
            return;
        }
        setPendingId(activeId);
        router.delete(`/admin/chatbot/chats/${activeId}`, {
            preserveScroll: true,
            onSuccess: () => {
                setActiveId(null);
                setActiveConv(null);
                setPendingId(null);
            },
        });
    }

    const filtered = useMemo(() => {
        return conversations.data;
    }, [conversations.data]);

    return (
        <>
            <Head title="Chats" />
            <div className="flex h-[calc(100vh-2rem)] flex-col gap-3 p-4">
                {/* <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Chats</h1>
                        <p className="text-sm text-muted-foreground">
                            {stats.open} abiertas · {stats.unread} sin leer · {stats.total} totales
                        </p>
                    </div>
                </div> */}

                <div className="flex flex-1 overflow-hidden rounded-lg border bg-card">
                    <div className="flex w-80 shrink-0 flex-col border-r">
                        <div className="border-b p-3">
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    applyFilters({ search });
                                }}
                                className="relative"
                            >
                                <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Buscar visitante..."
                                    className="pl-9"
                                />
                            </form>
                        </div>

                        <div className="flex-1 overflow-y-auto">
                            {filtered.length === 0 ? (
                                <div className="flex flex-col items-center gap-2 p-8 text-center">
                                    <MessageCircle className="size-10 text-muted-foreground/40" />
                                    <p className="text-sm text-muted-foreground">
                                        Aún no hay conversaciones
                                    </p>
                                </div>
                            ) : (
                                filtered.map((c) => {
                                    const isActive = c.id === activeId;
                                    return (
                                        <button
                                            key={c.id}
                                            type="button"
                                            onClick={() => openConversation(c.id)}
                                            className={cn(
                                                'flex w-full items-start gap-3 border-b p-3 text-left transition',
                                                isActive ? 'bg-muted' : 'hover:bg-muted/50',
                                            )}
                                        >
                                            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                                {(c.visitor_name?.[0] ?? '?').toUpperCase()}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-baseline justify-between gap-2">
                                                    <p className="truncate text-sm font-medium">
                                                        {c.visitor_name}
                                                    </p>
                                                    <p className="shrink-0 text-[10px] text-muted-foreground">
                                                        {c.last_message_at_diff ?? ''}
                                                    </p>
                                                </div>
                                                <div className="flex items-center justify-between gap-2">
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {c.last_message_preview ?? c.visitor_email}
                                                    </p>
                                                    {c.unread_by_admin > 0 && (
                                                        <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-primary-foreground">
                                                            {c.unread_by_admin}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </button>
                                    );
                                })
                            )}
                        </div>
                    </div>

                    <div className="flex flex-1 flex-col">
                        {! activeConv ? (
                            <div className="flex flex-1 flex-col items-center justify-center gap-3 text-center text-muted-foreground">
                                <MessageCircle className="size-12 text-muted-foreground/30" />
                                <p className="text-sm">Selecciona una conversación para empezar</p>
                            </div>
                        ) : (
                            <>
                                <div className="flex items-center justify-between border-b p-3">
                                    <div className="flex items-center gap-3">
                                        <div className="flex size-9 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                            {(activeConv.visitor_name?.[0] ?? '?').toUpperCase()}
                                        </div>
                                        <div>
                                            <p className="text-sm font-semibold">
                                                {activeConv.visitor_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {activeConv.visitor_email}
                                                {activeConv.page_url && ` · ${activeConv.page_url}`}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {activeConv.status === 'open' ? (
                                            <Button type="button" variant="outline" size="sm" onClick={closeConversation}>
                                                Cerrar
                                            </Button>
                                        ) : (
                                            <Button type="button" variant="outline" size="sm" onClick={reopenConversation}>
                                                Reabrir
                                            </Button>
                                        )}
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={deleteConversation}
                                            disabled={pendingId === activeConv.id}
                                        >
                                            {pendingId === activeConv.id ? (
                                                <Loader2 className="size-4 animate-spin" />
                                            ) : (
                                                <X className="size-4" />
                                            )}
                                        </Button>
                                    </div>
                                </div>

                                <div
                                    ref={scrollRef}
                                    className="flex-1 space-y-2 overflow-y-auto bg-muted/30 p-4"
                                >
                                    {activeConv.messages.length === 0 ? (
                                        <p className="text-center text-sm text-muted-foreground">
                                            Aún no hay mensajes
                                        </p>
                                    ) : (
                                        activeConv.messages.map((m) => {
                                            const mine = m.role === 'admin';
                                            return (
                                                <div
                                                    key={m.id}
                                                    className={cn(
                                                        'flex max-w-[75%] flex-col gap-1 rounded-lg px-3 py-2 text-sm shadow-sm',
                                                        mine
                                                            ? 'ml-auto bg-primary text-primary-foreground'
                                                            : 'mr-auto bg-background border',
                                                    )}
                                                >
                                                    <p className="whitespace-pre-wrap break-words">{m.content}</p>
                                                    {m.attachment_url && (
                                                        <img
                                                            src={m.attachment_url}
                                                            alt=""
                                                            className="mt-1 max-w-full rounded"
                                                        />
                                                    )}
                                                    <p
                                                        className={cn(
                                                            'text-[10px]',
                                                            mine
                                                                ? 'text-primary-foreground/70 text-right'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {m.created_at
                                                            ? new Date(m.created_at).toLocaleTimeString([], {
                                                                  hour: '2-digit',
                                                                  minute: '2-digit',
                                                              })
                                                            : ''}
                                                    </p>
                                                </div>
                                            );
                                        })
                                    )}
                                </div>

                                {activeConv.status === 'open' ? (
                                    <form
                                        onSubmit={sendMessage}
                                        className="flex items-center gap-2 border-t bg-background p-3"
                                    >
                                        <textarea
                                            value={draft}
                                            onChange={(e) => setDraft(e.target.value)}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter' && !e.shiftKey) {
                                                    e.preventDefault();
                                                    sendMessage(e as unknown as React.FormEvent);
                                                }
                                            }}
                                            placeholder="Escribe una respuesta..."
                                            className="flex min-h-[40px] max-h-32 w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            rows={1}
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing || !draft.trim()}
                                            className="h-10 w-10"
                                            size="icon"
                                        >
                                            <Send className="size-4" />
                                        </Button>
                                    </form>
                                ) : (
                                    <div className="border-t bg-muted/30 p-3 text-center text-sm text-muted-foreground">
                                        Esta conversación está cerrada.
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

ChatsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'ChatBot', href: '/admin/chatbot/chats' },
        { title: 'Chats', href: '/admin/chatbot/chats' },
    ],
};
