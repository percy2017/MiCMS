import { Head, Link, useForm } from '@inertiajs/react';
import { Save, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { admin } from '@/routes';

type Widget = {
    id: number;
    enabled: boolean;
    title: string;
    subtitle: string | null;
    greeting: string | null;
    position: 'left' | 'right';
    avatar_media_id: number | null;
    avatar_url: string | null;
    require_auth: boolean;
    show_typing: boolean;
    offline_message: string | null;
};

type PageProps = { widget: Widget };

export default function ChatBotWidgetConfig({ widget }: PageProps) {
    const { data, setData, patch, processing, errors } = useForm({
        enabled: widget.enabled,
        title: widget.title,
        subtitle: widget.subtitle ?? '',
        greeting: widget.greeting ?? '',
        position: widget.position,
        avatar_media_id: widget.avatar_media_id,
        require_auth: widget.require_auth,
        show_typing: widget.show_typing,
        offline_message: widget.offline_message ?? '',
    });

    function handleSubmit(e: React.FormEvent): void {
        e.preventDefault();
        patch('/admin/canales/web-widget', { preserveScroll: true });
    }

    return (
        <>
            <Head title="Config del Chat Widget" />
            <div className="space-y-6 p-4">
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>General</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={data.enabled}
                                            onChange={(e) => setData('enabled', e.target.checked)}
                                        />
                                        <span>Widget habilitado</span>
                                    </label>
                                    {errors.enabled && <p className="text-sm text-destructive">{errors.enabled}</p>}

                                    <div className="grid gap-2">
                                        <Label htmlFor="title">Título</Label>
                                        <Input
                                            id="title"
                                            value={data.title}
                                            onChange={(e) => setData('title', e.target.value)}
                                            required
                                        />
                                        {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="subtitle">Subtítulo</Label>
                                        <Input
                                            id="subtitle"
                                            value={data.subtitle}
                                            onChange={(e) => setData('subtitle', e.target.value)}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="greeting">Saludo inicial</Label>
                                        <textarea
                                            id="greeting"
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            value={data.greeting}
                                            onChange={(e) => setData('greeting', e.target.value)}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Posición</Label>
                                        <div className="flex gap-4 text-sm">
                                            <label className="flex items-center gap-2">
                                                <input
                                                    type="radio"
                                                    checked={data.position === 'left'}
                                                    onChange={() => setData('position', 'left')}
                                                />
                                                Inferior izquierda
                                            </label>
                                            <label className="flex items-center gap-2">
                                                <input
                                                    type="radio"
                                                    checked={data.position === 'right'}
                                                    onChange={() => setData('position', 'right')}
                                                />
                                                Inferior derecha
                                            </label>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Apariencia</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label>Avatar</Label>
                                        {widget.avatar_url ? (
                                            <div className="flex items-center gap-3">
                                                <img
                                                    src={widget.avatar_url}
                                                    alt=""
                                                    className="size-12 rounded-full border object-cover"
                                                />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => setData('avatar_media_id', null)}
                                                >
                                                    Quitar
                                                </Button>
                                            </div>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                Para seleccionar un avatar, primero súbelo en{' '}
                                                <Link href="/admin/media" className="underline">
                                                    /admin/media
                                                </Link>{' '}
                                                y pega aquí su ID.
                                            </p>
                                        )}
                                        <Input
                                            type="number"
                                            placeholder="ID del media (opcional)"
                                            value={data.avatar_media_id ?? ''}
                                            onChange={(e) =>
                                                setData('avatar_media_id', e.target.value ? Number(e.target.value) : null)
                                            }
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Comportamiento</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={data.require_auth}
                                            onChange={(e) => setData('require_auth', e.target.checked)}
                                        />
                                        <span>Requerir inicio de sesión/registro para chatear</span>
                                    </label>
                                    {errors.require_auth && <p className="text-sm text-destructive">{errors.require_auth}</p>}

                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={data.show_typing}
                                            onChange={(e) => setData('show_typing', e.target.checked)}
                                        />
                                        <span>Mostrar indicador de "escribiendo..."</span>
                                    </label>

                                    <div className="grid gap-2">
                                        <Label htmlFor="offline_message">Mensaje fuera de horario</Label>
                                        <textarea
                                            id="offline_message"
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            value={data.offline_message}
                                            onChange={(e) => setData('offline_message', e.target.value)}
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

ChatBotWidgetConfig.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Canales', href: '/admin/canales' },
        { title: 'Widget Web', href: '/admin/canales/web-widget' },
    ],
};
