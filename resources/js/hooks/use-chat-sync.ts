import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { useChatBotEcho, type ChatBotEventPayload, type ChatBotReactionEventPayload, type LinkPreviewsReadyPayload } from './use-chat-echo';

type WsStatus = 'connecting' | 'connected' | 'disconnected' | 'failed';

type UseChatSyncOptions = {
    activeConvId: number | null;
    pollIntervalMs?: number;
    onMessage?: (e: ChatBotEventPayload) => void;
    onReaction?: (e: ChatBotReactionEventPayload) => void;
    onLinkPreviewsReady?: (e: LinkPreviewsReadyPayload) => void;
};

export function useChatSync({ activeConvId, pollIntervalMs = 8000, onMessage, onReaction, onLinkPreviewsReady }: UseChatSyncOptions) {
    const [wsStatus, setWsStatus] = useState<WsStatus>('connecting');
    const callbacksRef = useRef({ onMessage, onReaction, onLinkPreviewsReady });
    callbacksRef.current = { onMessage, onReaction, onLinkPreviewsReady };

    const { status } = useChatBotEcho({
        enabled: true,
        onMessage: (e) => {
            callbacksRef.current.onMessage?.(e);
        },
        onReaction: (e) => {
            callbacksRef.current.onReaction?.(e);
        },
        onLinkPreviewsReady: (e) => {
            callbacksRef.current.onLinkPreviewsReady?.(e);
        },
    });

    useEffect(() => {
        setWsStatus(status);
    }, [status]);

    useEffect(() => {
        if (!activeConvId) {
            return;
        }
        const interval = setInterval(() => {
            router.reload({ only: ['active', 'conversations', 'stats'], preserveState: true, preserveScroll: true });
        }, pollIntervalMs);
        return () => clearInterval(interval);
    }, [activeConvId, pollIntervalMs]);

    useEffect(() => {
        const onFocus = (): void => {
            if (activeConvId) {
                router.reload({ only: ['active', 'conversations', 'stats'], preserveState: true, preserveScroll: true });
            }
        };
        window.addEventListener('focus', onFocus);
        return () => window.removeEventListener('focus', onFocus);
    }, [activeConvId]);

    useEffect(() => {
        if (status === 'connected') {
            router.reload({ only: ['active', 'conversations', 'stats'], preserveState: true, preserveScroll: true });
        }
    }, [status]);

    return { wsStatus };
}
