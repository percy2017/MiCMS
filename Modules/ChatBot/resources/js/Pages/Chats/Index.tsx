import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    FileImage,
    Loader2,
    MessageCircle,
    Paperclip,
    Search,
    Send,
    Smile,
    Trash2,
    X,
    Zap,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import ChatLayout from '@/layouts/chat/chat-layout';
import { MessageBubble } from '@/components/chat/MessageBubble';
import { QuickReplyDropdown, filterReplies, type QuickReply } from '@/components/chat/QuickReplyDropdown';
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
import { useChatSync } from '@/hooks/use-chat-sync';
import { useQuickReplies } from '../../Hooks/use-quick-replies';
import { csrfHeaders, formatBytes, formatChatDate } from '@/lib/chat-utils';
import { cn } from '@/lib/utils';
import ChatDetailsPanel from '../../Components/ChatDetailsPanel';
import type { ChatMessage, ChatPageProps, ConversationDetail, ConversationSummary, Reaction } from '@/types/chat';

export default function ChatsIndex({ conversations, stats, channels, filters, active }: ChatPageProps) {
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
    const [attachmentPreviewUrl, setAttachmentPreviewUrl] = useState<string | null>(null);
    const [pickedMediaId, setPickedMediaId] = useState<number | null>(null);
    const [pickedMediaMeta, setPickedMediaMeta] = useState<{ name: string | null; url: string | null; mime: string | null } | null>(null);
    const [sending, setSending] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [lightboxUrl, setLightboxUrl] = useState<string | null>(null);

    // Pagination state for active conversation (loads last 10, infinite scroll up)
    const [hasMore, setHasMore] = useState<boolean>(active?.has_more_messages ?? false);
    const [loadingMore, setLoadingMore] = useState(false);

    // Quick reply slash command state
    const { replies: quickReplies, loading: quickRepliesLoading } = useQuickReplies();
    const [qrOpen, setQrOpen] = useState(false);
    const [qrSelectedIndex, setQrSelectedIndex] = useState(0);

    const qrQuery = useMemo((): string => {
        if (!qrOpen) return '';
        const lines = draft.split('\n');
        const lastLine = lines[lines.length - 1] ?? '';
        const match = lastLine.match(/^\/([a-zA-Z0-9_-]*)$/);
        return match ? match[1] : '';
    }, [draft, qrOpen]);

    const qrFiltered = useMemo((): QuickReply[] => {
        if (!qrOpen) return [];
        return filterReplies(quickReplies, qrQuery);
    }, [quickReplies, qrQuery, qrOpen]);

    const scrollRef = useRef<HTMLDivElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const draftRef = useRef<HTMLTextAreaElement>(null);

    const filtered = filteredConversations ?? (Array.isArray(safeConversations.data) ? safeConversations.data : []);

    const { wsStatus } = useChatSync({
        activeConvId: activeId,
        pollIntervalMs: 8000,
        onMessage: (e) => {
            setActiveConv((prev) => {
                if (!prev || e.message.conversation_id !== prev.id) {
                    return prev;
                }
                if (prev.messages.some((m) => m.id === e.message.id)) {
                    return prev;
                }
                return {
                    ...prev,
                    messages: [...prev.messages, e.message],
                    last_message_at: e.message.created_at ?? prev.last_message_at,
                };
            });

            setFilteredConversations((prevList) => {
                const base = prevList ?? (Array.isArray(safeConversations.data) ? safeConversations.data : []);
                const updated: ConversationSummary = {
                    ...(base.find((c) => c.id === e.conversation.id) ?? {
                        id: e.conversation.id,
                        name: e.conversation.visitor_name,
                        status: 'open',
                        channel_id: e.conversation.channel_id,
                        channel_name: e.conversation.channel_name,
                    }),
                    last_message_at: e.conversation.last_message_at ?? e.message.created_at ?? null,
                    unread: e.message.role === 'user'
                        ? (e.conversation.unread ?? ((base.find((c) => c.id === e.conversation.id)?.unread ?? 0) + 1))
                        : (base.find((c) => c.id === e.conversation.id)?.unread ?? 0),
                    messages_count: (base.find((c) => c.id === e.conversation.id)?.messages_count ?? 0) + 1,
                };
                const without = base.filter((c) => c.id !== e.conversation.id);

                return [updated, ...without].sort((a, b) => {
                    const ta = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
                    const tb = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;

                    if (tb !== ta) {
                        return tb - ta;
                    }

                    return (b.id ?? 0) - (a.id ?? 0);
                });
            });
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
                    messages: prev.messages.map((mm) => (mm.id === e.message_id ? { ...mm, link_previews: e.link_previews } : mm)),
                };
            });
        },
    });

    useEffect(() => {
        const el = scrollRef.current;
        if (!el) return;
        const distanceFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
        if (distanceFromBottom < 120) {
            el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
        }
    }, [activeConv?.messages.length, activeConv?.messages[activeConv.messages.length - 1]?.id]);

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
        if (active?.id) {
            setActiveId(active.id);
            setActiveConv(active);
            setHasMore(active.has_more_messages ?? false);
        }
    }, [active?.id, active?.last_message_at, active?.messages_count]);

    useEffect(() => {
        if (!activeId) return;
        fetch(`/admin/chats/${activeId}/read`, { method: 'POST', headers: csrfHeaders(), credentials: 'same-origin' }).catch(() => {});
    }, [activeId]);

    // Infinite scroll up: when scrollTop is near the top, load 10 older messages
    useEffect(() => {
        const el = scrollRef.current;
        if (!el) return;

        function handleScroll(): void {
            if (loadingMore || !hasMore || !activeConv) return;
            if (el!.scrollTop <= 80) {
                void loadOlderMessages();
            }
        }

        el.addEventListener('scroll', handleScroll, { passive: true });
        return () => el.removeEventListener('scroll', handleScroll);
    }, [loadingMore, hasMore, activeConv?.id, activeConv?.messages.length]);

    async function loadOlderMessages(): Promise<void> {
        if (!activeConv || loadingMore || !hasMore) return;
        const oldest = activeConv.messages[0];
        if (!oldest) return;
        const beforeId = oldest.id;

        const previousScrollHeight = scrollRef.current?.scrollHeight ?? 0;
        const previousScrollTop = scrollRef.current?.scrollTop ?? 0;
        setLoadingMore(true);
        try {
            const res = await fetch(`/admin/chats/${activeConv.id}/messages?before_id=${beforeId}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data: { messages: ChatMessage[]; has_more: boolean; oldest_loaded_id: number | null } = await res.json();

            setActiveConv((prev) => {
                if (!prev) return prev;
                const existingIds = new Set(prev.messages.map((m) => m.id));
                const newOnes = data.messages.filter((m) => !existingIds.has(m.id));
                return {
                    ...prev,
                    messages: [...newOnes, ...prev.messages],
                };
            });
            setHasMore(data.has_more);

            requestAnimationFrame(() => {
                const el2 = scrollRef.current;
                if (!el2) return;
                const newScrollHeight = el2.scrollHeight;
                const delta = newScrollHeight - previousScrollHeight;
                el2.scrollTop = previousScrollTop + delta;
            });
        } catch (err) {
            console.error('Failed to load older messages', err);
        } finally {
            setLoadingMore(false);
        }
    }

    useEffect(() => {
        if (attachment && attachment.type.startsWith('image/')) {
            const url = URL.createObjectURL(attachment);
            setAttachmentPreviewUrl(url);
            return () => URL.revokeObjectURL(url);
        }
        setAttachmentPreviewUrl(null);
        return undefined;
    }, [attachment]);

    function openConversation(conv: ConversationSummary): void {
        setActiveId(conv.id);
        setFilteredConversations((prevList) => {
            const base = prevList ?? (Array.isArray(safeConversations.data) ? safeConversations.data : []);

            return base.map((c) => (c.id === conv.id ? { ...c, unread: 0 } : c));
        });
        const params: Record<string, string | number> = { active: conv.id };
        if (safeFilters.search) params.search = safeFilters.search;
        if (safeFilters.channel_id !== null && safeFilters.channel_id !== undefined) params.channel_id = safeFilters.channel_id;
        if (safeFilters.status) params.status = safeFilters.status;
        router.get('/admin/chats', params, { preserveState: true, preserveScroll: true, only: ['active', 'conversations', 'stats'] });
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
        fetch(url, { method: 'GET', headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`))))
            .then((data: { conversations: ConversationSummary[] }) => setFilteredConversations(data.conversations ?? []))
            .catch(() => setFilteredConversations([]));
    }

    function pickFile(): void {
        fileInputRef.current?.click();
    }

    function onFileChange(e: React.ChangeEvent<HTMLInputElement>): void {
        setAttachment(e.target.files?.[0] ?? null);
    }

    function clearAttachment(): void {
        setAttachment(null);
        setPickedMediaId(null);
        setPickedMediaMeta(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
    }

    function applyQuickReply(reply: QuickReply): void {
        // Replace the `/query` line with the reply's content
        const lines = draft.split('\n');
        const lastIdx = lines.length - 1;
        if (qrQuery !== undefined) {
            lines[lastIdx] = reply.content ?? '';
        }
        setDraft(lines.join('\n').replace(/^\s+|\s+$/g, ''));

        if (reply.media_id) {
            setPickedMediaId(reply.media_id);
            setPickedMediaMeta({
                name: reply.media_name,
                url: reply.media_url,
                mime: reply.media_mime,
            });
        }
        setQrOpen(false);
        setQrSelectedIndex(0);
        // Refocus textarea
        setTimeout(() => draftRef.current?.focus(), 0);
    }

    function sendMessage(e: React.FormEvent): void {
        e.preventDefault();
        if (qrOpen) {
            // Enter while dropdown is open → select the highlighted reply
            e.preventDefault();
            if (qrFiltered[qrSelectedIndex]) {
                applyQuickReply(qrFiltered[qrSelectedIndex]);
            }
            return;
        }
        if (!activeId || (!draft.trim() && !attachment && !pickedMediaId)) return;
        const content = draft;
        const file = attachment;
        const mediaId = pickedMediaId;
        const mediaMeta = pickedMediaMeta;
        setDraft('');
        setAttachment(null);
        setPickedMediaId(null);
        setPickedMediaMeta(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
        setSending(true);

        const formData = new FormData();
        formData.append('content', content);
        if (file) {
            formData.append('file', file);
        } else if (mediaId) {
            formData.append('attachment_media_id', String(mediaId));
        }

        function restoreInput(): void {
            setDraft(content);
            setAttachment(file);
            setPickedMediaId(mediaId);
            setPickedMediaMeta(mediaMeta);
            if (fileInputRef.current && file) {
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInputRef.current.files = dt.files;
            }
        }

        fetch(`/admin/chats/${activeId}/reply`, { method: 'POST', headers: csrfHeaders(), credentials: 'same-origin', body: formData })
            .then(async (r) => {
                const data = await r.json().catch(() => ({}));
                if (r.ok && data?.ok) return data;
                const err = new Error(data?.error ?? `HTTP ${r.status}`);
                (err as Error & { payload?: unknown }).payload = data;
                throw err;
            })
            .then((data) => {
                if (data?.conversation) {
                    setActiveConv(data.conversation);
                    router.reload({ only: ['conversations', 'stats'] });
                }
            })
            .catch((err: Error & { payload?: { error?: string } }) => {
                restoreInput();
                toast.error('No se envió a WhatsApp', {
                    description: err.payload?.error ?? err.message ?? 'No se pudo enviar el mensaje.',
                    duration: 8000,
                });
            })
            .finally(() => setSending(false));
    }

    function addOrRemoveReaction(message: ChatMessage, emoji: string): void {
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
                        return { ...mm, reactions: (mm.reactions ?? []).filter((r) => !(r.emoji === emoji && r.user_jid === currentAdminJid)) };
                    }),
                };
            });
            fetch(url, { method: 'DELETE', headers: csrfHeaders(), credentials: 'same-origin', body: JSON.stringify({ emoji }) })
                .catch(() => toast.error('No se pudo quitar la reacción'));
            return;
        }

        const optimistic: Reaction = { id: Date.now(), user_jid: currentAdminJid, emoji, created_at: new Date().toISOString() };
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
        fetch(url, { method: 'POST', headers: csrfHeaders(), credentials: 'same-origin', body: JSON.stringify({ emoji }) })
            .catch(() => toast.error('No se pudo guardar la reacción'));
    }

    function openDeleteDialog(): void {
        if (activeId) setDeleteDialogOpen(true);
    }

    function confirmDeleteConversation(): void {
        if (!activeId) return;
        const idToDelete = activeId;
        setPendingId(idToDelete);
        setDeleteDialogOpen(false);

        // Remover del estado local inmediatamente (UX optimista)
        setFilteredConversations((prevList) => {
            const base = prevList ?? (Array.isArray(safeConversations.data) ? safeConversations.data : []);
            return base.filter((c) => c.id !== idToDelete);
        });

        router.delete(`/admin/chats/${idToDelete}`, {
            preserveScroll: true,
            onSuccess: () => {
                setActiveId(null);
                setActiveConv(null);
                setPendingId(null);
                // Refrescar la lista desde el servidor para sincronizar contadores
                router.reload({ only: ['conversations', 'stats'], preserveState: true, preserveScroll: true });
            },
            onError: () => {
                setPendingId(null);
                // Si falla, recargar para re-sincronizar el estado
                router.reload({ only: ['conversations', 'stats'], preserveState: true, preserveScroll: true });
            },
        });
    }

    return (
        <>
            <Head title="Chats" />
            <div className="flex h-full min-h-0 flex-col overflow-hidden">
                <div className="flex min-h-0 flex-1 overflow-hidden bg-card">
                    <div className="flex w-96 shrink-0 flex-col border-r">
                        <div className="space-y-2 border-b p-3">
                            <div className="flex flex-wrap items-center gap-1">
                                <button
                                    type="button"
                                    onClick={() => applyFilters({ channel_id: null })}
                                    className={cn(
                                        'cursor-pointer rounded-full px-2.5 py-1 text-xs font-medium transition',
                                        safeFilters.channel_id === null ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80',
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
                                            safeFilters.channel_id === ch.id ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80',
                                        )}
                                    >
                                        {ch.name}
                                    </button>
                                ))}
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
                                {safeStats.unread > 0 && <span className="font-medium text-primary">{safeStats.unread} sin leer</span>}
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
                                    <span>
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
                                                <img src={c.user.avatar_url} alt={c.name} className="size-10 shrink-0 rounded-full object-cover" />
                                            ) : (
                                                <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                                    {(c.name?.[0] ?? '?').toUpperCase()}
                                                </div>
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-baseline justify-between gap-2">
                                                    <p className="truncate text-sm font-medium">{c.name}</p>
                                                    {c.unread > 0 ? (
                                                        <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-primary-foreground">
                                                            {c.unread}
                                                        </span>
                                                    ) : (
                                                        <p className="shrink-0 text-[10px] text-muted-foreground">{c.last_message_at_diff ?? ''}</p>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-1.5 truncate text-[11px]">
                                                    {c.channel_name && (
                                                        <>
                                                            <span className="font-semibold text-primary">{c.channel_name}</span>
                                                            <span className="text-muted-foreground/50">|</span>
                                                        </>
                                                    )}
                                                    <span className="font-mono text-muted-foreground">{c.visitor_phone ?? ''}</span>
                                                </div>
                                            </div>
                                        </button>
                                    );
                                })
                            )}
                        </div>
                    </div>

                    <div className="flex min-w-0 flex-1 flex-col">
                        {!activeConv ? (
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
                                            <img src={activeConv.user_avatar_url} alt={activeConv.name} className="size-9 shrink-0 rounded-full object-cover" />
                                        ) : (
                                            <div className="flex size-9 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                                {(activeConv.name?.[0] ?? '?').toUpperCase()}
                                            </div>
                                        )}
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <p className="truncate text-sm font-semibold">{activeConv.name}</p>
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
                                            {pendingId === activeConv.id ? <Loader2 className="size-4 animate-spin" /> : <Trash2 className="size-4" />}
                                        </Button>
                                    </div>
                                </button>

                                <div ref={scrollRef} className="flex-1 space-y-2 overflow-y-auto bg-muted/30 p-4">
                                    {loadingMore && (
                                        <div className="flex items-center justify-center py-2 text-xs text-muted-foreground">
                                            <Loader2 className="mr-2 size-3 animate-spin" />
                                            Cargando mensajes anteriores...
                                        </div>
                                    )}
                                    {!hasMore && activeConv.messages.length > 0 && (
                                        <div className="flex items-center justify-center py-2 text-[10px] uppercase tracking-wider text-muted-foreground/60">
                                            Inicio de la conversación
                                        </div>
                                    )}
                                    {activeConv.messages.length === 0 ? (
                                        <p className="text-center text-sm text-muted-foreground">Aún no hay mensajes</p>
                                    ) : (
                                        activeConv.messages.map((m) => (
                                            <MessageBubble
                                                key={m.id}
                                                m={m}
                                                isMine={m.role === 'admin'}
                                                currentAdminJid={currentAdminJid}
                                                onReact={addOrRemoveReaction}
                                                onLightbox={setLightboxUrl}
                                            />
                                        ))
                                    )}
                                </div>

                                <form onSubmit={sendMessage} className="space-y-2 border-t bg-card p-3">
                                    {attachment && (
                                        <div className="flex items-center gap-2 rounded-md border bg-muted/30 px-3 py-2 text-xs">
                                            {attachmentPreviewUrl ? (
                                                <img src={attachmentPreviewUrl} alt="adjunto" className="size-10 shrink-0 rounded object-cover" />
                                            ) : (
                                                <FileImage className="size-5 shrink-0" />
                                            )}
                                            <span className="truncate font-medium">{attachment.name}</span>
                                            <span className="shrink-0 text-muted-foreground">{formatBytes(attachment.size)}</span>
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
                                    {!attachment && pickedMediaId && pickedMediaMeta && (
                                        <div className="flex items-center gap-2 rounded-md border bg-yellow-50 px-3 py-2 text-xs">
                                            {pickedMediaMeta.mime?.startsWith('image/') && pickedMediaMeta.url ? (
                                                <img src={pickedMediaMeta.url} alt="" className="size-10 shrink-0 rounded object-cover" />
                                            ) : (
                                                <Zap className="size-5 shrink-0 text-yellow-600" />
                                            )}
                                            <span className="truncate font-medium">{pickedMediaMeta.name ?? 'Archivo de respuesta rápida'}</span>
                                            {pickedMediaMeta.mime && <span className="shrink-0 text-muted-foreground">{pickedMediaMeta.mime}</span>}
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
                                    <div className="relative flex items-center gap-2">
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
                                            title="Adjuntar archivo"
                                        >
                                            <Paperclip className="size-4" />
                                        </Button>
                                        <div className="relative flex-1">
                                            <QuickReplyDropdown
                                                open={qrOpen}
                                                query={qrQuery}
                                                replies={quickReplies}
                                                selectedIndex={qrSelectedIndex}
                                                loading={quickRepliesLoading}
                                                onSelect={applyQuickReply}
                                                onHover={setQrSelectedIndex}
                                                onClose={() => {
                                                    setQrOpen(false);
                                                    setQrSelectedIndex(0);
                                                }}
                                            />
                                            <textarea
                                                ref={draftRef}
                                                value={draft}
                                                onChange={(e) => {
                                                    const value = e.target.value;
                                                    setDraft(value);
                                                    const lines = value.split('\n');
                                                    const lastLine = lines[lines.length - 1] ?? '';
                                                    if (/^\/[a-zA-Z0-9_-]*$/.test(lastLine)) {
                                                        setQrOpen(true);
                                                        setQrSelectedIndex(0);
                                                    } else {
                                                        setQrOpen(false);
                                                    }
                                                }}
                                                onPaste={(e) => {
                                                    const items = e.clipboardData?.items;
                                                    if (!items) return;
                                                    for (let i = 0; i < items.length; i += 1) {
                                                        const item = items[i];
                                                        if (item.kind === 'file' && item.type.startsWith('image/')) {
                                                            e.preventDefault();
                                                            const file = item.getAsFile();
                                                            if (file) setAttachment(file);
                                                            return;
                                                        }
                                                    }
                                                }}
                                                onDragOver={(e) => e.preventDefault()}
                                                onDrop={(e) => {
                                                    e.preventDefault();
                                                    const file = e.dataTransfer?.files?.[0];
                                                    if (file) setAttachment(file);
                                                }}
                                                onKeyDown={(e) => {
                                                    if (qrOpen) {
                                                        if (e.key === 'ArrowDown') {
                                                            e.preventDefault();
                                                            setQrSelectedIndex((i) => Math.min(i + 1, qrFiltered.length - 1));
                                                            return;
                                                        }
                                                        if (e.key === 'ArrowUp') {
                                                            e.preventDefault();
                                                            setQrSelectedIndex((i) => Math.max(i - 1, 0));
                                                            return;
                                                        }
                                                        if (e.key === 'Tab' || (e.key === 'Enter' && qrFiltered.length > 0)) {
                                                            e.preventDefault();
                                                            if (qrFiltered[qrSelectedIndex]) {
                                                                applyQuickReply(qrFiltered[qrSelectedIndex]);
                                                            }
                                                            return;
                                                        }
                                                        if (e.key === 'Escape') {
                                                            e.preventDefault();
                                                            setQrOpen(false);
                                                            return;
                                                        }
                                                    }
                                                    if (e.key === 'Enter' && !e.shiftKey) {
                                                        e.preventDefault();
                                                        sendMessage(e as unknown as React.FormEvent);
                                                    }
                                                }}
                                                placeholder={attachment ? 'Añade un comentario (opcional)...' : 'Escribe una respuesta, / para respuestas rápidas, o pega una imagen...'}
                                                className="flex w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm leading-6"
                                                rows={1}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={sending || (!draft.trim() && !attachment && !pickedMediaId)}
                                            className="h-10 w-10"
                                            size="icon"
                                            title="Enviar"
                                        >
                                            {sending ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
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
                                    detailsOpen ? 'translate-x-0 xl:block' : 'pointer-events-none translate-x-[calc(100%+1rem)] opacity-0 xl:hidden',
                                )}
                                aria-hidden={!detailsOpen}
                            >
                                <ChatDetailsPanel active={activeConv} onClose={() => setDetailsOpen(false)} />
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
                                    <DialogDescription>Esta acción no se puede deshacer.</DialogDescription>
                                </div>
                            </div>
                        </DialogHeader>
                        <div className="space-y-2 text-sm text-muted-foreground">
                            <p>
                                ¿Estás seguro de eliminar la conversación con <span className="font-semibold text-foreground">{activeConv?.name ?? 'este usuario'}</span>?
                            </p>
                            <p>
                                Se eliminarán <span className="font-medium text-foreground">permanentemente</span> los mensajes y todos los archivos adjuntos (imágenes, videos, audios, documentos) tanto de la base de datos como del servidor.
                            </p>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDeleteDialogOpen(false)} disabled={pendingId === activeConv?.id}>
                                No, cancelar
                            </Button>
                            <Button type="button" variant="destructive" onClick={confirmDeleteConversation} disabled={pendingId === activeConv?.id}>
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
                            {lightboxUrl && <img src={lightboxUrl} alt="" className="max-h-[85vh] max-w-full rounded-lg object-contain" />}
                        </div>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

ChatsIndex.layout = (page: React.ReactNode) => <ChatLayout>{page}</ChatLayout>;

ChatsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Chats', href: '/admin/chats' },
    ],
};
