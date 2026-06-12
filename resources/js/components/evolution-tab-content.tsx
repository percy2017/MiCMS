import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Briefcase, Check, ExternalLink, Globe, Loader2, X, Phone, AtSign, Clock, Building2, Tag, Calendar } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type BusinessData = {
    description?: string | null;
    website?: string | null;
    category?: string | null;
    business_hours?: {
        timezone?: string;
        business_config?: Array<{ day_of_week?: string; mode?: string; open_time?: string; close_time?: string }>;
    } | null;
};

type FetchResult = {
    ok: boolean;
    exists: boolean;
    jid: string | null;
    number: string | null;
    push_name?: string | null;
    profile_picture_url?: string | null;
    avatar_media_id?: number | null;
    is_business?: boolean;
    business_data?: BusinessData | null;
    error?: string;
};

type Props = {
    userId: number | null;
    initialPhone?: string;
    initialName?: string;
    fetchEndpoint: string;
    saveEndpoint: string;
    onSaved?: () => void;
};

function DetailRow({ icon: Icon, label, value }: { icon: React.ComponentType<{ className?: string }>; label: string; value: string | null | undefined }) {
    return (
        <div className="grid gap-1">
            <p className="flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                <Icon className="size-3" />
                {label}
            </p>
            <p className="text-sm">{value ?? '—'}</p>
        </div>
    );
}

