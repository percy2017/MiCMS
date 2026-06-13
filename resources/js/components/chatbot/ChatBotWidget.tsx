import { useEffect, useState } from 'react';
import { MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ChatBotPanel } from '@/components/chatbot/ChatBotPanel';

type WidgetConfig = {
    enabled: boolean;
    key?: string;
    name?: string;
    title: string;
    subtitle?: string | null;
    greeting?: string | null;
    position: 'left' | 'right';
    require_auth: boolean;
    show_typing: boolean;
    offline_message?: string | null;
    avatar_url?: string | null;
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

function detectChannelKey(): string | null {
    if (typeof document === 'undefined') return null;

    const current = document.currentScript as HTMLScriptElement | null;
    if (current?.dataset.channel) {
        return current.dataset.channel;
    }

    const scripts = document.querySelectorAll<HTMLScriptElement>('script[data-channel]');
    for (const s of Array.from(scripts)) {
        if (s.src && s.src === (document.currentScript as HTMLScriptElement | null)?.src) {
            return s.dataset.channel ?? null;
        }
    }
    return scripts[0]?.dataset.channel ?? null;
}

export function ChatBotWidget() {
    const [open, setOpen] = useState(false);
    const [config, setConfig] = useState<WidgetConfig | null>(null);

    useEffect(() => {
        if (window.location.pathname.startsWith('/admin')) {
            return;
        }

        const key = detectChannelKey();
        if (! key) {
            return;
        }

        const url = `/api/chatbot/widget?key=${encodeURIComponent(key)}`;

        fetch(url, { headers: csrfHeaders(), credentials: 'same-origin' })
            .then((r) => (r.ok ? r.json() : null))
            .then((data) => {
                if (data && data.enabled) {
                    setConfig(data);
                }
            })
            .catch(() => {});
    }, []);

    if (! config) {
        return null;
    }

    const positionClass = config.position === 'left' ? 'left-4' : 'right-4';

    return (
        <div className={`fixed bottom-4 z-50 ${positionClass}`}>
            {open ? (
                <ChatBotPanel config={config} onClose={() => setOpen(false)} />
            ) : (
                <Button
                    type="button"
                    onClick={() => setOpen(true)}
                    size="icon"
                    className="size-14 rounded-full shadow-2xl"
                    aria-label="Abrir chat"
                >
                    {config.avatar_url ? (
                        <img
                            src={config.avatar_url}
                            alt=""
                            className="size-14 rounded-full object-cover"
                        />
                    ) : (
                        <MessageCircle className="size-6" />
                    )}
                </Button>
            )}
        </div>
    );
}
