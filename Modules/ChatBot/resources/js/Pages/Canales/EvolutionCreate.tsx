import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Check, Loader2, Save, Wifi, WifiOff, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { admin } from '@/routes';

type AvailableItem = {
    external_key: string;
    external_id: string | null;
    status: string;
    owner: string | null;
    profile_name: string | null;
    profile_picture_url: string | null;
    taken: boolean;
};

type Available = {
    configured: boolean;
    items: AvailableItem[];
    error?: string;
};

type WebhookInfo = {
    url: string;
    events: string[];
    enabled: boolean;
};

type Integration = {
    webhook: WebhookInfo;
} | null;

type InstanceDetail = AvailableItem | null;

type PageProps = {
    available: Available;
    selectedInstance: string | null;
    instance: InstanceDetail;
    integration: Integration;
};

export default function EvolutionCreate() {
    const { props } = usePage<PageProps>();

    const selectedInstance = props.selectedInstance ?? '';

    const { data, setData, post, processing, errors } = useForm({
        enabled: true,
        config: {
            instance_name: selectedInstance,
        },
    });

    function selectInstance(inst: AvailableItem): void {
        router.visit(`/admin/canales/evolution?instance_name=${encodeURIComponent(inst.external_key)}`);
    }

    function handleSubmit(e: React.FormEvent): void {
        e.preventDefault();
        post('/admin/canales/evolution');
    }

    return (
        <>
            <Head title="Nuevo inbox Evolution" />
            <div className="h-full min-h-0 space-y-4 overflow-y-auto p-4">
                <header className="flex items-center gap-3">
                    <Button type="button" variant="ghost" size="icon" onClick={() => router.visit('/admin/canales')}>
                        <ArrowLeft className="size-4" />
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Nuevo inbox Evolution</h1>
                        <p className="text-sm text-muted-foreground">
                            Elige una instancia de WhatsApp y guarda para crear el canal.
                        </p>
                    </div>
                </header>

                <div className="grid gap-4 lg:grid-cols-5">
                    {/* LEFT: list of available instances (2/5) */}
                    <div className="space-y-4 lg:col-span-2">
                        {!props.available.configured && (
                            <div className="rounded-md border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800">
                                <p className="font-medium">Evolution API no está configurada en .env</p>
                                <p className="mt-1 text-xs">
                                    Define <code className="rounded bg-yellow-100 px-1">EVOLUTION_DEFAULT_SERVER_URL</code> y{' '}
                                    <code className="rounded bg-yellow-100 px-1">EVOLUTION_DEFAULT_API_KEY</code> en .env
                                    para listar las instancias disponibles.
                                </p>
                            </div>
                        )}

                        {props.available.error && (
                            <div className="flex items-start gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                <span>{props.available.error}</span>
                            </div>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Instancias disponibles</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {props.available.configured && props.available.items.length === 0 && !props.available.error && (
                                    <p className="text-sm text-muted-foreground">No hay instancias. Crea una en Evolution.</p>
                                )}
                                {props.available.items.length > 0 && (
                                    <div className="max-h-[420px] space-y-2 overflow-y-auto pr-1">
                                        {props.available.items.map((inst) => (
                                            <button
                                                key={inst.external_key}
                                                type="button"
                                                disabled={inst.taken}
                                                onClick={() => selectInstance(inst)}
                                                className={`flex w-full items-center gap-3 rounded-md border px-3 py-2 text-left text-sm transition ${
                                                    inst.taken
                                                        ? 'cursor-not-allowed border-dashed border-muted-foreground/30 bg-muted/20 opacity-60'
                                                        : data.config.instance_name === inst.external_key
                                                            ? 'border-primary bg-primary/5'
                                                            : 'hover:bg-muted'
                                                }`}
                                            >
                                                {inst.profile_picture_url ? (
                                                    <img src={inst.profile_picture_url} alt="" className="size-10 shrink-0 rounded-full object-cover" />
                                                ) : (
                                                    <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-xs text-muted-foreground">?</div>
                                                )}
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate font-medium">{inst.profile_name ?? inst.external_key}</div>
                                                    {inst.owner && (
                                                        <div className="truncate text-xs text-muted-foreground">
                                                            {inst.owner.replace('@s.whatsapp.net', '')}
                                                        </div>
                                                    )}
                                                </div>
                                                {inst.taken ? (
                                                    <span className="shrink-0 text-xs text-muted-foreground">Ya vinculada</span>
                                                ) : inst.status === 'open' ? (
                                                    <Wifi className="size-4 shrink-0 text-green-600" />
                                                ) : (
                                                    <WifiOff className="size-4 shrink-0 text-muted-foreground" />
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* RIGHT: form (3/5) */}
                    <form onSubmit={handleSubmit} className="space-y-4 lg:col-span-3">
                        <Card>
                            <CardHeader>
                                <CardTitle>Opciones</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-2">
                                    <Label>Inbox</Label>
                                    {data.config.instance_name ? (
                                        <code className="block rounded-md border bg-muted px-3 py-2 text-sm font-medium">
                                            {data.config.instance_name}
                                        </code>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">Elige una instancia de la lista para crear el inbox.</p>
                                    )}
                                    {errors['config.instance_name'] && (
                                        <p className="text-sm text-destructive">{errors['config.instance_name']}</p>
                                    )}
                                </div>

                                {props.instance?.external_id && (
                                    <div className="grid gap-2">
                                        <Label>ID de instancia</Label>
                                        <code className="block rounded-md border bg-muted px-3 py-2 text-xs text-muted-foreground">
                                            {props.instance.external_id}
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

                        {/* Webhook integration — only shown if Evolution API returned a webhook for the selected instance. */}
                        {props.integration?.webhook && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center justify-between gap-2">
                                        <span>Webhook configurado en Evolution</span>
                                        <Badge
                                            variant="outline"
                                            className={
                                                props.integration.webhook.enabled
                                                    ? 'border-transparent bg-green-100 text-green-700'
                                                    : 'border-transparent bg-red-100 text-red-700'
                                            }
                                        >
                                            {props.integration.webhook.enabled ? <Check className="mr-1" /> : <X className="mr-1" />}
                                            {props.integration.webhook.enabled ? 'Habilitado' : 'Deshabilitado'}
                                        </Badge>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div>
                                        <Label>URL</Label>
                                        <code className="mt-1 block rounded-md border bg-muted px-3 py-2 text-xs">
                                            {props.integration.webhook.url}
                                        </code>
                                    </div>
                                    <div>
                                        <Label>Eventos</Label>
                                        {props.integration.webhook.events.length > 0 ? (
                                            <div className="mt-1 flex flex-wrap gap-1.5">
                                                {props.integration.webhook.events.map((ev) => (
                                                    <code key={ev} className="rounded bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground ring-1 ring-border">
                                                        {ev}
                                                    </code>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Esta instancia no tiene eventos configurados. Al guardar, el sistema
                                                registrará el evento{' '}
                                                <code className="rounded bg-muted px-1 py-0.5 text-[11px]">MESSAGES_UPSERT</code>.
                                            </p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {selectedInstance && !props.integration && !props.available.error && (
                            <Card>
                                <CardContent className="py-4 text-sm text-muted-foreground">
                                    Esta instancia aún no tiene un webhook configurado en Evolution. Al guardar, el sistema
                                    activará el webhook con la URL del inbox y el evento{' '}
                                    <code className="rounded bg-muted px-1 py-0.5 text-[11px]">MESSAGES_UPSERT</code>.
                                </CardContent>
                            </Card>
                        )}

                        <div className="flex items-center justify-end gap-3">
                            <Button type="button" variant="outline" onClick={() => router.visit('/admin/canales')}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={processing || !data.config.instance_name}>
                                {processing ? <Loader2 className="mr-2 size-4 animate-spin" /> : <Save className="mr-2 size-4" />}
                                {processing ? 'Guardando…' : 'Guardar'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

EvolutionCreate.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Canales', href: '/admin/canales' },
        { title: 'Nuevo Evolution', href: '/admin/canales/evolution' },
    ],
};
