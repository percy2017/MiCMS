import type { LinkPreviews } from './link-preview';

export type Channel = {
    id: number;
    name: string;
    type: string;
};

export type Reaction = {
    id: number;
    user_jid: string;
    emoji: string;
    created_at?: string | null;
};

export type ChatMessage = {
    id: number;
    role: 'user' | 'admin' | 'system';
    type?: string;
    content: string;
    reply_to_message_id?: number | null;
    attachment_url?: string | null;
    attachment_mime?: string | null;
    attachment_name?: string | null;
    attachment_size?: number | null;
    read_at?: string | null;
    created_at?: string | null;
    link_previews?: LinkPreviews | null;
    reactions?: Reaction[];
    metadata?: Record<string, unknown> | null;
};

export type ConversationSummary = {
    id: number;
    name: string;
    email: string | null;
    visitor_phone: string | null;
    status: 'open' | 'closed';
    unread: number;
    messages_count: number;
    last_message_at: string | null;
    last_message_at_diff: string | null;
    last_message_preview: string | null;
    channel_id: number | null;
    channel_name: string | null;
    user: { id: number; name: string; email: string; avatar_url?: string | null } | null;
};

export type ConversationDetail = {
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
    external_thread_id?: string | null;
    channel_id: number | null;
    channel_name: string | null;
    last_message_at: string | null;
    first_message_at?: string | null;
    messages_count?: number;
    messages: ChatMessage[];
};

export type ChatStats = {
    open: number;
    unread: number;
    total: number;
};

export type ChatFilters = {
    search: string;
    status: string | null;
    channel_id: number | null;
};

export type ChatPageProps = {
    conversations?: { data: ConversationSummary[] };
    stats?: ChatStats;
    channels?: Channel[];
    filters?: ChatFilters;
    active: ConversationDetail | null;
};
