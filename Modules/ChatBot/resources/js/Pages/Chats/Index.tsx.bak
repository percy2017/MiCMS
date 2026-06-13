import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Copy, Download, File as FileIconDefault, FileArchive, FileAudio, FileImage, FileText, FileVideo, Loader2, MessageCircle, Paperclip, Radio, Search, Send, Smile, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { MessageBody } from '@/components/message-body';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useChatBotEcho, type ChatBotEventPayload, type ChatMessage as ChatBotMessage } from '@/hooks/use-chat-echo';
import { useEscapeKey } from '@/hooks/use-escape-key';
import ChatLayout from '@/layouts/chat/chat-layout';
import { cn } from '@/lib/utils';
import { admin } from '@/routes';
import type { LinkPreviews } from '@/types/link-preview';
import ChatDetailsPanel from '../../Components/ChatDetailsPanel';

type Channel = {
    id: number;
    name: string;
    type: string;
};

type ConversationSummary = {
    id: number;
    name: string;
    email: string | null;
    visitor_phone: string | null;
    status: 'open' | 'closed';
    unread_by_admin: number;
    messages_count: number;
    last_message_at: string | null;
    last_message_at_diff: string | null;
    last_message_preview: string | null;
    channel_id: number | null;
    channel_name: string | null;
    user: { id: number; name: string; email: string; avatar_url?: string | null } | null;
};

type Reaction = {
    id: number;
    user_jid: string;
    emoji: string;
    created_at?: string | null;
};

type Message = {
    id: number;
    role: 'user' | 'admin' | 'system';
    type?: string;
    content: string;
    attachment_url?: string | null;
    attachment_mime?: string | null;
    attachment_name?: string | null;
    attachment_size?: number | null;
    read_at?: string | null;
    created_at?: string | null;
    link_previews?: LinkPreviews | null;
    reactions?: Reaction[];
};

type ConversationDetail = {
    id: number;
    name: string;
    email: string | null;
    page_url: string | null;
    status: 'open' | 'closed';
    user_id: number;
    user_avatar_url: string | null;
    user_phone: string | null;
    user_whatsapp_jid: string | null;
    external_id: string | null;
    channel_id: number | null;
    channel_name: string | null;
    last_message_at: string | null;
    first_message_at?: string | null;
    messages_count?: number;
    messages: Message[];
};

type PageProps = {
    conversations?: { data: ConversationSummary[] };
    stats?: { open: number; unread: number; total: number };
    channels?: Channel[];
    filters?: { search: string; status: string | null; channel_id: number | null };
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

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    if (bytes < 1024 * 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }
    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
}

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

