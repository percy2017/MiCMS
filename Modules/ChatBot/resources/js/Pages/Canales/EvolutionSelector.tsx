import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Loader2, Plus, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { admin } from '@/routes';

type InstanceItem = {
    name: string;
    instance_id: string | null;
    status: string;
    owner: string | null;
    profileName: string | null;
    profilePictureUrl: string | null;
};

type FetchResponse = {
    ok: boolean;
    instances?: InstanceItem[];
    error?: string;
};

export default function EvolutionSelector() {
    const [instances, setInstances] = useState<InstanceItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [creating, setCreating] = useState<string | null>(null);

    function csrfToken(): string {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    }

    async function fetchInstances(): Promise<void> {
        setLoading(true);
        setError('');
        try {
            const res = await fetch('/admin/canales/evolution/fetch-instances', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ exclude: 0 }),
            });
            const json: FetchResponse = await res.json();
            if (!json.ok) {
                setError(json.error ?? 'Error al conectar con Evolution');
                return;
            }
            setInstances(json.instances ?? []);
        } catch {
            setError('Error de red al conectar con Evolution');
        } finally {
            setLoading(false);
        }
    }

    function createChannel(name: string): void {
        setCreating(name);
        router.post('/admin/canales/evolution/select-store', { instance_name: name }, {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Seleccionar instancia de Evolution" />
            <div className="h-full min-h-0 space-y-4 overflow-y-auto p-4">
                <header className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Seleccionar instancia de Evolution</h1>
                        <p className="text-sm text-muted-foreground">
                            Elige una instancia existente de WhatsApp en tu Evolution API.
                        </p>
                    </div>
                    <Button onClick={fetchInstances} variant="outline" disabled={loading}>
                        <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                        Listar instancias
                    </Button>
                </header>

                {error && (
                    <div className="flex items-start gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        <AlertTriangle className="mt-0.5 h-4 w-4 flex-shrink-0" />
                        <span>{error}</span>
                    </div>
                )}

                {instances.length === 0 && !loading && !error && (
                    <p className="text-sm text-muted-foreground">
                        Haz click en "Listar instancias" para cargar las disponibles en Evolution.
                    </p>
                )}

                {instances.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Instancias disponibles</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {instances.map((inst) => (
                                    <div
                                        key={inst.name}
                                        className="flex flex-col gap-2 rounded-lg border-2 border-primary/30 bg-primary/5 p-4"
                                    >
                                        <div className="flex items-start gap-3">
                                            {inst.profilePictureUrl ? (
                                                <img
                                                    src={inst.profilePictureUrl}
                                                    alt=""
                                                    className="size-10 shrink-0 rounded-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-xs text-muted-foreground">?</div>
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate font-semibold">{inst.profileName ?? inst.name}</p>
                                                {inst.owner && (
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {inst.owner.replace('@s.whatsapp.net', '')}
                                                    </p>
                                                )}
                                                <p className={`mt-1 text-xs ${inst.status === 'open' ? 'text-green-600' : 'text-muted-foreground'}`}>
                                                    {inst.status === 'open' ? '✓ Conectado' : '⨯ Desconectado'}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            onClick={() => createChannel(inst.name)}
                                            disabled={creating === inst.name}
                                            size="sm"
                                            className="mt-auto"
                                        >
                                            {creating === inst.name ? (
                                                <Loader2 className="mr-1 h-3 w-3 animate-spin" />
                                            ) : (
                                                <Plus className="mr-1 h-3 w-3" />
                                            )}
                                            Crear inbox
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

EvolutionSelector.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Canales', href: '/admin/canales' },
        { title: 'Seleccionar instancia', href: '/admin/canales/evolution/seleccionar' },
    ],
};
