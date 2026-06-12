import { usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import PosWooSalesPanel from './PosWooSalesPanel';

type ConversationDetail = {
    id: number;
    name: string;
    email: string | null;
    page_url: string | null;
    status: 'open' | 'closed';
    user_id: number;
    user_avatar_url: string | null;
    user_phone: string | null;
    user_whatsapp_jid: string | null;
    external_id: string | null;
    channel_id: number | null;
    channel_name: string | null;
    last_message_at: string | null;
    messages: Array<{ id: number }>;
};

type Props = {
    active: ConversationDetail;
    onClose: () => void;
};

export default function ChatDetailsPanel({ active, onClose }: Props) {
    const page = usePage();

    const enabledPackages = (page.props as { enabledPackages?: Array<{ slug: string }> }).enabledPackages ?? [];
    const isPosWooEnabled = enabledPackages.some((p) => p.slug === 'poswoo' || p.slug === 'pos-woo');
    const posWooPhone = active.user_phone
        ?? (active.user_whatsapp_jid ? active.user_whatsapp_jid.split('@')[0] : null);

    return (
        <aside className="flex h-full w-full flex-col border-l bg-card">
            <div className="flex shrink-0 items-center justify-between border-b px-4 py-3">
                <h2 className="text-sm font-semibold">Detalles</h2>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={onClose}
                    title="Cerrar (ESC)"
                    className="size-8"
                >
                    <X className="size-4" />
                </Button>
            </div>

            <div className="flex-1 overflow-y-auto">
                <div className="border-b px-4 py-6">
                    <p className="mb-3 px-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                        Contacto
                    </p>
                    <div className="flex flex-col items-center gap-2 text-center">
                        {active.user_avatar_url ? (
                            <img
                                src={active.user_avatar_url}
                                alt={active.name}
                                className="size-20 rounded-full object-cover"
                            />
                        ) : (
                            <div className="flex size-20 items-center justify-center rounded-full bg-primary/10 text-xl font-semibold text-primary">
                                {(active.name?.[0] ?? '?').toUpperCase()}
                            </div>
                        )}
                        <div className="min-w-0">
                            <h3 className="truncate text-base font-semibold">{active.name}</h3>
                            {active.user_phone && (
                                <p className="mt-0.5 text-sm text-muted-foreground">{active.user_phone}</p>
                            )}
                        </div>
                    </div>
                </div>

                {isPosWooEnabled && (
                    <PosWooSalesPanel phone={posWooPhone} />
                )}
            </div>
        </aside>
    );
}
