import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { admin } from '@/routes';
import scheduleRoutes, { store } from '@/routes/admin/schedule';

type Command = {
    name: string;
    description: string;
};

const FREQUENCIES = [
    { label: 'Cada minuto', expression: '* * * * *' },
    { label: 'Cada 5 minutos', expression: '*/5 * * * *' },
    { label: 'Cada 10 minutos', expression: '*/10 * * * *' },
    { label: 'Cada 30 minutos', expression: '*/30 * * * *' },
    { label: 'Cada hora', expression: '0 * * * *' },
    { label: 'Cada 6 horas', expression: '0 */6 * * *' },
    { label: 'Cada 12 horas', expression: '0 */12 * * *' },
    { label: 'Diario (medianoche)', expression: '0 0 * * *' },
    { label: 'Diario (mañana)', expression: '0 6 * * *' },
    { label: 'Semanal (lunes)', expression: '0 0 * * 1' },
    { label: 'Mensual (día 1)', expression: '0 0 1 * *' },
    { label: 'Personalizada', expression: '__custom__' },
];

export default function ScheduleCreate({ commands }: { commands: Command[] }) {
    const { data, setData, post, processing, errors } = useForm({
        command: '',
        description: '',
        expression: '* * * * *',
        timezone: '',
        parameters: {} as Record<string, string>,
        without_overlapping: false,
        on_one_server: false,
        run_in_maintenance: false,
        active: true,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(store.url());
    }

    return (
        <>
            <Head title="Nueva Tarea Programada" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild className="shrink-0">
                        <Link href={scheduleRoutes.index()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h2 className="text-xl font-semibold tracking-tight">Nueva Tarea Programada</h2>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-medium">Detalles de la Tarea</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="command">Comando</Label>
                                <select
                                    id="command"
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    value={data.command}
                                    onChange={(e) => setData('command', e.target.value)}
                                    required
                                >
                                    <option value="">Seleccionar comando...</option>
                                    {commands.map((cmd) => (
                                        <option key={cmd.name} value={cmd.name}>
                                            {cmd.name}{cmd.description ? ` — ${cmd.description}` : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.command && <p className="text-sm text-destructive">{errors.command}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Descripción</Label>
                                <Input
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Descripción opcional de la tarea"
                                />
                                {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="frequency">Frecuencia</Label>
                                <Select
                                    onValueChange={(value) => {
                                        if (value === '__custom__') {
                                            setData('expression', '');
                                        } else {
                                            setData('expression', value);
                                        }
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Seleccionar frecuencia..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {FREQUENCIES.map((freq) => (
                                            <SelectItem key={freq.expression} value={freq.expression}>
                                                {freq.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="expression">Expresión CRON</Label>
                                <Input
                                    id="expression"
                                    value={data.expression}
                                    onChange={(e) => setData('expression', e.target.value)}
                                    placeholder="* * * * *"
                                    className="font-mono"
                                />
                                {errors.expression && <p className="text-sm text-destructive">{errors.expression}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="timezone">Zona Horaria</Label>
                                <Input
                                    id="timezone"
                                    value={data.timezone}
                                    onChange={(e) => setData('timezone', e.target.value)}
                                    placeholder="UTC (vacío = default de la app)"
                                />
                                {errors.timezone && <p className="text-sm text-destructive">{errors.timezone}</p>}
                            </div>

                            <div className="flex flex-wrap items-center gap-6">
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={data.without_overlapping}
                                        onChange={(e) => setData('without_overlapping', e.target.checked)}
                                    />
                                    Sin superposición
                                </label>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={data.on_one_server}
                                        onChange={(e) => setData('on_one_server', e.target.checked)}
                                    />
                                    Un solo servidor
                                </label>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={data.run_in_maintenance}
                                        onChange={(e) => setData('run_in_maintenance', e.target.checked)}
                                    />
                                    En mantenimiento
                                </label>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={data.active}
                                        onChange={(e) => setData('active', e.target.checked)}
                                    />
                                    Activa
                                </label>
                            </div>

                            <div className="flex items-center gap-4 pt-2">
                                <Button disabled={processing}>
                                    {processing ? 'Creando...' : 'Crear Tarea'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={scheduleRoutes.index()}>Cancelar</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ScheduleCreate.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Tareas Programadas', href: scheduleRoutes.index() },
        { title: 'Nueva Tarea', href: scheduleRoutes.create() },
    ],
};