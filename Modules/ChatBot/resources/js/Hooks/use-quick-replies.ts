import { useCallback, useEffect, useState } from 'react';
import type { QuickReply } from '@/components/chat/QuickReplyDropdown';

let cache: QuickReply[] | null = null;
let inflight: Promise<QuickReply[]> | null = null;

async function loadReplies(): Promise<QuickReply[]> {
    if (cache) return cache;
    if (inflight) return inflight;

    inflight = fetch('/api/chatbot/quick-replies', {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    })
        .then(async (r) => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            const data = await r.json();
            return Array.isArray(data.replies) ? data.replies : [];
        })
        .finally(() => {
            inflight = null;
        });

    cache = await inflight;
    return cache;
}

export function useQuickReplies(): {
    replies: QuickReply[];
    loading: boolean;
    error: string | null;
    refresh: () => Promise<void>;
} {
    const [replies, setReplies] = useState<QuickReply[]>(cache ?? []);
    const [loading, setLoading] = useState<boolean>(cache === null);
    const [error, setError] = useState<string | null>(null);

    const refresh = useCallback(async (): Promise<void> => {
        setLoading(true);
        setError(null);
        try {
            cache = null;
            const list = await loadReplies();
            setReplies(list);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Error cargando respuestas');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (cache !== null) return;
        let cancelled = false;
        setLoading(true);
        loadReplies()
            .then((list) => {
                if (!cancelled) setReplies(list);
            })
            .catch((err) => {
                if (!cancelled) setError(err instanceof Error ? err.message : 'Error');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });
        return () => {
            cancelled = true;
        };
    }, []);

    return { replies, loading, error, refresh };
}
