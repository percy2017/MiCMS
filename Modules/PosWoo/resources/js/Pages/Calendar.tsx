import { Head } from '@inertiajs/react';
import { ExternalLink, Loader2, MessageCircle, Phone, CalendarDays, DollarSign, User, Tag, ShoppingCart, Clock } from 'lucide-react';
import { useEffect, useState } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import multiMonthPlugin from '@fullcalendar/multimonth';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { admin } from '@/routes';
import { openPosWooChat } from '@/lib/pos-woo-chat';

type EventData = {
    title: string;
    start: string;
    allDay: boolean;
    extendedProps: Record<string, unknown>;
};

function openChat(convId: number | null, phone: string | null, name: string): Promise<void> {
    return openPosWooChat(convId, phone, name);
}

function InfoRow({ icon: Icon, label, value }: { icon: React.ComponentType<{ className?: string }>; label: string; value: string }) {
    return (
        <div className="flex items-center gap-3 rounded-lg bg-muted/40 px-3 py-2.5">
            <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                <Icon className="size-4" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
                <p className="truncate text-sm font-medium">{value}</p>
            </div>
        </div>
    );
}

export default function Calendar() {
    const [events, setEvents] = useState<EventData[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [selected, setSelected] = useState<EventData | null>(null);

    useEffect(() => {
        fetch('/admin/pos-woo/subscriptions')
            .then((r) => r.json())
            .then((data) => {
                if (data.error) {
                    setError(data.error);
                } else {
                    setEvents(data.events ?? []);
                }
            })
            .catch(() => setError('Error al cargar suscripciones'))
            .finally(() => setLoading(false));
    }, []);

    const ep = selected?.extendedProps ?? {};
    const avatarUrl = String(ep.user_avatar_url ?? '');
    const userName = String(ep.user_name || ep.contact_name || ep.customer_name || '');
    const orderId = ep.order_id ? String(ep.order_id) : null;
    const chatId = ep.chat_conversation_id ? Number(ep.chat_conversation_id) : null;
    const contactPhone = String(ep.contact_phone || ep.customer_phone || '');

    return (
        <>
            <Head title="Calendario de Suscripciones" />

            <div className="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-hidden p-4">
                <div className="flex shrink-0 items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Calendario de Suscripciones
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {loading ? 'Cargando...' : `${events.length} suscripción${events.length !== 1 ? 'es' : ''}`}
                        </p>
                    </div>
                </div>

                {error && (
                    <div className="shrink-0 rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                        {error}
                    </div>
                )}

                {loading ? (
                    <div className="flex flex-1 items-center justify-center">
                        <Loader2 className="size-6 animate-spin text-muted-foreground" />
                    </div>
                ) : (
                    <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border bg-card">
                        <div className="min-h-0 flex-1 overflow-auto">
                            <FullCalendar
                                plugins={[dayGridPlugin, multiMonthPlugin]}
                                initialView="dayGridMonth"
                                locale="es"
                                events={events}
                                eventClick={(info) => {
                                    info.jsEvent.preventDefault();
                                    setSelected({
                                        title: info.event.title,
                                        start: info.event.startStr,
                                        allDay: true,
                                        extendedProps: { ...info.event.extendedProps },
                                    });
                                }}
                                height="auto"
                                headerToolbar={{
                                    left: 'prev,next today',
                                    center: 'title',
                                    right: 'multiMonthYear,dayGridMonth',
                                }}
                                buttonText={{
                                    today: 'Hoy',
                                    month: 'Mes',
                                    year: 'Año',
                                }}
                                noEventsText="No hay suscripciones con vencimiento en este período"
                                eventTimeFormat={false}
                                multiMonthTitleFormat={{ year: 'numeric', month: 'long' }}
                                multiMonthMaxColumns={2}
                            />
                        </div>
                    </div>
                )}
            </div>

            <Dialog open={selected !== null} onOpenChange={(open) => { if (!open) setSelected(null); }}>
                <DialogContent className="max-w-lg p-0 gap-0 overflow-hidden">
                    <div className="bg-gradient-to-r from-primary/10 via-primary/5 to-transparent px-6 pb-4 pt-6">
                        <div className="flex items-start gap-4">
                            {avatarUrl ? (
                                <img
                                    src={avatarUrl}
                                    alt={userName}
                                    className="size-16 shrink-0 rounded-full border-2 border-border object-cover shadow-sm"
                                />
                            ) : (
                                <div className="flex size-16 shrink-0 items-center justify-center rounded-full border-2 border-border bg-muted text-xl font-bold text-muted-foreground shadow-sm">
                                    {userName.charAt(0)?.toUpperCase() ?? '?'}
                                </div>
                            )}
                            <div className="min-w-0 flex-1 pt-1">
                                <DialogTitle className="truncate text-lg">{String(ep.subscription_title || 'Suscripción')}</DialogTitle>
                                <p className="truncate text-sm font-medium text-foreground">{userName}</p>
                                {contactPhone && (
                                    <p className="truncate text-xs text-muted-foreground">{contactPhone}</p>
                                )}
                            </div>
                            <Badge variant="secondary" className="shrink-0 text-[10px]">
                                #{orderId ?? '—'}
                            </Badge>
                        </div>
                    </div>

                    <div className="space-y-3 px-6 py-4">
                        <div className="grid grid-cols-2 gap-2">
                            <InfoRow icon={Tag} label="Suscripción" value={String(ep.subscription_title || ep.title || '—')} />
                            <InfoRow icon={DollarSign} label="Total" value={ep.total ? `Bs. ${ep.total}` : '—'} />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <InfoRow icon={CalendarDays} label="Fecha de venta" value={ep.start_date ? new Date(String(ep.start_date)).toLocaleDateString('es') : '—'} />
                            <InfoRow icon={Clock} label="Vence" value={selected ? new Date(selected.start).toLocaleDateString('es') : '—'} />
                        </div>
                        {ep.customer_email && String(ep.customer_email) ? (
                            <InfoRow icon={User} label="Email" value={String(ep.customer_email)} />
                        ) : null}
                    </div>

                    <Separator />

                    <div className="flex items-center justify-between gap-2 px-6 py-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setSelected(null)}
                            className="gap-2"
                        >
                            Cerrar
                        </Button>
                        <div className="flex items-center gap-2">
                            {chatId || contactPhone ? (
                                <Button
                                    type="button"
                                    variant="default"
                                    onClick={() => openChat(chatId, contactPhone, userName)}
                                    className="gap-2"
                                >
                                    <MessageCircle className="size-4" />
                                    Enviar mensaje
                                </Button>
                            ) : null}
                            {orderId ? (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => window.open(`https://gosystem.percyalvarez.lat/wp-admin/post.php?post=${orderId}&action=edit`, '_blank')}
                                    className="gap-2"
                                >
                                    <ExternalLink className="size-4" />
                                    Ver en WC
                                </Button>
                            ) : null}
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

Calendar.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'PosWoo', href: '/admin/pos-woo' },
        { title: 'Calendario', href: '/admin/pos-woo/calendario' },
    ],
};