export default function ChatsIndex({ conversations, stats, channels, filters, active }: PageProps) {
    const safeFilters = filters ?? { search: '', status: null, channel_id: null };
    const safeConversations = conversations ?? { data: [] };
    const safeStats = stats ?? { open: 0, unread: 0, total: 0 };
    const safeChannels = Array.isArray(channels) ? channels : [];
    const page = usePage();
    const authUser = (page.props as { auth?: { user?: { id: number; name: string; phone?: string | null; whatsapp_jid?: string | null } } }).auth?.user;
    const currentAdminJid = authUser?.whatsapp_jid
        ?? (authUser?.phone ? `admin-${authUser.phone}` : (authUser ? `admin-${authUser.id}` : 'admin-unknown'));
    const [search, setSearch] = useState(typeof safeFilters.search === 'string' ? safeFilters.search : '');
    const [filteredConversations, setFilteredConversations] = useState<ConversationSummary[] | null>(null);
    const [activeId, setActiveId] = useState<number | null>(active?.id ?? null);
    const [activeConv, setActiveConv] = useState<ConversationDetail | null>(active);
    const [pendingId, setPendingId] = useState<number | null>(null);
    const [draft, setDraft] = useState('');
    const [attachment, setAttachment] = useState<File | null>(null);
    const [sending, setSending] = useState(false);
    const [attachmentPreviewUrl, setAttachmentPreviewUrl] = useState<string | null>(null);
    const scrollRef = useRef<HTMLDivElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const draftRef = useRef<HTMLTextAreaElement>(null);

    const filtered = filteredConversations ?? (Array.isArray(safeConversations.data) ? safeConversations.data : []);

    useEffect(() => {
        if (search === '' && (safeFilters.search ?? '') === '') {
            return;
        }
        if (search === (safeFilters.search ?? '')) {
            return;
        }
        const t = window.setTimeout(() => {
            applyFilters({ search });
        }, 250);
        return () => window.clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    useEffect(() => {
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
    }, [activeConv?.messages.length]);

    useEffect(() => {
        const el = draftRef.current;
        if (!el) return;
        el.style.height = 'auto';
        const lineHeight = 24;
        const maxHeight = lineHeight * 6;
        const next = Math.min(el.scrollHeight, maxHeight);
        el.style.height = `${next}px`;
        el.style.overflowY = el.scrollHeight > maxHeight ? 'auto' : 'hidden';
    }, [draft]);

    useEffect(() => {
        if (! activeId) {
            return;
        }
        fetch(`/admin/chats/${activeId}/read`, {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
        }).catch(() => {});
    }, [activeId]);

    useEffect(() => {
        if (active?.id) {
            setActiveId(active.id);
            setActiveConv(active);
        }
    }, [active?.id]);

    useEffect(() => {
        if (attachment && attachment.type.startsWith('image/')) {
            const url = URL.createObjectURL(attachment);
            setAttachmentPreviewUrl(url);
            return () => {
                URL.revokeObjectURL(url);
            };
        }
        setAttachmentPreviewUrl(null);

        return undefined;
    }, [attachment]);

    const { status: wsStatus } = useChatBotEcho({
        onMessage: (e: ChatBotEventPayload) => {
            console.log('[ChatBot WS] Event received:', e);
            setActiveConv((prev) => {
                if (prev && e.message.conversation_id === prev.id) {
                    return {
                        ...prev,
                        messages: [...prev.messages, e.message],
                        last_message_at: e.message.created_at ?? prev.last_message_at,
                    };
                }
                return prev;
            });
            router.reload({ only: ['conversations', 'stats'] });
        },
        onReaction: (e) => {
            setActiveConv((prev) => {
                if (!prev || prev.id !== e.conversation_id) return prev;
                return {
                    ...prev,
                    messages: prev.messages.map((mm) => {
                        if (mm.id !== e.message_id) return mm;
                        const existing = (mm.reactions ?? []).filter((r) => !(r.emoji === e.reaction.emoji && r.user_jid === e.reaction.user_jid));
                        if (e.action === 'removed' || !e.reaction.emoji) {
                            return { ...mm, reactions: existing };
                        }
                        return {
                            ...mm,
                            reactions: [...existing, {
                                id: e.reaction.id,
                                user_jid: e.reaction.user_jid,
                                emoji: e.reaction.emoji,
                                created_at: new Date().toISOString(),
                            }],
                        };
                    }),
                };
            });
        },
        onLinkPreviewsReady: (e) => {
            setActiveConv((prev) => {
                if (!prev || prev.id !== e.conversation_id) return prev;
                return {
                    ...prev,
                    messages: prev.messages.map((mm) => {
                        if (mm.id !== e.message_id) return mm;
                        return { ...mm, link_previews: e.link_previews };
                    }),
                };
            });
        },
    });

    function openConversation(conv: ConversationSummary): void {
        setActiveId(conv.id);
        const params: Record<string, string | number> = { active: conv.id };
        if (safeFilters.search) {
            params.search = safeFilters.search;
        }
        if (safeFilters.channel_id !== null && safeFilters.channel_id !== undefined) {
            params.channel_id = safeFilters.channel_id;
        }
        if (safeFilters.status) {
            params.status = safeFilters.status;
        }
        router.get('/admin/chats', params, {
            preserveState: true,
            preserveScroll: true,
            only: ['active', 'conversations'],
        });
    }

    function applyFilters(next: { search?: string; status?: string | null; channel_id?: number | null }): void {
        const params = new URLSearchParams();
        const nextSearch = next.search ?? search;
        const nextStatus = next.status !== undefined ? next.status : safeFilters.status;
        const nextChannel = next.channel_id !== undefined ? next.channel_id : safeFilters.channel_id;
        if (nextSearch) params.set('search', nextSearch);
        if (nextStatus) params.set('status', nextStatus);
        if (nextChannel !== null && nextChannel !== undefined) params.set('channel_id', String(nextChannel));

        const url = `/admin/chats/search?${params.toString()}`;
        fetch(url, {
            method: 'GET',
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then((data: { conversations: ConversationSummary[] }) => {
                setFilteredConversations(data.conversations ?? []);
            })
            .catch(() => {
                setFilteredConversations([]);
            });
    }

    function sendMessage(e: React.FormEvent): void {
        e.preventDefault();
        if (! activeId) {
            return;
        }
        if (! draft.trim() && ! attachment) {
            return;
        }
        const content = draft;
        const file = attachment;
        setDraft('');
        setAttachment(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
        setSending(true);

        const formData = new FormData();
        formData.append('content', content);
        if (file) {
            formData.append('file', file);
        }

        function restoreInput(): void {
            setDraft(content);
            setAttachment(file);
            if (fileInputRef.current && file) {
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInputRef.current.files = dt.files;
            }
        }

        fetch(`/admin/chats/${activeId}/reply`, {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: formData,
        })
            .then(async (r) => {
                const data = await r.json().catch(() => ({}));
                if (r.ok && data?.ok) {
                    return data;
                }
                const err = new Error(data?.error ?? `HTTP ${r.status}`);
                (err as Error & { payload?: unknown }).payload = data;
                throw err;
            })
            .then((data) => {
                if (data?.conversation) {
                    setActiveConv(data.conversation);
                    router.reload({ only: ['conversations'] });
                }
            })
            .catch((err: Error & { payload?: { error?: string; error_detail?: unknown } }) => {
                restoreInput();
                const errorMsg = err.payload?.error ?? err.message ?? 'No se pudo enviar el mensaje.';
                toast.error('No se envió a WhatsApp', {
                    description: errorMsg,
                    duration: 8000,
                });
            })
            .finally(() => {
                setSending(false);
            });
    }

    function pickFile(): void {
        fileInputRef.current?.click();
    }

    function onFileChange(e: React.ChangeEvent<HTMLInputElement>): void {
        const file = e.target.files?.[0] ?? null;
        setAttachment(file);
    }

    function addOrRemoveReaction(message: Message, emoji: string): void {
        if (!activeConv) return;
        const existing = (message.reactions ?? []).find((r) => r.emoji === emoji && r.user_jid === currentAdminJid);
        const url = `/admin/chats/${activeConv.id}/messages/${message.id}/reactions`;

        if (existing) {
            setActiveConv((prev) => {
                if (!prev) return prev;
                return {
                    ...prev,
                    messages: prev.messages.map((mm) => {
                        if (mm.id !== message.id) return mm;
                        return {
                            ...mm,
                            reactions: (mm.reactions ?? []).filter((r) => !(r.emoji === emoji && r.user_jid === currentAdminJid)),
                        };
                    }),
                };
            });
            fetch(url, {
                method: 'DELETE',
                headers: csrfHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ emoji }),
            }).catch(() => toast.error('No se pudo quitar la reacción'));
            return;
        }

        const optimistic: Reaction = {
            id: Date.now(),
            user_jid: currentAdminJid,
            emoji,
            created_at: new Date().toISOString(),
        };
        setActiveConv((prev) => {
            if (!prev) return prev;
            return {
                ...prev,
                messages: prev.messages.map((mm) => {
                    if (mm.id !== message.id) return mm;
                    return { ...mm, reactions: [...(mm.reactions ?? []), optimistic] };
                }),
            };
        });
        fetch(url, {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ emoji }),
        }).catch(() => toast.error('No se pudo guardar la reacción'));
    }

    function clearAttachment(): void {
        setAttachment(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    }

    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [lightboxUrl, setLightboxUrl] = useState<string | null>(null);

    useEscapeKey(detailsOpen, () => setDetailsOpen(false));

    function openDeleteDialog(): void {
        if (! activeId) {
            return;
        }
        setDeleteDialogOpen(true);
    }

    function confirmDeleteConversation(): void {
        if (! activeId) {
            return;
        }
        setPendingId(activeId);
        setDeleteDialogOpen(false);
        router.delete(`/admin/chats/${activeId}`, {
            preserveScroll: true,
            onSuccess: () => {
                setActiveId(null);
                setActiveConv(null);
                setPendingId(null);
            },
            onError: () => {
                setPendingId(null);
            },
        });
    }

    return (
        <>
            <Head title="Chats" />
            <div className="flex h-full min-h-0 flex-col overflow-hidden text-[9px]">
                <div className="flex min-h-0 flex-1 overflow-hidden bg-card">
                    <div className="flex w-96 shrink-0 flex-col border-r">
                        <div className="border-b p-3 space-y-2">
                            <div className="flex flex-wrap items-center gap-1">
                                <button
                                    type="button"
                                    onClick={() => applyFilters({ channel_id: null })}
                                    className={cn(
                                        'cursor-pointer rounded-full px-2.5 py-1 text-xs font-medium transition',
                                        safeFilters.channel_id === null
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground hover:bg-muted/80',
                                    )}
                                >
                                    Todos
                                </button>
                                {safeChannels.map((ch) => (
                                    <button
                                        key={ch.id}
                                        type="button"
                                        onClick={() => applyFilters({ channel_id: ch.id })}
                                        className={cn(
                                            'cursor-pointer rounded-full px-2.5 py-1 text-xs font-medium transition',
                                            safeFilters.channel_id === ch.id
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-muted-foreground hover:bg-muted/80',
                                        )}
                                    >
                                        {ch.name}
                                    </button>
                                ))}
                                {(safeFilters.channel_id !== null || safeFilters.search) && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSearch('');
                                            applyFilters({ channel_id: null, search: '' });
                                        }}
                                        className="ml-auto cursor-pointer rounded-full bg-destructive/10 px-2.5 py-1 text-xs font-medium text-destructive hover:bg-destructive/20"
                                    >
                                        Limpiar filtros
                                    </button>
                                )}
                            </div>
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
                                    placeholder="Buscar por nombre, email o teléfono..."
                                    className="pl-9"
                                />
                            </form>
                            <div className="flex items-center gap-3 px-1 text-[10px] text-muted-foreground">
                                <span>{filtered.length} conversaciones</span>
                                {safeStats.unread > 0 && (
                                    <span className="font-medium text-primary">
                                        {safeStats.unread} sin leer
                                    </span>
                                )}
                                <span className="ml-auto flex items-center gap-1.5" title={`WebSocket: ${wsStatus}`}>
                                    <span
                                        className={cn(
                                            'size-2 shrink-0 rounded-full',
                                            wsStatus === 'connected' && 'bg-green-500 shadow-[0_0_6px_rgba(34,197,94,0.6)]',
                                            wsStatus === 'connecting' && 'bg-yellow-500 animate-pulse',
                                            wsStatus === 'failed' && 'bg-red-500',
                                            wsStatus === 'disconnected' && 'bg-gray-400',
                                        )}
                                    />
                                    <span className="text-[10px]">
                                        {wsStatus === 'connected' && 'En vivo'}
                                        {wsStatus === 'connecting' && 'Conectando'}
                                        {wsStatus === 'failed' && 'Desconectado'}
                                        {wsStatus === 'disconnected' && 'Sin conexión'}
                                    </span>
                                </span>
                            </div>
                        </div>

                        <div className="flex-1 overflow-y-auto">
                            {filtered.length === 0 ? (
                                <div className="flex flex-col items-center gap-2 p-8 text-center">
                                    <MessageCircle className="size-10 text-muted-foreground/40" />
                                    <p className="text-sm text-muted-foreground">
                                        {safeFilters.search || safeFilters.channel_id !== null
                                            ? 'Sin resultados para los filtros activos'
                                            : 'Aún no hay conversaciones'}
                                    </p>
                                    {(safeFilters.search || safeFilters.channel_id !== null) && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setSearch('');
                                                applyFilters({ channel_id: null, search: '' });
                                            }}
                                            className="mt-2 cursor-pointer rounded-md border px-3 py-1 text-xs hover:bg-muted"
                                        >
                                            Limpiar filtros
                                        </button>
                                    )}
                                </div>
                            ) : (
                                filtered.map((c) => {
                                    const isActive = c.id === activeId;
                                    return (
                                        <button
                                            key={c.id}
                                            type="button"
                                            onClick={() => openConversation(c)}
                                            className={cn(
                                                'flex w-full cursor-pointer items-start gap-3 border-b p-3 text-left transition',
                                                isActive ? 'bg-muted' : 'hover:bg-muted/50',
                                            )}
                                        >
                                            {c.user?.avatar_url ? (
                                                <img
                                                    src={c.user.avatar_url}
                                                    alt={c.name}
                                                    className="size-10 shrink-0 rounded-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                                    {(c.name?.[0] ?? '?').toUpperCase()}
                                                </div>
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-baseline justify-between gap-2">
                                                    <p className="truncate text-sm font-medium">
                                                        {c.name}
                                                    </p>
                                                    {c.unread_by_admin > 0 ? (
                                                        <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-primary-foreground">
                                                            {c.unread_by_admin}
                                                        </span>
                                                    ) : (
                                                        <p className="shrink-0 text-[10px] text-muted-foreground">
                                                            {c.last_message_at_diff ?? ''}
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-1.5 truncate text-[11px]">
                                                    {c.channel_name && (
                                                        <>
                                                            <span className="font-semibold text-primary">
                                                                {c.channel_name}
                                                            </span>
                                                            <span className="text-muted-foreground/50">|</span>
                                                        </>
                                                    )}
                                                    <span className="font-mono text-muted-foreground">
                                                        {c.visitor_phone ?? ''}
                                                    </span>
                                                </div>
                                            </div>
                                        </button>
                                    );
                                })
                            )}
                        </div>
                    </div>

                    <div className="flex min-w-0 flex-1 flex-col">
                        {! activeConv ? (
                            <div className="flex flex-1 flex-col items-center justify-center gap-3 text-center text-muted-foreground">
                                <MessageCircle className="size-12 text-muted-foreground/30" />
                                <p className="text-sm">Selecciona una conversación para empezar</p>
                            </div>
                        ) : (
                            <>
                                <button
                                    type="button"
                                    onClick={() => setDetailsOpen(true)}
                                    className="flex w-full cursor-pointer items-center justify-between border-b p-3 text-left transition hover:bg-muted/50"
                                >
                                    <div className="flex items-center gap-3">
                                        {activeConv.user_avatar_url ? (
                                            <img
                                                src={activeConv.user_avatar_url}
                                                alt={activeConv.name}
                                                className="size-9 shrink-0 rounded-full object-cover"
                                            />
                                        ) : (
                                            <div className="flex size-9 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                                {(activeConv.name?.[0] ?? '?').toUpperCase()}
                                            </div>
                                        )}
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <p className="truncate text-sm font-semibold">
                                                    {activeConv.name}
                                                </p>
                                                {activeConv.channel_name && (
                                                    <span className="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                                                        {activeConv.channel_name}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {activeConv.user_whatsapp_jid ?? activeConv.external_id ?? ''}
                                                {activeConv.page_url && ` · ${activeConv.page_url}`}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                openDeleteDialog();
                                            }}
                                            disabled={pendingId === activeConv.id}
                                            title="Eliminar conversación y archivos"
                                            className="cursor-pointer"
                                        >
                                            {pendingId === activeConv.id ? (
                                                <Loader2 className="size-4 animate-spin" />
                                            ) : (
                                                <Trash2 className="size-4" />
                                            )}
                                        </Button>
                                    </div>
                                </button>

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
                                            const t = m.type ?? 'text';
                                            const isPlaceholder = m.content && /^\[(Imagen|Video|Audio|Archivo|Sticker|Documento)\]$/i.test(m.content);
                                            const showCaption = m.content && !isPlaceholder;
                                            const hasMedia = Boolean(m.attachment_url);
                                            const fileSize = m.attachment_size ? formatBytes(m.attachment_size) : null;
                                            const isImage = t === 'image' && hasMedia;
                                            const isVideo = t === 'video' && hasMedia;
                                            const isAudio = t === 'audio' && hasMedia;
                                            const isSticker = t === 'sticker' && hasMedia;
                                            const isFile = t === 'file' && hasMedia;
                                            const isDoc = !isImage && !isVideo && !isAudio && !isSticker && hasMedia;

                                            if (isSticker) {
                                                return (
                                                    <div
                                                        key={m.id}
                                                        className={cn('flex', mine ? 'justify-end' : 'justify-start')}
                                                    >
                                                        <img
                                                            src={m.attachment_url ?? ''}
                                                            alt="sticker"
                                                            className="size-24 object-contain cursor-pointer"
                                                            onClick={() => setLightboxUrl(m.attachment_url ?? '')}
                                                        />
                                                    </div>
                                                );
                                            }

                                            return (
                                                <div
                                                    key={m.id}
                                                    className={cn(
                                                        'flex w-full',
                                                        mine ? 'justify-end' : 'justify-start',
                                                    )}
                                                >
                                                    <div
                                                        className={cn(
                                                            'flex max-w-[75%] flex-col text-sm shadow-sm overflow-hidden',
                                                        )}
                                                    >
                                                    <div
                                                        className={cn(
                                                            'flex flex-col bg-muted text-foreground border border-border',
                                                            hasMedia ? 'p-0 overflow-hidden rounded-lg' : 'rounded-lg px-3 py-2',
                                                        )}
                                                    >
                                                        {isImage && (
                                                            <img
                                                                src={m.attachment_url ?? ''}
                                                                alt={m.attachment_name ?? 'imagen'}
                                                                className="max-h-48 max-w-64 cursor-zoom-in rounded object-cover"
                                                                onClick={() => setLightboxUrl(m.attachment_url ?? '')}
                                                            />
                                                        )}

                                                        {isVideo && (
                                                            <video
                                                                src={m.attachment_url ?? ''}
                                                                controls
                                                                preload="metadata"
                                                                className="max-h-48 max-w-64 cursor-zoom-in rounded"
                                                                onClick={() => setLightboxUrl(m.attachment_url ?? '')}
                                                            />
                                                        )}

                                                        {isAudio && (
                                                            <div className="flex min-w-[260px] items-center gap-3 px-3 py-2">
                                                                <audio
                                                                    src={m.attachment_url ?? ''}
                                                                    preload="metadata"
                                                                    className="hidden"
                                                                    ref={(el) => {
                                                                        if (el) {
                                                                            (el as HTMLAudioElement & { __playHandler?: () => void }).dataset.bound = '1';
                                                                        }
                                                                    }}
                                                                />
                                                                <button
                                                                    type="button"
                                                                    onClick={(e) => {
                                                                        const container = e.currentTarget.parentElement;
                                                                        const audio = container?.querySelector('audio');
                                                                        if (audio) {
                                                                            if (audio.paused) {
                                                                                audio.play().catch(() => toast.error('No se pudo reproducir'));
                                                                            } else {
                                                                                audio.pause();
                                                                            }
                                                                        }
                                                                    }}
                                                                    className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary transition hover:bg-primary/25"
                                                                    title="Reproducir / pausar"
                                                                >
                                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="size-4">
                                                                        <path d="M8 5v14l11-7z" />
                                                                    </svg>
                                                                </button>
                                                                <div className="min-w-0 flex-1">
                                                                    <p className="truncate text-xs font-medium text-foreground">
                                                                        Audio · {m.attachment_name ?? 'audio.ogg'}
                                                                    </p>
                                                                    <p className="text-[10px] text-muted-foreground">
                                                                        {m.attachment_size ? formatBytes(m.attachment_size) : ''}
                                                                        {m.attachment_mime ? ` · ${m.attachment_mime.split(';')[0]}` : ''}
                                                                    </p>
                                                                </div>
                                                                <a
                                                                    href={m.attachment_url ?? '#'}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    download
                                                                    className="flex size-7 shrink-0 items-center justify-center rounded text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                                    title="Descargar audio"
                                                                >
                                                                    <Download className="size-3.5" />
                                                                </a>
                                                            </div>
                                                        )}

                                                        {isFile && (
                                                            <a
                                                                href={m.attachment_url ?? '#'}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="flex items-center gap-3 p-3 transition text-foreground hover:bg-muted/50"
                                                            >
                                                                <div className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted text-primary">
                                                                    <FileIcon mime={m.attachment_mime ?? null} />
                                                                </div>
                                                                <div className="min-w-0 flex-1">
                                                                    <p className="truncate text-sm font-medium">
                                                                        {m.attachment_name ?? 'Archivo'}
                                                                    </p>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        {m.attachment_mime ?? 'archivo'}
                                                                        {fileSize ? ` · ${fileSize}` : ''}
                                                                    </p>
                                                                </div>
                                                                <Download className="size-4 shrink-0 opacity-70" />
                                                            </a>
                                                        )}

                                                        {isDoc && (
                                                            <a
                                                                href={m.attachment_url ?? '#'}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="flex items-center gap-3 p-3 transition text-foreground hover:bg-muted/50"
                                                            >
                                                                <div className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted text-primary">
                                                                    <FileIcon mime={m.attachment_mime ?? null} />
                                                                </div>
                                                                <div className="min-w-0 flex-1">
                                                                    <p className="truncate text-sm font-medium">
                                                                        {m.attachment_name ?? m.content ?? 'Archivo'}
                                                                    </p>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        {m.attachment_mime ?? 'archivo'}
                                                                        {fileSize ? ` · ${fileSize}` : ''}
                                                                    </p>
                                                                </div>
                                                                <Download className="size-4 shrink-0 opacity-70" />
                                                            </a>
                                                        )}

                                                        {(hasMedia ? showCaption : Boolean(m.content)) && (
                                                            <div className={cn(
                                                                hasMedia ? 'px-3 pb-1 pt-1.5' : '',
                                                            )}>
                                                                <MessageBody content={m.content} linkPreviews={m.link_previews ?? null} />
                                                            </div>
                                                        )}
                                                    </div>

                                                    <div className="mt-0.5 flex items-center justify-between gap-2 px-1 text-[10px] text-muted-foreground">
                                                        <span className="truncate">
                                                            {m.created_at
                                                                ? new Date(m.created_at).toLocaleString([], {
                                                                      day: '2-digit',
                                                                      month: '2-digit',
                                                                      year: '2-digit',
                                                                      hour: '2-digit',
                                                                      minute: '2-digit',
                                                                  })
                                                                : ''}
                                                            {mine && m.read_at ? ' · Leído' : ''}
                                                        </span>
                                                        <div className="flex items-center gap-1">
                                                            <ReactionPicker
                                                                existing={(m.reactions ?? []).map((r) => r.emoji)}
                                                                onPick={(emoji) => addOrRemoveReaction(m, emoji)}
                                                            />
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    const text = m.content && m.content.length > 0
                                                                        ? m.content
                                                                        : (m.attachment_url ? m.attachment_url : '');
                                                                    if (text && navigator.clipboard?.writeText) {
                                                                        navigator.clipboard.writeText(text).then(
                                                                            () => toast.success('Copiado al portapapeles'),
                                                                            () => toast.error('No se pudo copiar'),
                                                                        );
                                                                    }
                                                                }}
                                                                title="Copiar mensaje"
                                                                aria-label="Copiar mensaje"
                                                                className="shrink-0 rounded p-0.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                            >
                                                                <Copy className="size-3" />
                                                            </button>
                                                        </div>
                                                    </div>

                                                    {m.reactions && m.reactions.length > 0 && (
                                                        <div
                                                            className={cn(
                                                                'mt-1 flex flex-wrap gap-1 px-1',
                                                                mine ? 'justify-end' : 'justify-start',
                                                            )}
                                                        >
                                                            {groupReactions(m.reactions).map((group) => {
                                                                const reacted = group.user_jids.includes(currentAdminJid);
                                                                return (
                                                                    <button
                                                                        key={group.emoji}
                                                                        type="button"
                                                                        onClick={() => addOrRemoveReaction(m, group.emoji)}
                                                                        title={group.user_jids.join(', ')}
                                                                        className={cn(
                                                                            'inline-flex items-center gap-1 rounded-full border px-1.5 py-0.5 text-[10px] transition',
                                                                            reacted
                                                                                ? 'border-primary/50 bg-primary/10 text-foreground'
                                                                                : 'border-border bg-background/70 text-foreground/80 hover:bg-muted',
                                                                        )}
                                                                    >
                                                                        <span>{group.emoji}</span>
                                                                        <span className="font-medium">{group.count}</span>
                                                                    </button>
                                                                );
                                                            })}
                                                        </div>
                                                    )}
                                                    </div>
                                                </div>
                                            );
                                        })
                                    )}
                                </div>

                                <form
                                    onSubmit={sendMessage}
                                    className="flex shrink-0 flex-col gap-2 border-t bg-background p-3"
                                >
                                    {attachment && (
                                        <div className="flex items-center gap-2 rounded-md border bg-muted/30 px-2 py-1.5 text-xs">
                                            {attachmentPreviewUrl ? (
                                                <img
                                                    src={attachmentPreviewUrl}
                                                    alt={attachment.name}
                                                    className="size-10 shrink-0 rounded object-cover"
                                                />
                                            ) : (
                                                <Paperclip className="size-3.5 shrink-0" />
                                            )}
                                            <span className="truncate font-medium">
                                                {attachment.name}
                                            </span>
                                            <span className="shrink-0 text-muted-foreground">
                                                {(attachment.size / 1024).toFixed(1)} KB
                                            </span>
                                            <button
                                                type="button"
                                                onClick={clearAttachment}
                                                className="ml-auto shrink-0 rounded p-0.5 hover:bg-muted"
                                                title="Quitar adjunto"
                                            >
                                                <X className="size-3.5" />
                                            </button>
                                        </div>
                                    )}
                                    <div className="flex items-center gap-2">
                                        <input
                                            ref={fileInputRef}
                                            type="file"
                                            onChange={onFileChange}
                                            className="hidden"
                                            accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            onClick={pickFile}
                                            disabled={sending}
                                            className="h-10 w-10"
                                        >
                                            <Paperclip className="size-4" />
                                        </Button>
                                        <textarea
                                            ref={draftRef}
                                            value={draft}
                                            onChange={(e) => setDraft(e.target.value)}
                                            onPaste={(e) => {
                                                const items = e.clipboardData?.items;
                                                if (!items) return;
                                                for (let i = 0; i < items.length; i += 1) {
                                                    const item = items[i];
                                                    if (item.kind === 'file' && item.type.startsWith('image/')) {
                                                        e.preventDefault();
                                                        const file = item.getAsFile();
                                                        if (file) {
                                                            setAttachment(file);
                                                        }
                                                        return;
                                                    }
                                                }
                                            }}
                                            onDragOver={(e) => {
                                                e.preventDefault();
                                            }}
                                            onDrop={(e) => {
                                                e.preventDefault();
                                                const file = e.dataTransfer?.files?.[0];
                                                if (file) {
                                                    setAttachment(file);
                                                }
                                            }}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter' && !e.shiftKey) {
                                                    e.preventDefault();
                                                    sendMessage(e as unknown as React.FormEvent);
                                                }
                                            }}
                                            placeholder={attachment ? 'Añade un comentario (opcional)...' : 'Escribe una respuesta o pega una imagen...'}
                                            className="flex w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm leading-6"
                                            rows={1}
                                        />
                                        <Button
                                            type="submit"
                                            disabled={sending || (!draft.trim() && !attachment)}
                                            className="h-10 w-10"
                                            size="icon"
                                        >
                                            {sending ? (
                                                <Loader2 className="size-4 animate-spin" />
                                            ) : (
                                                <Send className="size-4" />
                                            )}
                                        </Button>
                                    </div>
                                </form>
                            </>
                        )}
                    </div>

                    {activeConv && (
                        <>
                            <div
                                aria-hidden
                                onClick={() => setDetailsOpen(false)}
                                className={cn(
                                    'fixed inset-0 z-30 bg-black/40 transition-opacity xl:hidden',
                                    detailsOpen ? 'opacity-100' : 'pointer-events-none opacity-0',
                                )}
                            />
                            <div
                                className={cn(
                                    'fixed inset-y-2 right-2 z-40 w-80 max-w-[calc(100vw-1rem)] flex-shrink-0 transition-transform duration-200 ease-out',
                                    'xl:static xl:inset-auto xl:right-auto xl:z-auto xl:w-80 xl:max-w-none xl:transition-none',
                                    detailsOpen
                                        ? 'translate-x-0 xl:block'
                                        : 'pointer-events-none translate-x-[calc(100%+1rem)] opacity-0 xl:hidden',
                                )}
                                aria-hidden={!detailsOpen}
                            >
                                <ChatDetailsPanel
                                    active={activeConv}
                                    onClose={() => setDetailsOpen(false)}
                                />
                            </div>
                        </>
                    )}
                </div>

                <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10">
                                    <AlertTriangle className="size-5 text-destructive" />
                                </div>
                                <div>
                                    <DialogTitle>Eliminar conversación</DialogTitle>
                                    <DialogDescription>
                                        Esta acción no se puede deshacer.
                                    </DialogDescription>
                                </div>
                            </div>
                        </DialogHeader>

                        <div className="space-y-2 text-sm text-muted-foreground">
                            <p>¿Estás seguro de eliminar la conversación con <span className="font-semibold text-foreground">{activeConv?.name ?? 'este usuario'}</span>?</p>
                            <p>Se eliminarán <span className="font-medium text-foreground">permanentemente</span> los mensajes y todos los archivos adjuntos (imágenes, videos, audios, documentos) tanto de la base de datos como del servidor.</p>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDeleteDialogOpen(false)}
                                disabled={pendingId === activeConv?.id}
                            >
                                No, cancelar
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={confirmDeleteConversation}
                                disabled={pendingId === activeConv?.id}
                            >
                                {pendingId === activeConv?.id ? (
                                    <>
                                        <Loader2 className="mr-2 size-4 animate-spin" />
                                        Eliminando...
                                    </>
                                ) : (
                                    'Sí, eliminar'
                                )}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog open={lightboxUrl !== null} onOpenChange={(open) => { if (!open) setLightboxUrl(null); }}>
                    <DialogContent className="max-w-3xl border-0 bg-black/90 p-0 shadow-none">
                        <div className="flex items-center justify-center p-2">
                            {lightboxUrl && (
                                <img
                                    src={lightboxUrl}
                                    alt=""
                                    className="max-h-[85vh] max-w-full rounded-lg object-contain"
                                />
                            )}
                        </div>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

