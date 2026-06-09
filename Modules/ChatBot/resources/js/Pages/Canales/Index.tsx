import { Head, Link, router } from '@inertiajs/react';
import { Globe, MessageCircle, ArrowRight, Plus } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { admin } from '@/routes';

type ChannelItem = {
    id: number;
    type: string;
    name: string;
    enabled: boolean;
    sort: number;
    url: string;
};

type PageProps = { channels: ChannelItem[] };

const CHANNEL_TYPES: { type: string; icon: typeof Globe; color: string; name: string; description: string }[] = [
    {
        type: 'web_widget',
        icon: Globe,
        color: '#2563eb',
        name: 'Widget Web',
        description: 'Chat widget integrado en el sitio web',
    },
    {
        type: 'evolution',
        icon: MessageCircle,
        color: '#25D366',
        name: 'WhatsApp',
        description: 'WhatsApp mediante Evolution API',
    },
];

const CHANNEL_META = Object.fromEntries(CHANNEL_TYPES.map((c) => [c.type, c]));

export default function CanalesIndex({ channels }: PageProps) {
    function createChannel(type: string): void {
        const url = type === 'web_widget'
            ? '/admin/canales/web-widget'
            : `/admin/canales/${type}`;
        router.post(url);
    }

    return (
        <>
            <Head title="Canales" />
            <div className="space-y-6 p-4">

                <div>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {CHANNEL_TYPES.map((a) => {
                            const Icon = a.icon;

                            return (
                                <button
                                    key={a.type}
                                    type="button"
                                    onClick={() => createChannel(a.type)}
                                    className="group block w-full text-left"
                                >
                                    <Card className="border-dashed transition hover:shadow-md">
                                        <CardHeader>
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-3">
                                                    <div
                                                        className="flex size-10 items-center justify-center rounded-lg"
                                                        style={{ backgroundColor: `${a.color}15`, color: a.color }}
                                                    >
                                                        <Icon className="size-5" />
                                                    </div>
                                                    <div>
                                                        <CardTitle className="text-base">{a.name}</CardTitle>
                                                        <p className="text-xs text-muted-foreground">{a.description}</p>
                                                    </div>
                                                </div>
                                                <Plus className="size-4 text-muted-foreground opacity-0 transition group-hover:opacity-100" />
                                            </div>
                                        </CardHeader>
                                        <CardContent>
                                            <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                                <Plus className="size-3" />
                                                Crear
                                            </span>
                                        </CardContent>
                                    </Card>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {channels.length > 0 && (
                    <div>
                        <h2 className="mb-3 text-lg font-medium">Canales existentes</h2>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {channels.map((c) => {
                                const meta = CHANNEL_META[c.type];
                                const Icon = meta?.icon ?? Globe;

                                return (
                                    <Link key={c.id} href={c.url} className="group block">
                                        <Card className="transition hover:shadow-md">
                                            <CardHeader>
                                                <div className="flex items-center justify-between">
                                                    <div className="flex items-center gap-3">
                                                        <div
                                                            className="flex size-10 items-center justify-center rounded-lg"
                                                            style={{ backgroundColor: `${meta?.color ?? '#666'}15`, color: meta?.color ?? '#666' }}
                                                        >
                                                            <Icon className="size-5" />
                                                        </div>
                                                        <div>
                                                            <CardTitle className="text-base">{c.name}</CardTitle>
                                                            <p className="text-xs text-muted-foreground">
                                                                {meta?.description ?? c.type}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <ArrowRight className="size-4 text-muted-foreground opacity-0 transition group-hover:opacity-100" />
                                                </div>
                                            </CardHeader>
                                            <CardContent>
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                        c.enabled
                                                            ? 'bg-green-100 text-green-700'
                                                            : 'bg-muted text-muted-foreground'
                                                    }`}
                                                >
                                                    {c.enabled ? 'Activo' : 'Inactivo'}
                                                </span>
                                            </CardContent>
                                        </Card>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

CanalesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Canales', href: '/admin/canales' },
    ],
};
