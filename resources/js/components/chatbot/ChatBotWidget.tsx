import { useEffect, useState } from 'react';
import { MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ChatBotPanel } from '@/components/chatbot/ChatBotPanel';

type WidgetConfig = {
    enabled: boolean;
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

export function ChatBotWidget() {
    const [open, setOpen] = useState(false);
    const [config, setConfig] = useState<WidgetConfig | null>(null);

    useEffect(() => {
        if (window.location.pathname.startsWith('/admin')) {
            return;
        }
        fetch('/api/chatbot/widget', { headers: csrfHeaders(), credentials: 'same-origin' })
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
