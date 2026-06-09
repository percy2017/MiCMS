import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Message = {
    id: number;
    role: 'user' | 'admin' | 'system';
    content: string;
    attachment_url?: string | null;
    read_at?: string | null;
    created_at?: string;
};

type Conversation = {
    id: number;
    status: 'open' | 'closed';
    messages: Message[];
};

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

type Props = {
    config: WidgetConfig;
    onClose: () => void;
    isAdmin?: boolean;
};

type AuthState =
    | { kind: 'loading' }
    | { kind: 'guest' }
    | { kind: 'authed'; user: { id: number; name: string; email: string }; conversation: Conversation };

function csrfHeaders(): Record<string, string> {
    const match = document.cookie.match(new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]*)'));
    const token = match ? decodeURIComponent(match[2]) : null;
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-XSRF-TOKEN': token } : {}),
    };
}

async function apiGet(url: string): Promise<any> {
    const res = await fetch(url, { headers: csrfHeaders(), credentials: 'same-origin' });
    if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
    }
    return res.json();
}

async function apiPost(url: string, body: unknown): Promise<any> {
    const res = await fetch(url, {
        method: 'POST',
        headers: { ...csrfHeaders(), 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    if (!res.ok) {
        const error: any = new Error(`HTTP ${res.status}`);
        error.status = res.status;
        error.data = await res.json().catch(() => ({}));
        throw error;
    }
    return res.json();
}

export function ChatBotPanel({ config, onClose, isAdmin = false }: Props) {
    const [auth, setAuth] = useState<AuthState>({ kind: 'loading' });
    const [draft, setDraft] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [authForm, setAuthForm] = useState({
        email: '',
        password: '',
        password_confirmation: '',
        name: '',
        action: 'login' as 'login' | 'register',
    });
    const [authError, setAuthError] = useState<string | null>(null);

    useEffect(() => {
        apiGet('/api/chatbot/session')
            .then((data) => {
                if (data.authenticated) {
                    setAuth({
                        kind: 'authed',
                        user: data.user,
                        conversation: data.conversation,
                    });
                } else {
                    setAuth({ kind: 'guest' });
                }
            })
            .catch(() => setAuth({ kind: 'guest' }));
    }, []);

    function refreshConversation(): void {
        apiGet('/api/chatbot/session')
            .then((data) => {
                if (data.authenticated && data.conversation) {
                    setAuth({
                        kind: 'authed',
                        user: data.user,
                        conversation: data.conversation,
                    });
                }
            });
    }

    function handleAuthSubmit(e: React.FormEvent): void {
        e.preventDefault();
        setAuthError(null);
        setSubmitting(true);
        apiPost('/api/chatbot/session', {
            email: authForm.email,
            password: authForm.password,
            name: authForm.action === 'register' ? authForm.name : undefined,
            action: authForm.action,
        })
            .then((res) => {
                setAuth({
                    kind: 'authed',
                    user: res.user,
                    conversation: res.conversation,
                });
            })
            .catch((err) => {
                if (err.status === 422) {
                    const errors = err.data?.errors ?? {};
                    const first = Object.values(errors)[0] as string[] | undefined;
                    setAuthError(first?.[0] ?? 'Datos inválidos.');
                } else if (err.status === 401) {
                    setAuthError('Credenciales inválidas.');
                } else {
                    setAuthError('No se pudo iniciar la sesión.');
                }
            })
            .finally(() => setSubmitting(false));
    }

    function handleSend(e: React.FormEvent): void {
        e.preventDefault();
        if (auth.kind !== 'authed' || ! draft.trim()) {
            return;
        }
        const text = draft;
        setDraft('');
        setSubmitting(true);
        apiPost(`/api/chatbot/conversations/${auth.conversation.id}/messages`, { content: text })
            .then(() => refreshConversation())
            .catch(() => {
                setDraft(text);
            })
            .finally(() => setSubmitting(false));
    }

    return (
        <Card className="flex h-[500px] w-[360px] flex-col overflow-hidden border-2 shadow-2xl">
            <div className="flex items-center gap-3 border-b bg-card p-3">
                {config.avatar_url ? (
                    <img
                        src={config.avatar_url}
                        alt=""
                        className="size-9 rounded-full border-2 border-border object-cover"
                    />
                ) : (
                    <div className="flex size-9 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                        {(config.title?.[0] ?? 'C').toUpperCase()}
                    </div>
                )}
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold">{config.title}</p>
                    {config.subtitle && (
                        <p className="truncate text-xs text-muted-foreground">{config.subtitle}</p>
                    )}
                </div>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    onClick={onClose}
                    className="size-7"
                >
                    ×
                </Button>
            </div>

            <CardContent className="flex-1 overflow-y-auto p-3">
                {auth.kind === 'loading' && (
                    <p className="text-center text-sm text-muted-foreground">Cargando…</p>
                )}

                {auth.kind === 'guest' && !isAdmin && (
                    <AuthForm
                        form={authForm}
                        setForm={setAuthForm}
                        error={authError}
                        submitting={submitting}
                        onSubmit={handleAuthSubmit}
                        greeting={config.greeting}
                    />
                )}

                {(auth.kind === 'authed' || isAdmin) && auth.kind === 'authed' && (
                    <MessageList
                        messages={auth.conversation.messages}
                        currentUserName={auth.user.name}
                        showTyping={config.show_typing}
                    />
                )}
            </CardContent>

            {auth.kind === 'authed' && (
                <form
                    onSubmit={handleSend}
                    className="flex items-center gap-2 border-t bg-background p-2"
                >
                    <Input
                        value={draft}
                        onChange={(e) => setDraft(e.target.value)}
                        placeholder="Escribe un mensaje…"
                        disabled={submitting}
                        className="flex-1"
                    />
                    <Button
                        type="submit"
                        size="icon"
                        disabled={!draft.trim() || submitting}
                    >
                        ▶
                    </Button>
                </form>
            )}
        </Card>
    );
}

function AuthForm({
    form,
    setForm,
    error,
    submitting,
    onSubmit,
    greeting,
}: {
    form: { email: string; password: string; password_confirmation: string; name: string; action: 'login' | 'register' };
    setForm: (f: { email: string; password: string; password_confirmation: string; name: string; action: 'login' | 'register' }) => void;
    error: string | null;
    submitting: boolean;
    onSubmit: (e: React.FormEvent) => void;
    greeting?: string | null;
}) {
    return (
        <div className="space-y-3">
            {greeting && <p className="text-sm text-muted-foreground">{greeting}</p>}
            <form onSubmit={onSubmit} className="space-y-3">
                <div className="flex gap-2 rounded-md border p-1 text-xs">
                    <button
                        type="button"
                        onClick={() => setForm({ ...form, action: 'login' })}
                        className={cn(
                            'flex-1 rounded px-2 py-1 transition',
                            form.action === 'login'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        Iniciar sesión
                    </button>
                    <button
                        type="button"
                        onClick={() => setForm({ ...form, action: 'register' })}
                        className={cn(
                            'flex-1 rounded px-2 py-1 transition',
                            form.action === 'register'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        Crear cuenta
                    </button>
                </div>

                {form.action === 'register' && (
                    <div>
                        <Label htmlFor="cb-name">Nombre</Label>
                        <Input
                            id="cb-name"
                            value={form.name}
                            onChange={(e) => setForm({ ...form, name: e.target.value })}
                            required
                        />
                    </div>
                )}

                <div>
                    <Label htmlFor="cb-email">Email</Label>
                    <Input
                        id="cb-email"
                        type="email"
                        value={form.email}
                        onChange={(e) => setForm({ ...form, email: e.target.value })}
                        required
                    />
                </div>

                <div>
                    <Label htmlFor="cb-password">Contraseña</Label>
                    <Input
                        id="cb-password"
                        type="password"
                        value={form.password}
                        onChange={(e) => setForm({ ...form, password: e.target.value })}
                        required
                        minLength={8}
                    />
                </div>

                {form.action === 'register' && (
                    <div>
                        <Label htmlFor="cb-password-confirm">Confirmar contraseña</Label>
                        <Input
                            id="cb-password-confirm"
                            type="password"
                            value={form.password_confirmation}
                            onChange={(e) =>
                                setForm({ ...form, password_confirmation: e.target.value })
                            }
                            required
                            minLength={8}
                        />
                    </div>
                )}

                {error && <p className="text-sm text-destructive">{error}</p>}

                <Button type="submit" disabled={submitting} className="w-full">
                    {submitting
                        ? 'Enviando…'
                        : form.action === 'login'
                            ? 'Iniciar sesión'
                            : 'Crear cuenta y empezar'}
                </Button>
            </form>
        </div>
    );
}

function MessageList({
    messages,
    currentUserName,
    showTyping,
}: {
    messages: Message[];
    currentUserName: string;
    showTyping: boolean;
}) {
    if (messages.length === 0) {
        return (
            <p className="text-center text-sm text-muted-foreground">
                Hola {currentUserName}, ¿en qué podemos ayudarte?
            </p>
        );
    }
    return (
        <div className="flex flex-col gap-2">
            {messages.map((m) => {
                const mine = m.role === 'user';
                return (
                    <div
                        key={m.id}
                        className={cn(
                            'flex max-w-[80%] flex-col gap-1 rounded-lg px-3 py-2 text-sm',
                            mine
                                ? 'self-end bg-primary text-primary-foreground'
                                : 'self-start bg-muted text-foreground',
                        )}
                    >
                        <p className="whitespace-pre-wrap break-words">{m.content}</p>
                        {m.attachment_url && (
                            <img
                                src={m.attachment_url}
                                alt=""
                                className="mt-1 max-w-full rounded"
                            />
                        )}
                    </div>
                );
            })}
            {showTyping && messages[messages.length - 1]?.role === 'user' && (
                <p className="self-start text-xs text-muted-foreground">
                    <span className="animate-pulse">● ● ●</span> escribiendo
                </p>
            )}
        </div>
    );
}
