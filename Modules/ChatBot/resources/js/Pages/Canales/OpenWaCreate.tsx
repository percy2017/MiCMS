import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Loader2, RefreshCw, Save, Wifi, WifiOff } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { admin } from '@/routes';

type AvailableItem = {
    external_key: string;
    external_id: string | null;
    status: string;
    phone: string | null;
    push_name: string | null;
    taken: boolean;
};

type Available = {
    configured: boolean;
    items: AvailableItem[];
    error?: string;
};

type PageProps = {
    available: Available;
};

export default function OpenWaCreate() {
    const { props } = usePage<PageProps>();
    const [available, setAvailable] = useState<Available>(props.available ?? { configured: false, items: [] });
    const [loading, setLoading] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        enabled: true,
        config: {
            session_name: '',
        },
        settings: {
            display_name: '',
            auto_reply: '',
        },
    });

    async function refresh(): Promise<void> {
        setLoading(true);
        try {
            const res = await fetch('/admin/canales/openwa/available', {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data: Available = await res.json();
            setAvailable(data);
        } catch {
            setAvailable({ configured: false, items: [], error: 'Error de red' });
        } finally {
            setLoading(false);
        }
    }

    function selectSession(s: AvailableItem): void {
        setData('config', { ...data.config, session_name: s.external_key });
        setData('settings', { ...data.settings, display_name: s.push_name ?? s.external_key });
    }

    function handleSubmit(e: React.FormEvent): void {
        e.preventDefault();
        post('/admin/canales/openwa');
    }

    return (
        <>
            <Head title="Nuevo inbox OpenWA" />
            <div className="h-full min-h-0 space-y-4 overflow-y-auto p-4">
                <header className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button type="button" variant="ghost" size="icon" onClick={() => router.visit('/admin/canales')}>
                            <ArrowLeft className="size-4" />
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">Nuevo inbox OpenWA</h1>
                            <p className="text-sm text-muted-foreground">
                                Elige una sesión de WhatsApp y guarda para crear el canal.
                            </p>
                        </div>
                    </div>
                    <Button type="button" variant="outline" onClick={refresh} disabled={loading}>
                        <RefreshCw className={`mr-2 size-4 ${loading ? 'animate-spin' : ''}`} />
                        Refrescar
                    </Button>
                </header>

                <form onSubmit={handleSubmit} className="grid gap-4 lg:grid-cols-2">
                    {/* LEFT: list of available sessions */}
                    <div className="space-y-4">
                        {!available.configured && (
                            <div className="rounded-md border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800">
                                <p className="font-medium">OpenWA no está configurado en .env</p>
                                <p className="mt-1 text-xs">
                                    Define <code className="rounded bg-yellow-100 px-1">OPENWA_BASE_URL</code> y{' '}
                                    <code className="rounded bg-yellow-100 px-1">OPENWA_API_KEY</code> en .env
                                    para listar las sesiones disponibles. Aun así puedes escribir el nombre manualmente abajo.
                                </p>
                            </div>
                        )}

                        {available.error && (
                            <div className="flex items-start gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                <span>{available.error}</span>
                            </div>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Sesiones disponibles</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {available.configured && available.items.length === 0 && !loading && !available.error && (
                                    <p className="text-sm text-muted-foreground">No hay sesiones. Crea una en OpenWA o escribe el nombre abajo.</p>
                                )}
                                {available.items.length > 0 && (
                                    <div className="max-h-96 space-y-2 overflow-y-auto pr-1">
                                        {available.items.map((s) => (
                                            <button
                                                key={s.external_key}
                                                type="button"
                                                disabled={s.taken}
                                                onClick={() => selectSession(s)}
                                                className={`flex w-full items-center gap-3 rounded-md border px-3 py-2 text-left text-sm transition ${
                                                    s.taken
                                                        ? 'cursor-not-allowed border-dashed border-muted-foreground/30 bg-muted/20 opacity-60'
                                                        : data.config.session_name === s.external_key
                                                            ? 'border-primary bg-primary/5'
                                                            : 'hover:bg-muted'
                                                }`}
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate font-medium">{s.external_key}</div>
                                                    {s.phone && <div className="truncate text-xs text-muted-foreground">+{s.phone}</div>}
                                                </div>
                                                {s.taken ? (
                                                    <span className="shrink-0 text-xs text-muted-foreground">Ya vinculada</span>
                                                ) : s.status === 'CONNECTED' ? (
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

                    {/* RIGHT: minimal form (only session_name + options) */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Opciones</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="session_name">Nombre de sesión</Label>
                                    <Input
                                        id="session_name"
                                        value={data.config.session_name}
                                        onChange={(e) => setData('config', { ...data.config, session_name: e.target.value })}
                                        placeholder="Elige arriba o escribe"
                                        required
                                    />
                                    {errors['config.session_name'] && (
                                        <p className="text-sm text-destructive">{errors['config.session_name']}</p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="display_name">Nombre a mostrar</Label>
                                    <Input
                                        id="display_name"
                                        value={data.settings.display_name}
                                        onChange={(e) => setData('settings', { ...data.settings, display_name: e.target.value })}
                                        placeholder="WhatsApp"
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="auto_reply">Respuestas automáticas</Label>
                                    <textarea
                                        id="auto_reply"
                                        className="flex min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        value={data.settings.auto_reply}
                                        onChange={(e) => setData('settings', { ...data.settings, auto_reply: e.target.value })}
                                        placeholder="Mensaje automático al recibir un mensaje nuevo"
                                    />
                                </div>

                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={data.enabled}
                                        onChange={(e) => setData('enabled', e.target.checked)}
                                    />
                                    <span>Activar inbox inmediatamente</span>
                                </label>
                            </CardContent>
                        </Card>

                        <div className="flex items-center justify-end gap-3">
                            <Button type="button" variant="outline" onClick={() => router.visit('/admin/canales')}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? <Loader2 className="mr-2 size-4 animate-spin" /> : <Save className="mr-2 size-4" />}
                                {processing ? 'Guardando…' : 'Guardar'}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}

OpenWaCreate.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Canales', href: '/admin/canales' },
        { title: 'Nuevo OpenWA', href: '/admin/canales/openwa' },
    ],
};