const QUICK_REACTIONS = ['❤️', '😂', '😮', '😢', '🙏', '👍'];

function groupReactions(reactions: Reaction[]): Array<{ emoji: string; count: number; user_jids: string[] }> {
    const map = new Map<string, string[]>();
    for (const r of reactions) {
        if (!r.emoji) continue;
        if (!map.has(r.emoji)) {
            map.set(r.emoji, []);
        }
        map.get(r.emoji)!.push(r.user_jid);
    }
    return Array.from(map.entries()).map(([emoji, user_jids]) => ({
        emoji,
        count: user_jids.length,
        user_jids,
    }));
}

function ReactionPicker({
    existing,
    onPick,
}: {
    existing: string[];
    onPick: (emoji: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const [pos, setPos] = useState<{ top: number; left: number } | null>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const popoverRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        function onDocClick(e: MouseEvent): void {
            const target = e.target as Node;
            if (
                triggerRef.current?.contains(target)
                || popoverRef.current?.contains(target)
            ) {
                return;
            }
            setOpen(false);
        }
        function onScroll(): void {
            setOpen(false);
        }
        document.addEventListener('mousedown', onDocClick);
        document.addEventListener('scroll', onScroll, true);
        return () => {
            document.removeEventListener('mousedown', onDocClick);
            document.removeEventListener('scroll', onScroll, true);
        };
    }, [open]);

    function openPopover(): void {
        const btn = triggerRef.current;
        if (!btn) return;
        const rect = btn.getBoundingClientRect();
        const popoverHeight = 40;
        const popoverWidth = 240;
        const viewportH = window.innerHeight;
        const spaceAbove = rect.top;
        const spaceBelow = viewportH - rect.bottom;
        const fitsAbove = spaceAbove >= popoverHeight + 8;
        const fitsBelow = spaceBelow >= popoverHeight + 8;
        let top: number;
        if (fitsAbove) {
            top = rect.top - popoverHeight - 4;
        } else if (fitsBelow) {
            top = rect.bottom + 4;
        } else {
            top = Math.max(8, Math.min(viewportH - popoverHeight - 8, rect.top - popoverHeight - 4));
        }
        const left = Math.max(8, Math.min(window.innerWidth - popoverWidth - 8, rect.right - popoverWidth));
        setPos({ top, left });
        setOpen(true);
    }

    return (
        <>
            <button
                ref={triggerRef}
                type="button"
                onClick={(e) => {
                    e.stopPropagation();
                    if (open) {
                        setOpen(false);
                    } else {
                        openPopover();
                    }
                }}
                title="Reaccionar"
                aria-label="Reaccionar"
                className="shrink-0 rounded p-0.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
            >
                <Smile className="size-3" />
            </button>
            {open && pos && (
                <div
                    ref={popoverRef}
                    role="dialog"
                    aria-label="Selecciona una reacción"
                    style={{ top: pos.top, left: pos.left }}
                    className="fixed z-[100] flex gap-0.5 rounded-full border bg-popover px-1.5 py-1 shadow-md"
                    onClick={(e) => e.stopPropagation()}
                >
                    {QUICK_REACTIONS.map((emoji) => {
                        const isMine = existing.includes(emoji);
                        return (
                            <button
                                key={emoji}
                                type="button"
                                onClick={() => {
                                    onPick(emoji);
                                    setOpen(false);
                                }}
                                className={cn(
                                    'flex size-6 items-center justify-center rounded-full text-base transition hover:scale-125',
                                    isMine ? 'bg-primary/15' : 'hover:bg-muted',
                                )}
                                title={emoji}
                            >
                                {emoji}
                            </button>
                        );
                    })}
                </div>
            )}
        </>
    );
}

ChatsIndex.layout = (page: React.ReactNode) => (
    <ChatLayout>{page}</ChatLayout>
);

ChatsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Chats', href: '/admin/chats' },
    ],
};
