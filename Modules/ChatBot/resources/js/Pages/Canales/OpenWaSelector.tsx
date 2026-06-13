import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Loader2, Plus, RefreshCw, Wifi, WifiOff } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { admin } from '@/routes';

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

export default function OpenWaSelector() {
    const [data, setData] = useState<AvailableResponse | null>(null);
    const [loading, setLoading] = useState(false);
    const [creating, setCreating] = useState<string | null>(null);

    useEffect(() => {
        load();
    }, []);

    function load(): void {
        setLoading(true);
        fetch('/admin/canales/openwa/available', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then((r) => r.json())
            .then((d: AvailableResponse) => setData(d))
            .catch(() => setData({ configured: false, sessions: [], error: 'Error de red' }))
            .finally(() => setLoading(false));
    }

    function create(name: string): void {
        setCreating(name);
        router.post('/admin/canales/openwa', { session_name: name }, { preserveScroll: true });
    }

    function statusBadge(s: string): { text: string; cls: string } {
        if (s === 'CONNECTED') return { text: '✓ Conectado', cls: 'text-green-600' };
        if (s === 'SCAN_QR') return { text: '⏳ Esperando QR', cls: 'text-yellow-600' };
        if (s === 'CONNECTING') return { text: '⏳ Conectando', cls: 'text-yellow-600' };
        if (s === 'DISCONNECTED') return { text: '⨯ Desconectado', cls: 'text-red-600' };
        if (s === 'FAILED') return { text: '✗ Error', cls: 'text-red-600' };
        return { text: s, cls: 'text-muted-foreground' };
    }

    return (
        <>
            <Head title="Seleccionar sesión de OpenWA" />
            <div className="h-full min-h-0 space-y-4 overflow-y-auto p-4">
                <header className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Seleccionar sesión de OpenWA</h1>
                        <p className="text-sm text-muted-foreground">
                            Elige una sesión existente de WhatsApp en tu OpenWA.
                        </p>
                    </div>
                    <Button onClick={load} variant="outline" disabled={loading}>
                        <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                        Refrescar
                    </Button>
                </header>

                {!data?.configured && (
                    <div className="rounded-md border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800">
                        <p className="font-medium">OpenWA no está configurado en .env</p>
                        <p className="mt-1 text-xs">
                            Define <code className="rounded bg-yellow-100 px-1">OPENWA_BASE_URL</code> y{' '}
                            <code className="rounded bg-yellow-100 px-1">OPENWA_API_KEY</code> en .env.
                        </p>
                    </div>
                )}

                {data?.error && (
                    <div className="flex items-start gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        <AlertTriangle className="mt-0.5 h-4 w-4 flex-shrink-0" />
                        <span>{data.error}</span>
                    </div>
                )}

                {data?.configured && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Sesiones disponibles</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {data.sessions.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No hay sesiones en OpenWA. Crea una desde el dashboard de OpenWA o con{' '}
                                    <code className="rounded bg-muted px-1">php artisan openwa:setup-webhook</code>.
                                </p>
                            ) : (
                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    {data.sessions.map((s) => {
                                        const badge = statusBadge(s.status);
                                        const linked = s.already_linked;
                                        return (
                                            <div
                                                key={s.name}
                                                className={`flex flex-col gap-2 rounded-lg border-2 p-4 ${
                                                    linked
                                                        ? 'border-dashed border-muted-foreground/30 bg-muted/20 opacity-60'
                                                        : 'border-primary/30 bg-primary/5'
                                                }`}
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="min-w-0 flex-1">
                                                        <p className="truncate font-semibold">{s.name}</p>
                                                        {s.phone && (
                                                            <p className="truncate text-sm text-muted-foreground">+{s.phone}</p>
                                                        )}
                                                    </div>
                                                    {s.status === 'CONNECTED' ? (
                                                        <Wifi className="size-4 shrink-0 text-green-600" />
                                                    ) : (
                                                        <WifiOff className="size-4 shrink-0 text-muted-foreground" />
                                                    )}
                                                </div>
                                                <p className={`text-xs ${badge.cls}`}>{badge.text}</p>
                                                <div className="mt-auto">
                                                    {linked ? (
                                                        <p className="text-center text-xs text-muted-foreground">Ya vinculado</p>
                                                    ) : (
                                                        <Button
                                                            onClick={() => create(s.name)}
                                                            disabled={creating === s.name}
                                                            size="sm"
                                                            className="w-full"
                                                        >
                                                            {creating === s.name ? (
                                                                <Loader2 className="mr-1 h-3 w-3 animate-spin" />
                                                            ) : (
                                                                <Plus className="mr-1 h-3 w-3" />
                                                            )}
                                                            Crear inbox
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

OpenWaSelector.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Canales', href: '/admin/canales' },
        { title: 'Seleccionar sesión', href: '/admin/canales/openwa/seleccionar' },
    ],
};
