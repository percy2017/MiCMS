import { useEffect, useRef, useState } from 'react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export type ChatMessage = {
    id: number;
    conversation_id: number;
    role: 'user' | 'admin' | 'system';
    type?: string | null;
    content: string;
    attachment_url?: string | null;
    attachment_mime?: string | null;
    attachment_name?: string | null;
    attachment_size?: number | null;
    created_at?: string | null;
};

export type ChatConversationSummary = {
    id: number;
    visitor_name: string;
    visitor_email: string;
    status: 'open' | 'closed';
    unread_by_admin: number;
    messages_count: number;
    last_message_at: string | null;
    channel_id: number | null;
    channel_name: string | null;
};

export type ChatBotEventPayload = {
    message: ChatMessage;
    conversation: ChatConversationSummary;
};

export type ChatBotReactionEventPayload = {
    action: 'added' | 'removed';
    message_id: number;
    conversation_id: number;
    reaction: {
        id: number;
        emoji: string;
        user_jid: string;
    };
};

export type LinkPreviewsReadyPayload = {
    message_id: number;
    conversation_id: number;
    link_previews: {
        version?: number;
        fetched_at?: string | null;
        items: Array<{
            url: string;
            final_url?: string | null;
            title?: string | null;
            description?: string | null;
            image?: string | null;
            image_width?: number | null;
            image_height?: number | null;
            site_name?: string | null;
            favicon?: string | null;
            error?: string | null;
        }>;
    } | null;
};

let echoInstance: Echo<'reverb'> | null = null;

function getEcho(): Echo<'reverb'> {
    if (echoInstance) {
        return echoInstance;
    }

    const pusherClient = new Pusher(import.meta.env.VITE_REVERB_APP_KEY, {
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
        wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        channelAuthorization: {
            endpoint: '/broadcasting/auth',
            transport: 'ajax',
        },
    });

    echoInstance = new Echo({
        broadcaster: 'reverb',
        client: pusherClient,
        authEndpoint: '/broadcasting/auth',
    } as any) as Echo<'reverb'>;

    return echoInstance;
}

export type UseChatBotEchoOptions = {
    onMessage: (payload: ChatBotEventPayload) => void;
    onReaction?: (payload: ChatBotReactionEventPayload) => void;
    onLinkPreviewsReady?: (payload: LinkPreviewsReadyPayload) => void;
    enabled?: boolean;
};

export function useChatBotEcho({ onMessage, onReaction, onLinkPreviewsReady, enabled = true }: UseChatBotEchoOptions) {
    const [status, setStatus] = useState<'connecting' | 'connected' | 'disconnected' | 'failed'>('connecting');
    const onMessageRef = useRef(onMessage);
    const onReactionRef = useRef(onReaction);
    const onLinkPreviewsRef = useRef(onLinkPreviewsReady);

    useEffect(() => {
        onMessageRef.current = onMessage;
    }, [onMessage]);

    useEffect(() => {
        onReactionRef.current = onReaction;
    }, [onReaction]);

    useEffect(() => {
        onLinkPreviewsRef.current = onLinkPreviewsReady;
    }, [onLinkPreviewsReady]);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const echo = getEcho();
        const channelName = 'chatbot.admin';

        let mounted = true;

        const subscribe = () => {
            const channel = echo.private(channelName);

            const onSubscribed = () => {
                if (mounted) {
                    console.log('[ChatBotEcho] subscription_succeeded on', channelName);
                    setStatus('connected');
                }
            };

            const onError = (err: unknown) => {
                console.error('[ChatBotEcho] subscription_error', err);
                if (mounted) {
                    setStatus('failed');
                }
            };

            channel.subscribed(onSubscribed);
            channel.error(onError);

            channel.listen('.ChatBotMessageReceived', (data: ChatBotEventPayload) => {
                console.log('[ChatBotEcho] event received', data);
                onMessageRef.current(data);
            });

            channel.listen('ChatBotMessageReceived', (data: ChatBotEventPayload) => {
                console.log('[ChatBotEcho] event received (no dot)', data);
                onMessageRef.current(data);
            });

            channel.listen('.ChatBotMessageReaction', (data: ChatBotReactionEventPayload) => {
                console.log('[ChatBotEcho] reaction event', data);
                onReactionRef.current?.(data);
            });

            channel.listen('ChatBotMessageReaction', (data: ChatBotReactionEventPayload) => {
                console.log('[ChatBotEcho] reaction event (no dot)', data);
                onReactionRef.current?.(data);
            });

            channel.listen('.LinkPreviewsReady', (data: LinkPreviewsReadyPayload) => {
                console.log('[ChatBotEcho] link previews ready', data);
                onLinkPreviewsRef.current?.(data);
            });

            channel.listen('LinkPreviewsReady', (data: LinkPreviewsReadyPayload) => {
                onLinkPreviewsRef.current?.(data);
            });

            return channel;
        };

        let channel = subscribe();

        const checkInterval = setInterval(() => {
            if (!mounted) {
                return;
            }
            try {
                const state = echo.connector?.pusher?.connection?.state;
                if (state === 'connected') {
                    setStatus((s) => (s === 'connected' ? s : 'connected'));
                } else if (state === 'failed' || state === 'unavailable') {
                    setStatus('failed');
                } else {
                    setStatus('connecting');
                }
            } catch {
                setStatus('disconnected');
            }
        }, 2000);

        const reconnectInterval = setInterval(() => {
            if (!mounted) {
                return;
            }
            try {
                const state = echo.connector?.pusher?.connection?.state;
                if (state !== 'connected' && state !== 'connecting') {
                    console.log('[ChatBotEcho] reconnecting...');
                    try {
                        echo.leave(channelName);
                    } catch {}
                    channel = subscribe();
                }
            } catch {}
        }, 10000);

        return () => {
            mounted = false;
            clearInterval(checkInterval);
            clearInterval(reconnectInterval);
            try {
                channel.stopListening('.ChatBotMessageReceived');
                channel.stopListening('ChatBotMessageReceived');
                channel.stopListening('.ChatBotMessageReaction');
                channel.stopListening('ChatBotMessageReaction');
                channel.stopListening('.LinkPreviewsReady');
                channel.stopListening('LinkPreviewsReady');
                echo.leave(channelName);
            } catch (err) {
                console.warn('[ChatBotEcho] cleanup error', err);
            }
        };
    }, [enabled]);

    return { status };
}
