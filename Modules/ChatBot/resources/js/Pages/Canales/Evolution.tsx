import { Head, useForm } from '@inertiajs/react';
import { Copy, Loader2, Save, Wifi, WifiOff, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { admin } from '@/routes';

type ChannelProps = {
    id: number;
    type: string;
    name: string;
    enabled: boolean;
    config: {
        server_url: string;
        api_key: string;
        instance_name: string;
    };
    settings: {
        display_name?: string;
        auto_reply?: string;
    };
};

type Stats = {
    connected: boolean;
    state?: string;
    instance?: string;
    qr_code?: string | null;
    error?: string;
};

type InstanceItem = {
    name: string;
    status: string;
    owner: string | null;
    profileName: string | null;
    profilePictureUrl: string | null;
};

type PageProps = {
    channel: ChannelProps;
    stats: Stats;
    webhookUrl: string;
};

function csrfToken(): string {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? '';
}

export default function EvolutionConfig({ channel, stats, webhookUrl }: PageProps) {
    const [instances, setInstances] = useState<InstanceItem[]>([]);
    const [loadingInstances, setLoadingInstances] = useState(false);
    const [instanceError, setInstanceError] = useState('');
    const [copied, setCopied] = useState(false);
    const [instancesFetched, setInstancesFetched] = useState(false);

    const { data, setData, patch, processing, errors } = useForm({
        enabled: channel.enabled,
        config: {
            server_url: channel.config.server_url,
            api_key: channel.config.api_key,
            instance_name: channel.config.instance_name,
        },
        settings: {
            display_name: channel.settings.display_name ?? '',
            auto_reply: channel.settings.auto_reply ?? '',
        },
    });

    function handleSubmit(e: React.FormEvent): void {
        e.preventDefault();
        patch(`/admin/canales/evolution/${channel.id}`, { preserveScroll: true });
    }

    async function fetchInstances(): Promise<void> {
        setLoadingInstances(true);
        setInstanceError('');

        try {
            const res = await fetch('/admin/canales/evolution/fetch-instances', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    server_url: data.config.server_url,
                    api_key: data.config.api_key,
                }),
            });

            const json = await res.json();

            if (!json.ok) {
                setInstanceError(json.error ?? 'Error al conectar con Evolution');
                return;
            }

            setInstances(json.instances ?? []);
        } catch (e) {
            setInstanceError('Error de red al conectar con Evolution');
        } finally {
            setLoadingInstances(false);
        }
    }

    // Auto-fetch instances on mount
    useEffect(() => {
        if (data.config.server_url && data.config.api_key && !instancesFetched) {
            setInstancesFetched(true);
            fetchInstances();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function selectInstance(name: string): void {
        setData('config', { ...data.config, instance_name: name });
    }

    function copyWebhook(): void {
        navigator.clipboard.writeText(webhookUrl).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }

    return (
        <>
            <Head title="WhatsApp (Evolution)" />
            <div className="space-y-6 p-4">
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="space-y-6">
                      

                            <Card>
                                <CardHeader>
                                    <CardTitle>Instancia</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={fetchInstances}
                                            disabled={loadingInstances || !data.config.server_url || !data.config.api_key}
                                        >
                                            {loadingInstances ? (
                                                <Loader2 className="mr-2 size-4 animate-spin" />
                                            ) : (
                                                <RefreshCw className="mr-2 size-4" />
                                            )}
                                            {loadingInstances ? 'Cargando...' : 'Listar instancias'}
                                        </Button>

                                        {instanceError && (
                                            <p className="mt-2 text-sm text-destructive">{instanceError}</p>
                                        )}
                                    </div>

                                    {instances.length > 0 && (
                                        <div className="grid gap-2">
                                            <Label>Selecciona una instancia</Label>
                                            <div className="grid gap-2 max-h-80 overflow-y-auto">
                                                {instances.map((inst) => (
                                                    <button
                                                        key={inst.name}
                                                        type="button"
                                                        onClick={() => selectInstance(inst.name)}
                                                        className={`flex items-center gap-3 rounded-md border px-3 py-2 text-left text-sm transition hover:bg-muted ${
                                                            data.config.instance_name === inst.name
                                                                ? 'border-primary bg-primary/5'
                                                                : ''
                                                        }`}
                                                    >
                                                        {inst.profilePictureUrl ? (
                                                            <img
                                                                src={inst.profilePictureUrl}
                                                                alt=""
                                                                className="size-10 shrink-0 rounded-full object-cover"
                                                            />
                                                        ) : (
                                                            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-xs text-muted-foreground">
                                                                ?
                                                            </div>
                                                        )}
                                                        <div className="min-w-0 flex-1">
                                                            <div className="font-medium">
                                                                {inst.profileName ?? inst.name}
                                                            </div>
                                                            {inst.owner && (
                                                                <div className="truncate text-xs text-muted-foreground">
                                                                    {inst.owner.replace('@s.whatsapp.net', '')}
                                                                </div>
                                                            )}
                                                        </div>
                                                        <span
                                                            className={`shrink-0 text-xs ${
                                                                inst.status === 'open'
                                                                    ? 'text-green-600'
                                                                    : 'text-muted-foreground'
                                                            }`}
                                                        >
                                                            {inst.status === 'open' ? 'Conectado' : 'Desconectado'}
                                                        </span>
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    <div className="grid gap-2">
                                        <Label htmlFor="instance_name">Nombre de instancia</Label>
                                        <Input
                                            id="instance_name"
                                            value={data.config.instance_name}
                                            onChange={(e) => setData('config', { ...data.config, instance_name: e.target.value })}
                                            placeholder="Selecciona o escribe el nombre"
                                            required
                                        />
                                        {errors['config.instance_name'] && (
                                            <p className="text-sm text-destructive">{errors['config.instance_name']}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Webhook URL</Label>
                                        <div className="flex items-center gap-2">
                                            <code className="flex-1 rounded-md border bg-muted px-3 py-2 text-sm">
                                                {webhookUrl}
                                            </code>
                                            <Button type="button" variant="outline" size="icon" onClick={copyWebhook}>
                                                {copied ? <span className="text-xs">OK</span> : <Copy className="size-4" />}
                                            </Button>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Configura esta URL como webhook en Evolution API para recibir mensajes entrantes.
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
                                            checked={data.enabled}
                                            onChange={(e) => setData('enabled', e.target.checked)}
                                        />
                                        <span>Canal habilitado</span>
                                    </label>

                                    <div className="grid gap-2">
                                        <Label htmlFor="display_name">Nombre mostrado</Label>
                                        <Input
                                            id="display_name"
                                            value={data.settings.display_name}
                                            onChange={(e) =>
                                                setData('settings', { ...data.settings, display_name: e.target.value })
                                            }
                                            placeholder="WhatsApp"
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="auto_reply">Respuesta automática</Label>
                                        <textarea
                                            id="auto_reply"
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            value={data.settings.auto_reply}
                                            onChange={(e) =>
                                                setData('settings', { ...data.settings, auto_reply: e.target.value })
                                            }
                                            placeholder="Mensaje automático al recibir un mensaje nuevo"
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
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

EvolutionConfig.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Canales', href: '/admin/canales' },
        { title: 'WhatsApp', href: '/admin/canales/evolution' },
    ],
};
