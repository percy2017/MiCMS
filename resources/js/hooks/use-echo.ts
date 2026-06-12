import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { useEffect, useRef, useState } from 'react';

type EchoInstance = Echo<'reverb'>;

let globalEcho: EchoInstance | null = null;

function createEcho(): EchoInstance {
    if (globalEcho) {
        return globalEcho;
    }

    const pusherClient = new Pusher(import.meta.env.VITE_REVERB_APP_KEY, {
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
        wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 6001),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 6001),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        channelAuthorization: {
            endpoint: '/broadcasting/auth',
            transport: 'ajax',
        },
    });

    globalEcho = new Echo({
        broadcaster: 'reverb',
        client: pusherClient,
        authEndpoint: '/broadcasting/auth',
    } as any) as EchoInstance;

    return globalEcho;
}

type EchoEvent = {
    channel: string;
    event: string;
    data: unknown;
    timestamp: number;
};

export function useEcho() {
    const [echo] = useState(createEcho);
    const [isConnected, setIsConnected] = useState(false);
    const channelsRef = useRef<Map<string, { unsubscribe: () => void }>>(new Map());
    const eventsRef = useRef<EchoEvent[]>([]);
    const [version, setVersion] = useState(0);

    useEffect(() => {
        const checkConnection = setInterval(() => {
            try {
                const state = echo.connector?.pusher?.connection?.state;
                setIsConnected(state === 'connected');
            } catch {
                setIsConnected(false);
            }
        }, 2000);

        return () => clearInterval(checkConnection);
    }, [echo]);

    const subscribe = (channel: string) => {
        if (channelsRef.current.has(channel)) {
            return;
        }

        const echoChannel = echo.channel(channel);
        const listeners: string[] = [];

        const listen = (event: string) => {
            echoChannel.listen(event, (data: unknown) => {
                eventsRef.current = [
                    ...eventsRef.current,
                    { channel, event, data, timestamp: Date.now() },
                ].slice(-500);
                setVersion((v) => v + 1);
            });
            listeners.push(event);
        };

        listen('.MessageSent');
        listen('.ChannelCreated');
        listen('.ChannelRemoved');
        listen('.App\\Events\\DashboardEvent');

        channelsRef.current.set(channel, {
            unsubscribe: () => {
                listeners.forEach((e) => echoChannel.stopListening(e));
                echo.leave(channel);
            },
        });
    };

    const unsubscribe = (channel: string) => {
        const entry = channelsRef.current.get(channel);

        if (entry) {
            entry.unsubscribe();
            channelsRef.current.delete(channel);
        }
    };

    const getEvents = () => eventsRef.current;

    const clearEvents = () => {
        eventsRef.current = [];
        setVersion((v) => v + 1);
    };

    return { echo, isConnected, subscribe, unsubscribe, getEvents, clearEvents, version };
}
