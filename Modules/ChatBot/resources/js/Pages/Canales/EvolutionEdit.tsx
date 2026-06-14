import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Check, Loader2, Save, Wifi, WifiOff, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { admin } from '@/routes';

type ChannelDetail = {
    id: number;
    name: string;
    enabled: boolean;
    instance_name: string;
    profile_name: string;
    profile_picture_url: string;
    owner_jid: string;
};

type WebhookInfo = {
    url: string;
    events: string[];
    enabled: boolean;
};

type Settings = {
    rejectCall?: boolean;
    msgCall?: string;
    groupsIgnore?: boolean;
    alwaysOnline?: boolean;
    readMessages?: boolean;
    readStatus?: boolean;
    syncFullHistory?: boolean;
    wavoipToken?: string;
} | null;

type PageProps = {
    channel: ChannelDetail;
    liveSettings: Settings;
    liveWebhook: WebhookInfo | null;
};

export default function EvolutionEdit({ channel, liveSettings, liveWebhook }: PageProps) {
    const { data, setData, patch, processing, errors } = useForm({
        enabled: channel.enabled,
        groups_ignore: Boolean(liveSettings?.groupsIgnore),
        reject_call: Boolean(liveSettings?.rejectCall),
        always_online: Boolean(liveSettings?.alwaysOnline),
        read_messages: Boolean(liveSettings?.readMessages),
        read_status: Boolean(liveSettings?.readStatus),
        sync_full_history: Boolean(liveSettings?.syncFullHistory),
        msg_call: String(liveSettings?.msgCall ?? ''),
    });

    function handleSubmit(e: React.FormEvent): void {
        e.preventDefault();
        patch(`/admin/canales/evolution/${channel.id}`);
    }

    return (
        <>
            <Head title={`Editar inbox ${channel.name}`} />
            <div className="h-full min-h-0 space-y-4 overflow-y-auto p-4">
                <header className="flex items-center gap-3">
                    <Button type="button" variant="ghost" size="icon" onClick={() => router.visit('/admin/canales')}>
                        <ArrowLeft className="size-4" />
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Editar inbox Evolution</h1>
                        <p className="text-sm text-muted-foreground">
                            Cambia la activación del canal y la configuración de Evolution.
                        </p>
                    </div>
                </header>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-4 lg:grid-cols-5">
                        {/* LEFT: info de la instancia (2/5) */}
                        <div className="space-y-4 lg:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Instancia</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center gap-3">
                                        {channel.profile_picture_url ? (
                                            <img src={channel.profile_picture_url} alt="" className="size-12 shrink-0 rounded-full object-cover" />
                                        ) : (
                                            <div className="flex size-12 shrink-0 items-center justify-center rounded-full bg-muted text-xs text-muted-foreground">?</div>
                                        )}
                                        <div className="min-w-0">
                                            <div className="truncate font-semibold">{channel.profile_name || channel.name}</div>
                                            {channel.profile_name && (
                                                <div className="truncate text-xs text-muted-foreground">({channel.name})</div>
                                            )}
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Nombre de instancia</Label>
                                        <code className="block rounded-md border bg-muted px-3 py-2 text-sm font-medium">
                                            {channel.instance_name}
                                        </code>
                                        <p className="text-xs text-muted-foreground">
                                            No se puede cambiar. Para reasignar, elimina este inbox y crea uno nuevo.
                                        </p>
                                    </div>

                                    {channel.owner_jid && (
                                        <div className="grid gap-2">
                                            <Label>Número conectado</Label>
                                            <code className="block rounded-md border bg-muted px-3 py-2 text-xs text-muted-foreground">
                                                {channel.owner_jid.replace('@s.whatsapp.net', '')}
                                            </code>
                                        </div>
                                    )}

                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={data.enabled}
                                            onChange={(e) => setData('enabled', e.target.checked)}
                                        />
                                        <span>Activar / desactivar canal</span>
                                    </label>
                                </CardContent>
                            </Card>

                            {liveWebhook && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center justify-between gap-2">
                                            <span>Webhook</span>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    liveWebhook.enabled
                                                        ? 'border-transparent bg-green-100 text-green-700'
                                                        : 'border-transparent bg-red-100 text-red-700'
                                                }
                                            >
                                                {liveWebhook.enabled ? <Check className="mr-1" /> : <X className="mr-1" />}
                                                {liveWebhook.enabled ? 'Habilitado' : 'Deshabilitado'}
                                            </Badge>
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        <div>
                                            <Label>URL</Label>
                                            <code className="mt-1 block rounded-md border bg-muted px-3 py-2 text-xs break-all">
                                                {liveWebhook.url}
                                            </code>
                                        </div>
                                        <div>
                                            <Label>Eventos</Label>
                                            {liveWebhook.events.length > 0 ? (
                                                <div className="mt-1 flex flex-wrap gap-1.5">
                                                    {liveWebhook.events.map((ev) => (
                                                        <code key={ev} className="rounded bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground ring-1 ring-border">
                                                            {ev}
                                                        </code>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="mt-1 text-xs text-muted-foreground">Esta instancia no tiene eventos configurados.</p>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </div>

                        {/* RIGHT: settings editables (3/5) */}
                        <div className="space-y-4 lg:col-span-3">
                            {liveSettings === null ? (
                                <Card>
                                    <CardContent className="flex items-start gap-2 py-4 text-sm text-muted-foreground">
                                        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-yellow-600" />
                                        <span>No se pudo leer la configuración de Evolution. Verifica que la API esté accesible y que la API key sea válida.</span>
                                    </CardContent>
                                </Card>
                            ) : (
                                <>
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Mensajes</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <label className="flex items-start gap-3 text-sm">
                                                <input
                                                    type="checkbox"
                                                    className="mt-0.5"
                                                    checked={data.groups_ignore}
                                                    onChange={(e) => setData('groups_ignore', e.target.checked)}
                                                />
                                                <div>
                                                    <div className="font-medium">Ignorar mensajes de grupos</div>
                                                    <div className="text-xs text-muted-foreground">
                                                        Evolution no enviará al webhook los mensajes provenientes de grupos de WhatsApp.
                                                    </div>
                                                </div>
                                            </label>
                                            {errors['groups_ignore'] && (
                                                <p className="text-sm text-destructive">{errors['groups_ignore']}</p>
                                            )}
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Llamadas</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <label className="flex items-start gap-3 text-sm">
                                                <input
                                                    type="checkbox"
                                                    className="mt-0.5"
                                                    checked={data.reject_call}
                                                    onChange={(e) => setData('reject_call', e.target.checked)}
                                                />
                                                <div>
                                                    <div className="font-medium">Rechazar llamadas</div>
                                                    <div className="text-xs text-muted-foreground">Rechaza automáticamente las llamadas entrantes.</div>
                                                </div>
                                            </label>
                                            <div className="grid gap-2">
                                                <Label htmlFor="msg_call">Mensaje al rechazar</Label>
                                                <input
                                                    id="msg_call"
                                                    type="text"
                                                    value={data.msg_call}
                                                    onChange={(e) => setData('msg_call', e.target.value)}
                                                    placeholder="Ej: No acepto llamadas por este medio"
                                                    className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                />
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Presencia y lectura</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            <label className="flex items-start gap-3 text-sm">
                                                <input
                                                    type="checkbox"
                                                    className="mt-0.5"
                                                    checked={data.always_online}
                                                    onChange={(e) => setData('always_online', e.target.checked)}
                                                />
                                                <div>
                                                    <div className="font-medium">Siempre en línea</div>
                                                    <div className="text-xs text-muted-foreground">Muestra que el bot está conectado permanentemente.</div>
                                                </div>
                                            </label>
                                            <label className="flex items-start gap-3 text-sm">
                                                <input
                                                    type="checkbox"
                                                    className="mt-0.5"
                                                    checked={data.read_messages}
                                                    onChange={(e) => setData('read_messages', e.target.checked)}
                                                />
                                                <div>
                                                    <div className="font-medium">Leer mensajes automáticamente</div>
                                                    <div className="text-xs text-muted-foreground">Marca los mensajes como leídos al recibirlos.</div>
                                                </div>
                                            </label>
                                            <label className="flex items-start gap-3 text-sm">
                                                <input
                                                    type="checkbox"
                                                    className="mt-0.5"
                                                    checked={data.read_status}
                                                    onChange={(e) => setData('read_status', e.target.checked)}
                                                />
                                                <div>
                                                    <div className="font-medium">Leer estados (stories)</div>
                                                    <div className="text-xs text-muted-foreground">Marca como vistas las stories publicadas.</div>
                                                </div>
                                            </label>
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Historial</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            <label className="flex items-start gap-3 text-sm">
                                                <input
                                                    type="checkbox"
                                                    className="mt-0.5"
                                                    checked={data.sync_full_history}
                                                    onChange={(e) => setData('sync_full_history', e.target.checked)}
                                                />
                                                <div>
                                                    <div className="font-medium">Sincronizar historial completo al conectar</div>
                                                    <div className="text-xs text-muted-foreground">Al reconectar la instancia, descarga todos los mensajes previos (puede tardar).</div>
                                                </div>
                                            </label>
                                        </CardContent>
                                    </Card>
                                </>
                            )}

                            <div className="flex items-center justify-end gap-3">
                                <Button type="button" variant="outline" onClick={() => router.visit('/admin/canales')}>
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={processing || liveSettings === null}>
                                    {processing ? <Loader2 className="mr-2 size-4 animate-spin" /> : <Save className="mr-2 size-4" />}
                                    {processing ? 'Guardando…' : 'Guardar'}
                                </Button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}

EvolutionEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Canales', href: '/admin/canales' },
        { title: 'Editar Evolution', href: '#' },
    ],
};
