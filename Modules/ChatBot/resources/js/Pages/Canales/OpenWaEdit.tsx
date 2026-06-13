import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Loader2, Plus, RefreshCw, Save, Trash2, Wifi, WifiOff } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { admin } from '@/routes';

type ChannelProps = {
    id: number;
    type: string;
    name: string;
    enabled: boolean;
    config: {
        session_name: string;
    };
    settings: {
        display_name?: string;
        auto_reply?: string;
    };
};

type OpenWaSession = {
    name: string;
    status: string;
    phone: string | null;
    push_name: string | null;
    already_linked: boolean;
};

type AvailableResponse = {
    configured: boolean;
    sessions: OpenWaSession[];
    error?: string;
};

type PageProps = { channel: ChannelProps };

export default function OpenWaEdit({ channel }: PageProps) {
    const [sessions, setSessions] = useState<OpenWaSession[]>([]);
    const [loadingSessions, setLoadingSessions] = useState(false);
    const [sessionsError, setSessionsError] = useState('');
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const webhookUrl = `${window.location.origin}/api/webhooks/openwa/${channel.id}`;

    const form = useForm({
        enabled: channel.enabled,
        config: {
            session_name: channel.config.session_name,
        },
        settings: {
            display_name: channel.settings.display_name ?? channel.name,
            auto_reply: channel.settings.auto_reply ?? '',
        },
    });

    async function fetchSessions(): Promise<void> {
        setLoadingSessions(true);
        setSessionsError('');
        try {
            const res = await fetch('/admin/canales/openwa/available', {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data: AvailableResponse = await res.json();
            if (!data.configured) {
                setSessionsError(data.error ?? 'OpenWA no configurado');
                return;
            }
            setSessions(data.sessions);
        } catch {
            setSessionsError('Error de red');
        } finally {
            setLoadingSessions(false);
        }
    }

    useEffect(() => {
        fetchSessions();
    }, []);

    function selectSession(name: string): void {
        form.setData('config', { session_name: name });
    }

    function handleSubmit(e: React.FormEvent): void {
        e.preventDefault();
        form.patch(`/admin/canales/openwa/${channel.id}`, { preserveScroll: true });
    }

    function confirmDelete(): void {
        setDeleting(true);
        router.delete(`/admin/canales/openwa/${channel.id}`, {
            onFinish: () => setDeleting(false),
        });
    }

    return (
        <>
            <Head title={`WhatsApp (OpenWA) — ${channel.name}`} />
            <div className="h-full min-h-0 space-y-4 overflow-y-auto p-4">
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Sesión</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={fetchSessions}
                                            disabled={loadingSessions}
                                        >
                                            {loadingSessions ? (
                                                <Loader2 className="mr-2 size-4 animate-spin" />
                                            ) : (
                                                <RefreshCw className="mr-2 size-4" />
                                            )}
                                            {loadingSessions ? 'Cargando...' : 'Listar sesiones'}
                                        </Button>
                                        {sessionsError && (
                                            <p className="mt-2 text-sm text-destructive">{sessionsError}</p>
                                        )}
                                    </div>

                                    {sessions.length > 0 && (
                                        <div className="grid gap-2">
                                            <Label>Selecciona una sesión</Label>
                                            <div className="grid gap-2 max-h-80 overflow-y-auto">
                                                {sessions.map((s) => (
                                                    <button
                                                        key={s.name}
                                                        type="button"
                                                        onClick={() => selectSession(s.name)}
                                                        className={`flex items-center gap-3 rounded-md border px-3 py-2 text-left text-sm transition hover:bg-muted ${
                                                            form.data.config.session_name === s.name
                                                                ? 'border-primary bg-primary/5'
                                                                : ''
                                                        } ${s.already_linked && s.name !== channel.config.session_name ? 'opacity-50' : ''}`}
                                                    >
                                                        <div className="min-w-0 flex-1">
                                                            <div className="font-medium">{s.name}</div>
                                                            {s.phone && (
                                                                <div className="truncate text-xs text-muted-foreground">
                                                                    +{s.phone}
                                                                </div>
                                                            )}
                                                        </div>
                                                        {s.status === 'CONNECTED' ? (
                                                            <Wifi className="size-4 shrink-0 text-green-600" />
                                                        ) : (
                                                            <WifiOff className="size-4 shrink-0 text-muted-foreground" />
                                                        )}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    <div className="grid gap-2">
                                        <Label htmlFor="session_name">Nombre de la sesión</Label>
                                        <Input
                                            id="session_name"
                                            value={form.data.config.session_name}
                                            onChange={(e) =>
                                                form.setData('config', { session_name: e.target.value })
                                            }
                                            placeholder="Selecciona o escribe el nombre"
                                            required
                                        />
                                        {form.errors['config.session_name'] && (
                                            <p className="text-sm text-destructive">
                                                {form.errors['config.session_name']}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Webhook URL</Label>
                                        <code className="block rounded-md border bg-muted px-3 py-2 text-xs text-muted-foreground break-all">
                                            {webhookUrl}
                                        </code>
                                        <p className="text-xs text-muted-foreground">
                                            Configura este URL manualmente en tu OpenWA. Credenciales vía .env.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Opciones</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.data.enabled}
                                            onChange={(e) => form.setData('enabled', e.target.checked)}
                                        />
                                        <span>Canal habilitado</span>
                                    </label>

                                    <div className="grid gap-2">
                                        <Label htmlFor="display_name">Nombre mostrado</Label>
                                        <Input
                                            id="display_name"
                                            value={form.data.settings.display_name}
                                            onChange={(e) =>
                                                form.setData('settings', {
                                                    ...form.data.settings,
                                                    display_name: e.target.value,
                                                })
                                            }
                                            placeholder={channel.name}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="auto_reply">Respuesta automática</Label>
                                        <textarea
                                            id="auto_reply"
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            value={form.data.settings.auto_reply}
                                            onChange={(e) =>
                                                form.setData('settings', {
                                                    ...form.data.settings,
                                                    auto_reply: e.target.value,
                                                })
                                            }
                                            placeholder="Mensaje automático al recibir un mensaje nuevo"
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? (
                                        <Loader2 className="mr-2 size-4 animate-spin" />
                                    ) : (
                                        <Save className="mr-2 size-4" />
                                    )}
                                    {form.processing ? 'Guardando…' : 'Guardar'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={() => setDeleteDialogOpen(true)}
                                >
                                    <Trash2 className="mr-2 size-4" />
                                    Eliminar
                                </Button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10">
                                <AlertTriangle className="size-5 text-destructive" />
                            </div>
                            <div>
                                <DialogTitle>Eliminar canal</DialogTitle>
                                <DialogDescription>Esta acción no se puede deshacer.</DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="space-y-2 text-sm text-muted-foreground">
                        <p>¿Estás seguro de eliminar este canal OpenWA ({channel.name})?</p>
                        <p>Las conversaciones y mensajes se mantendrán pero ya no se podrán enviar/recibir mensajes nuevos.</p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setDeleteDialogOpen(false)} disabled={deleting}>
                            No, cancelar
                        </Button>
                        <Button type="button" variant="destructive" onClick={confirmDelete} disabled={deleting}>
                            {deleting ? <Loader2 className="mr-2 size-4 animate-spin" /> : null}
                            {deleting ? 'Eliminando...' : 'Sí, eliminar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

OpenWaEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Canales', href: '/admin/canales' },
        { title: channel.name, href: `/admin/canales/openwa/${channel.id}` },
    ],
};