export function EvolutionTabContent({
    userId,
    initialPhone = '',
    initialName = '',
    fetchEndpoint,
    saveEndpoint,
    onSaved,
}: Props) {
    const [phone, setPhone] = useState<string>(initialPhone);
    const [result, setResult] = useState<FetchResult | null>(null);
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [savedAt, setSavedAt] = useState<string | null>(null);

    const csrf = document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.getAttribute('content') ?? '';

    async function handleFetch(): Promise<void> {
        if (!phone.trim()) return;
        setLoading(true);
        setResult(null);
        setSavedAt(null);
        setSubmitError(null);
        try {
            const r = await fetch(fetchEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    phone: phone,
                }),
            });
            const data: FetchResult = await r.json();
            setResult(data);
        } catch (e) {
            setResult({ ok: false, exists: false, jid: null, number: null, error: (e as Error).message });
        } finally {
            setLoading(false);
        }
    }

    async function handleSave(): Promise<void> {
        if (!result?.exists) return;
        setSubmitting(true);
        setSubmitError(null);
        try {
            const r = await fetch(saveEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    phone: phone,
                    push_name: result.push_name,
                    profile_picture_url: result.profile_picture_url ?? null,
                    avatar_media_id: result.avatar_media_id ?? null,
                    whatsapp_jid: result.jid ?? null,
                    is_business: result.is_business ?? false,
                    business_data: result.business_data ?? null,
                }),
            });
            if (r.redirected) {
                window.location.href = r.url;
                return;
            }
            const data = await r.json().catch(() => ({}));
            if (data.error) {
                setSubmitError(data.error);
            } else {
                setSavedAt(new Date().toLocaleTimeString());
                if (onSaved) onSaved();
                router.reload({ only: ['user'] });
            }
        } catch (e) {
            setSubmitError((e as Error).message);
        } finally {
            setSubmitting(false);
        }
    }

    const canFetch = !loading && phone.trim().length > 0;
    const canSave = result?.exists && !submitting;

    return (
        <Card>
            <CardHeader>
                <CardTitle>{userId ? 'Consultar Evolution' : 'Crear usuario desde Evolution'}</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="ev-phone">Teléfono</Label>
                            <Input
                                id="ev-phone"
                                type="tel"
                                value={phone}
                                onChange={(e) => {
                                    setPhone(e.target.value);
                                    setResult(null);
                                    setSavedAt(null);
                                }}
                                placeholder="+591 7XXXXXXX"
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                onClick={handleFetch}
                                disabled={!canFetch}
                            >
                                {loading && <Loader2 className="mr-1.5 size-4 animate-spin" />}
                                Obtener datos
                            </Button>
                        </div>

                        {result?.error && (
                            <div className="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                                {result.error}
                            </div>
                        )}

                        {result && !result.exists && !result.error && (
                            <div className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                                <X className="mt-0.5 size-4 shrink-0" />
                                <p>Este número no tiene WhatsApp.</p>
                            </div>
                        )}

                        {submitError && <p className="text-sm text-destructive">{submitError}</p>}

                        {savedAt && (
                            <p className="text-sm text-emerald-700 dark:text-emerald-400">
                                Guardado a las {savedAt}.
                            </p>
                        )}

                        {result?.exists && !savedAt && (
                            <div className="flex items-center gap-3">
                                <Button type="button" onClick={handleSave} disabled={!canSave}>
                                    {submitting && <Loader2 className="mr-1.5 size-4 animate-spin" />}
                                    {userId ? 'Guardar cambios' : 'Crear usuario'}
                                </Button>
                            </div>
                        )}
                    </div>

                    <div className="space-y-4">
                        {result?.exists ? (
                            <div className="rounded-md border p-4">
                                <p className="mb-4 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Datos obtenidos de WhatsApp
                                </p>

                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <div className="space-y-4">
                                        <div className="flex items-center gap-4">
                                            {result.profile_picture_url ? (
                                                <img
                                                    src={result.profile_picture_url}
                                                    alt={result.push_name ?? 'WhatsApp'}
                                                    className="size-20 shrink-0 rounded-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex size-20 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                                    <Check className="size-8" />
                                                </div>
                                            )}
                                            <div>
                                                <p className="text-base font-semibold">
                                                    {result.push_name ?? 'Sin nombre'}
                                                </p>
                                                {result.is_business && (
                                                    <span className="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium">
                                                        <Briefcase className="size-3" />
                                                        Business
                                                    </span>
                                                )}
                                            </div>
                                        </div>

                                        <DetailRow icon={Phone} label="Teléfono" value={result.number} />
                                        <DetailRow icon={AtSign} label="WhatsApp JID" value={result.jid} />
                                        <DetailRow icon={Building2} label="Tipo de cuenta" value={result.is_business ? 'Business' : 'Personal'} />
                                    </div>

                                    <div className="space-y-4">
                                        {result.is_business && result.business_data ? (
                                            <>
                                                <DetailRow icon={Tag} label="Categoría" value={result.business_data.category} />
                                                <DetailRow icon={Globe} label="Sitio web" value={result.business_data.website} />
                                                <DetailRow icon={Calendar} label="Zona horaria" value={result.business_data.business_hours?.timezone} />

                                                {result.business_data.description && (
                                                    <div className="grid gap-1">
                                                        <p className="flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                                            Descripción
                                                        </p>
                                                        <p className="text-sm leading-relaxed text-muted-foreground">
                                                            {result.business_data.description}
                                                        </p>
                                                    </div>
                                                )}

                                                {result.business_data.business_hours?.business_config && result.business_data.business_hours.business_config.length > 0 && (
                                                    <div className="grid gap-1">
                                                        <p className="flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                                            <Clock className="size-3" />
                                                            Horarios
                                                        </p>
                                                        <div className="grid grid-cols-2 gap-1 text-sm">
                                                            {result.business_data.business_hours.business_config.map((h, i) => (
                                                                <span key={i} className="text-muted-foreground">
                                                                    {h.day_of_week ?? `Día ${i + 1}`}: {h.mode ?? '—'}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}
                                            </>
                                        ) : result.is_business ? (
                                            <div className="flex items-center justify-center rounded-md border border-dashed p-6 text-sm text-muted-foreground">
                                                No hay información de negocio disponible
                                            </div>
                                        ) : (
                                            <div className="flex items-center justify-center rounded-md border border-dashed p-6 text-sm text-muted-foreground">
                                                Esta cuenta no es de tipo Business
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="flex h-full min-h-[200px] items-center justify-center rounded-md border border-dashed p-6 text-sm text-muted-foreground">
                                Ingresa un teléfono y haz clic en "Obtener datos" para ver toda la información de WhatsApp
                            </div>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}