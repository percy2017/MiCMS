import { useEffect, useState } from 'react';

export type PosWooOrder = {
    id: number;
    number?: string;
    status: string;
    total: string;
    date_created: string;
    customer_name?: string | null;
    customer_phone?: string | null;
    chat_conversation_id?: number | null;
    user_id?: number | null;
    avatar_url?: string | null;
    [key: string]: unknown;
};

type Result = {
    orders: PosWooOrder[];
    total: number;
    loading: boolean;
    error: string | null;
    refetch: () => void;
};

export function usePosWooOrdersByPhone(phone: string | null): Result {
    const [orders, setOrders] = useState<PosWooOrder[]>([]);
    const [total, setTotal] = useState(0);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [reloadKey, setReloadKey] = useState(0);

    useEffect(() => {
        if (!phone) {
            setOrders([]);
            setTotal(0);
            setError(null);

            return;
        }

        const ac = new AbortController();
        setLoading(true);
        setError(null);

        fetch(`/admin/pos-woo/orders/by-phone?phone=${encodeURIComponent(phone)}`, {
            signal: ac.signal,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => {
                if (!r.ok) {
                    throw new Error(`HTTP ${r.status}`);
                }
                return r.json();
            })
            .then((data: { data?: PosWooOrder[]; total?: number; error?: string | null }) => {
                setOrders(data.data ?? []);
                setTotal(data.total ?? 0);
                if (data.error) {
                    setError(data.error);
                }
            })
            .catch((e: unknown) => {
                if (e instanceof DOMException && e.name === 'AbortError') {
                    return;
                }
                if (e instanceof Error) {
                    setError(e.message);
                } else {
                    setError('Error desconocido');
                }
            })
            .finally(() => {
                setLoading(false);
            });

        return () => {
            ac.abort();
        };
    }, [phone, reloadKey]);

    return {
        orders,
        total,
        loading,
        error,
        refetch: () => setReloadKey((k) => k + 1),
    };
}
