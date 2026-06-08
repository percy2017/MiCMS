import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, Play, Plus, RotateCcw, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { admin } from '@/routes';
import scheduleRoutes, { create, history, toggle, destroy, run } from '@/routes/admin/schedule';

type Task = {
    id: number;
    command: string;
    description: string | null;
    expression: string;
    active: boolean;
    last_run: string | null;
    last_exit_code: number | null;
    created_at: string;
};

export default function ScheduleIndex({ tasks }: { tasks: Task[] }) {
    function handleToggle(task: Task) {
        router.patch(toggle({ task: task.id }));
    }

    function handleRun(task: Task) {
        router.post(run({ task: task.id }));
    }

    function handleDelete(task: Task) {
        if (confirm(`¿Eliminar la tarea "${task.command}"?`)) {
            router.delete(destroy({ task: task.id }));
        }
    }

    const activeTasks = tasks.filter((t) => t.active);
    const inactiveTasks = tasks.filter((t) => !t.active);

    return (
        <>
            <Head title="Tareas Programadas" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold tracking-tight">Tareas Programadas</h2>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="mr-1 size-4" />
                            Nueva Tarea
                        </Link>
                    </Button>
                </div>

                {tasks.length === 0 && (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-2 py-12">
                            <CalendarClock className="size-12 text-muted-foreground/50" />
                            <p className="text-sm text-muted-foreground">No hay tareas programadas</p>
                            <Button asChild variant="outline" size="sm">
                                <Link href={create()}>Crear primera tarea</Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {activeTasks.length > 0 && (
                    <div className="space-y-3">
                        <h3 className="text-sm font-medium text-muted-foreground">Activas</h3>
                        {activeTasks.map((task) => (
                            <TaskRow key={task.id} task={task} onToggle={handleToggle} onRun={handleRun} onDelete={handleDelete} />
                        ))}
                    </div>
                )}

                {inactiveTasks.length > 0 && (
                    <div className="space-y-3">
                        <h3 className="text-sm font-medium text-muted-foreground">Inactivas</h3>
                        {inactiveTasks.map((task) => (
                            <TaskRow key={task.id} task={task} onToggle={handleToggle} onRun={handleRun} onDelete={handleDelete} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function TaskRow({ task, onToggle, onRun, onDelete }: { task: Task; onToggle: (t: Task) => void; onRun: (t: Task) => void; onDelete: (t: Task) => void }) {
    return (
        <Card className="transition-colors hover:border-sidebar-border">
            <CardHeader className="flex flex-row items-center justify-between py-4">
                <div className="flex items-center gap-3">
                    <CalendarClock className="size-5 text-muted-foreground" />
                    <div>
                        <CardTitle className="text-sm font-medium">
                            <Link href={history({ task: task.id })} className="hover:underline">
                                {task.description || task.command}
                            </Link>
                        </CardTitle>
                        <p className="mt-0.5 text-xs text-muted-foreground">{task.command}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Badge variant={task.active ? 'default' : 'secondary'}>
                        {task.active ? 'Activa' : 'Inactiva'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="flex items-center justify-between py-3 pt-0">
                <div className="flex items-center gap-4 text-xs text-muted-foreground">
                    <span className="font-mono">{task.expression}</span>
                    {task.last_run && (
                        <span>Última ejecución: {task.last_run}</span>
                    )}
                    {task.last_exit_code !== null && (
                        <Badge variant={task.last_exit_code === 0 ? 'secondary' : 'destructive'} className="text-[10px]">
                            código {task.last_exit_code}
                        </Badge>
                    )}
                </div>
                <div className="flex items-center gap-1">
                    <Button variant="ghost" size="icon" className="size-8" onClick={() => onRun(task)} title="Ejecutar ahora">
                        <Play className="size-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon" className="size-8" onClick={() => onToggle(task)} title={task.active ? 'Desactivar' : 'Activar'}>
                        <RotateCcw className="size-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon" className="size-8 text-destructive" onClick={() => onDelete(task)} title="Eliminar">
                        <Trash2 className="size-3.5" />
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

ScheduleIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Tareas Programadas', href: scheduleRoutes.index() },
    ],
};