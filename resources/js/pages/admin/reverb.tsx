import { Head, router, usePage } from '@inertiajs/react';
import { Activity, Radio, Wifi, BarChart3, Plug, X, LogIn, LogOut, MessageSquare, RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { admin } from '@/routes';
import reverbRoutes from '@/routes/admin/reverb';
import { reverb as reverbRoute } from '@/routes/admin';

interface ReverbStats {
    connections: number;
    peak_connections: number;
    messages: number;
    active_channels: number;
    events: EventEntry[];
    updated_at: string;
}

interface EventEntry {
    type: string;
    channel: string;
    details: string;
    timestamp: string;
    time: string;
}

const EVENT_ICONS: Record<string, typeof Plug> = {
    connection_established: LogIn,
    connection_closed: LogOut,
    channel_created: Radio,
    channel_removed: X,
    message_handled: MessageSquare,
};

const EVENT_COLORS: Record<string, string> = {
    connection_established: 'text-emerald-500',
    connection_closed: 'text-red-500',
    channel_created: 'text-blue-500',
    channel_removed: 'text-orange-500',
    message_handled: 'text-violet-500',
};

const EVENT_LABELS: Record<string, string> = {
    connection_established: 'Connection Established',
    connection_closed: 'Connection Closed',
    channel_created: 'Channel Created',
    channel_removed: 'Channel Removed',
    message_handled: 'Message Handled',
};

export default function ReverbMonitor() {
    const { props } = usePage<{ stats: ReverbStats }>();
    const [stats, setStats] = useState<ReverbStats>(props.stats);
    const feedRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const interval = setInterval(async () => {
            try {
                const res = await fetch(reverbRoutes.stats.url());
                if (res.ok) {
                    const data: ReverbStats = await res.json();
                    setStats(data);
                }
            } catch {
                //
            }
        }, 2000);

        return () => clearInterval(interval);
    }, []);

    useEffect(() => {
        if (feedRef.current) {
            feedRef.current.scrollTop = feedRef.current.scrollHeight;
        }
    }, [stats.events.length]);

    const messagesPerSecond = stats.messages > 0
        ? (stats.messages / Math.max((Date.now() - new Date(stats.updated_at).getTime()) / 1000, 1)).toFixed(1)
        : '0';

    return (
        <>
            <Head title="Socket Monitor" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-hidden rounded-xl p-4">

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4 shrink-0">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Conexiones Activas</CardTitle>
                            <Wifi className="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold tabular-nums">{stats.connections}</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Pico máximo: {stats.peak_connections}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Mensajes Totales</CardTitle>
                            <Activity className="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold tabular-nums">{stats.messages.toLocaleString()}</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                ~{messagesPerSecond} msg/s
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Canales Activos</CardTitle>
                            <Radio className="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold tabular-nums">{stats.active_channels}</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                suscripciones activas
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Throughput</CardTitle>
                            <BarChart3 className="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold tabular-nums">{messagesPerSecond}</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                mensajes por segundo
                            </p>
                        </CardContent>
                    </Card>
                </div>

               <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            if (confirm('¿Limpiar cache y resetear contadores?')) {
                                router.post(reverbRoutes.reset());
                                setStats({
                                    connections: 0,
                                    peak_connections: 0,
                                    messages: 0,
                                    active_channels: 0,
                                    events: [],
                                    updated_at: new Date().toISOString(),
                                });
                            }
                        }}
                        className="gap-1.5"
                    >
                        <RotateCcw className="size-3.5" />
                        Limpiar Cache
                    </Button>
                    <Badge variant="outline" className="gap-1.5 text-xs">
                        <span className="relative flex size-2">
                            <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                            <span className="relative inline-flex size-2 rounded-full bg-emerald-500" />
                        </span>
                        Live
                    </Badge>
                </div>

                <Card className="flex min-h-0 flex-1 flex-col">
                    <CardHeader className="shrink-0">
                        <CardTitle className="text-sm font-medium">
                            Eventos en Vivo
                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                                ({stats.events.length} eventos)
                            </span>
                        </CardTitle>
                    </CardHeader>
              
                    <CardContent className="min-h-0 flex-1 p-0">
                        <div
                            ref={feedRef}
                            className="h-full overflow-y-auto px-6 pb-4"
                        >
                            {stats.events.length === 0 && (
                                <div className="flex items-center justify-center py-12 text-sm text-muted-foreground">
                                    Esperando eventos...
                                </div>
                            )}
                            <div className="space-y-0.5">
                                {stats.events.toReversed().map((entry, i) => {
                                    const Icon = EVENT_ICONS[entry.type] ?? Plug;
                                    const color = EVENT_COLORS[entry.type] ?? 'text-muted-foreground';
                                    const label = EVENT_LABELS[entry.type] ?? entry.type;

                                    return (
                                        <div
                                            key={`${entry.timestamp}-${i}`}
                                            className="flex items-center gap-3 rounded px-2 py-1.5 text-xs transition-colors hover:bg-muted/50"
                                        >
                                            <Icon className={`size-3.5 shrink-0 ${color}`} />
                                            <span className="font-mono text-muted-foreground tabular-nums">{entry.time}</span>
                                            <span className={`font-medium ${color}`}>{label}</span>
                                            {entry.channel && (
                                                <code className="rounded bg-muted px-1 py-0.5 text-[10px] text-muted-foreground">
                                                    {entry.channel}
                                                </code>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="shrink-0 text-right text-xs text-muted-foreground">
                    Última actualización: {new Date(stats.updated_at).toLocaleTimeString()}
                </div>
            </div>
        </>
    );
}

ReverbMonitor.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Socket Monitor', href: reverbRoute() },
    ],
};