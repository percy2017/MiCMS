import { router, usePage } from '@inertiajs/react';
import { Building2, Copy, ExternalLink, MessageSquare, Phone, Search, ShieldOff, User as UserIcon, VolumeX, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type ConversationDetail = {
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
    channel_id: number | null;
    channel_name: string | null;
    last_message_at: string | null;
    messages: Array<{ id: number }>;
};

type Props = {
    active: ConversationDetail;
    onClose: () => void;
    totalMessages: number;
    firstMessageAt: string | null;
};

function csrfHeaders(): Record<string, string> {
    const match = document.cookie.match(new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]*)'));
    const token = match ? decodeURIComponent(match[2]) : null;
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-XSRF-TOKEN': token } : {}),
    };
}

function copyToClipboard(text: string, label: string): void {
    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(text).then(
            () => toast.success(`${label} copiado`),
            () => toast.error('No se pudo copiar'),
        );
    } else {
        toast.error('Clipboard no disponible');
    }
}

function DataRow({
    icon: Icon,
    label,
    children,
    copyable,
    copyValue,
}: {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    children: React.ReactNode;
    copyable?: boolean;
    copyValue?: string;
}) {
    return (
        <div className="flex items-start gap-3 py-2">
            <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <div className="min-w-0 flex-1">
                <p className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                    {label}
                </p>
                <div className="mt-0.5 flex items-center gap-1.5">
                    <div className="min-w-0 flex-1 truncate text-sm">{children}</div>
                    {copyable && copyValue && (
                        <button
                            type="button"
                            onClick={() => copyToClipboard(copyValue, label)}
                            className="shrink-0 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            title={`Copiar ${label.toLowerCase()}`}
                        >
                            <Copy className="size-3.5" />
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

function ActionIcon({
    icon: Icon,
    label,
    onClick,
    disabled,
}: {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    onClick?: () => void;
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={label}
            className={cn(
                'flex size-10 items-center justify-center rounded-full transition',
                disabled
                    ? 'cursor-not-allowed text-muted-foreground/50'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
            )}
        >
            <Icon className="size-5" />
        </button>
    );
}

export default function ChatDetailsPanel({ active, onClose, totalMessages, firstMessageAt }: Props) {
    const page = usePage();
    const userId = (page.props as { auth?: { user?: { id: number; name: string; avatar_url?: string | null } } }).auth?.user?.id;
    const userName = (page.props as { auth?: { user?: { name: string } } }).auth?.user?.name;
    const isBusiness = active.user_whatsapp_jid?.includes('@business') ?? false;

    const [togglingStatus, setTogglingStatus] = useState(false);
    const [note, setNote] = useState('');

    function toggleStatus(): void {
        const newStatus = active.status === 'open' ? 'closed' : 'open';
        setTogglingStatus(true);
        fetch(`/admin/chats/${active.id}/status`, {
            method: 'PATCH',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ status: newStatus }),
        })
            .then((r) => {
                if (r.ok) {
                    toast.success(newStatus === 'open' ? 'Conversación abierta' : 'Conversación cerrada');
                    router.reload({ only: ['active', 'conversations'] });
                } else {
                    toast.error('No se pudo cambiar el estado');
                }
            })
            .catch(() => toast.error('Error de red'))
            .finally(() => setTogglingStatus(false));
    }

    return (
        <aside className="flex h-full w-full flex-col border-l bg-card">
            <div className="flex shrink-0 items-center justify-between border-b px-4 py-3">
                <h2 className="text-sm font-semibold">Detalles</h2>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={onClose}
                    title="Cerrar (ESC)"
                    className="size-8"
                >
                    <X className="size-4" />
                </Button>
            </div>

            <div className="flex-1 overflow-y-auto">
                <div className="flex flex-col items-center gap-2 border-b px-4 py-6 text-center">
                    {active.user_avatar_url ? (
                        <img
                            src={active.user_avatar_url}
                            alt={active.name}
                            className="size-24 rounded-full object-cover"
                        />
                    ) : (
                        <div className="flex size-24 items-center justify-center rounded-full bg-primary/10 text-2xl font-semibold text-primary">
                            {(active.name?.[0] ?? '?').toUpperCase()}
                        </div>
                    )}
                    <div className="min-w-0">
                        <h3 className="truncate text-base font-semibold">{active.name}</h3>
                        {active.user_phone && (
                            <p className="mt-0.5 text-sm text-muted-foreground">{active.user_phone}</p>
                        )}
                        {isBusiness && (
                            <span className="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-600 dark:text-emerald-400">
                                <Building2 className="size-3" />
                                Business
                            </span>
                        )}
                    </div>
                </div>

                <div className="flex items-center justify-center gap-1 border-b px-4 py-3">
                    <ActionIcon icon={Phone} label="Llamar (próximamente)" disabled />
                    <ActionIcon icon={MessageSquare} label="Mensaje (próximamente)" disabled />
                    <ActionIcon
                        icon={Search}
                        label="Buscar en la conversación"
                        onClick={() => toast.info('Función próximamente')}
                    />
                    <ActionIcon icon={VolumeX} label="Silenciar (próximamente)" disabled />
                    <ActionIcon icon={ShieldOff} label="Bloquear (próximamente)" disabled />
                </div>

                <div className="border-b px-4 py-3">
                    <p className="mb-1 px-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                        Datos del contacto
                    </p>
                    <div className="divide-y divide-border">
                        <DataRow icon={UserIcon} label="Nombre">
                            <span className="truncate">{active.name}</span>
                        </DataRow>

                        <DataRow
                            icon={Phone}
                            label="Teléfono"
                            copyable
                            copyValue={active.user_phone ?? ''}
                        >
                            {active.user_phone ?? <span className="text-muted-foreground">—</span>}
                        </DataRow>

                        <DataRow
                            icon={MessageSquare}
                            label="JID"
                            copyable
                            copyValue={active.user_whatsapp_jid ?? active.external_id ?? ''}
                        >
                            <span className="font-mono text-xs">
                                {active.user_whatsapp_jid ?? active.external_id ?? <span className="text-muted-foreground">—</span>}
                            </span>
                        </DataRow>

                        <DataRow icon={MessageSquare} label="Canal">
                            {active.channel_name ? (
                                <span className="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                                    {active.channel_name}
                                </span>
                            ) : (
                                <span className="text-muted-foreground">—</span>
                            )}
                        </DataRow>

                        <DataRow icon={ExternalLink} label="Página de origen">
                            {active.page_url ? (
                                <a
                                    href={active.page_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 text-primary hover:underline"
                                >
                                    <span className="truncate">{active.page_url}</span>
                                    <ExternalLink className="size-3 shrink-0" />
                                </a>
                            ) : (
                                <span className="text-muted-foreground">—</span>
                            )}
                        </DataRow>

                        <DataRow icon={UserIcon} label="Usuario">
                            {active.user_id ? (
                                <a
                                    href={`/admin/usuarios/${active.user_id}/editar`}
                                    className="inline-flex items-center gap-1 text-primary hover:underline"
                                >
                                    <span>Ver perfil #{active.user_id}</span>
                                    <ExternalLink className="size-3 shrink-0" />
                                </a>
                            ) : (
                                <span className="text-muted-foreground">—</span>
                            )}
                        </DataRow>

                        <DataRow icon={MessageSquare} label="Estado">
                            <div className="flex items-center gap-2">
                                <span
                                    className={cn(
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium',
                                        active.status === 'open'
                                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {active.status === 'open' ? 'Abierta' : 'Cerrada'}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={toggleStatus}
                                    disabled={togglingStatus}
                                    className="h-6 px-2 text-[10px]"
                                >
                                    {active.status === 'open' ? 'Cerrar' : 'Abrir'}
                                </Button>
                            </div>
                        </DataRow>

                        <DataRow icon={MessageSquare} label="Primer mensaje">
                            {firstMessageAt ? (
                                <span className="text-xs">
                                    {new Date(firstMessageAt).toLocaleString([], {
                                        day: '2-digit',
                                        month: '2-digit',
                                        year: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                </span>
                            ) : (
                                <span className="text-muted-foreground">—</span>
                            )}
                        </DataRow>

                        <DataRow icon={MessageSquare} label="Total mensajes">
                            <span className="text-xs">
                                {totalMessages} {totalMessages === 1 ? 'mensaje' : 'mensajes'}
                            </span>
                        </DataRow>

                        <DataRow icon={MessageSquare} label="Último mensaje">
                            {active.last_message_at ? (
                                <span className="text-xs">
                                    {new Date(active.last_message_at).toLocaleString([], {
                                        day: '2-digit',
                                        month: '2-digit',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                </span>
                            ) : (
                                <span className="text-muted-foreground">—</span>
                            )}
                        </DataRow>
                    </div>
                </div>

                <div className="px-4 py-3">
                    <p className="mb-1 px-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                        Notas internas
                    </p>
                    <textarea
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        placeholder="Anota algo sobre este cliente…"
                        className="w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                        rows={4}
                    />
                    <p className="mt-1 px-1 text-[10px] text-muted-foreground">
                        Las notas son locales y no se guardan en el servidor.
                    </p>
                </div>

                {userId && userName && (
                    <div className="border-t px-4 py-3 text-center text-[10px] text-muted-foreground">
                        Atendido por <span className="font-medium text-foreground">{userName}</span>
                    </div>
                )}
            </div>
        </aside>
    );
}
