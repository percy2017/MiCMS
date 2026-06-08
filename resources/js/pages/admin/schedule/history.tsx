import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { admin } from '@/routes';
import scheduleRoutes from '@/routes/admin/schedule';

type LogEntry = {
    id: number;
    started_at: string;
    finished_at: string | null;
    exit_code: number | null;
    output: string | null;
    duration_ms: number | null;
};

export default function ScheduleHistory({ task, logs }: { task: { id: number; command: string; description: string | null }; logs: { data: LogEntry[] } }) {
    return (
        <>
            <Head title={`Historial - ${task.description || task.command}`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild className="shrink-0">
                        <Link href={scheduleRoutes.index()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h2 className="text-xl font-semibold tracking-tight">{task.description || task.command}</h2>
                        <p className="text-sm text-muted-foreground">{task.command}</p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-medium">Historial de Ejecuciones</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {logs.data.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No hay ejecuciones registradas todavía.
                            </p>
                        ) : (
                            <div className="space-y-2">
                                {logs.data.map((log) => (
                                    <div key={log.id} className="rounded-lg border p-4 text-sm transition-colors hover:border-sidebar-border">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <span className="text-muted-foreground">
                                                    {new Date(log.started_at).toLocaleString()}
                                                </span>
                                                <Badge variant={log.exit_code === 0 ? 'secondary' : 'destructive'} className="text-[10px]">
                                                    código {log.exit_code ?? 'N/A'}
                                                </Badge>
                                                {log.duration_ms !== null && (
                                                    <span className="text-muted-foreground">
                                                        {log.duration_ms < 1000
                                                            ? `${log.duration_ms}ms`
                                                            : `${(log.duration_ms / 1000).toFixed(2)}s`}
                                                    </span>
                                                )}
                                            </div>
                                            {log.exit_code !== 0 && log.exit_code !== null && (
                                                <Badge variant="destructive" className="text-[10px]">Falló</Badge>
                                            )}
                                        </div>
                                        {log.output && log.exit_code !== 0 && (
                                            <pre className="mt-2 max-h-32 overflow-auto rounded bg-muted p-2 text-xs text-muted-foreground">
                                                {log.output}
                                            </pre>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ScheduleHistory.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Tareas Programadas', href: scheduleRoutes.index() },
        { title: 'Historial', href: scheduleRoutes.create() },
    ],
};