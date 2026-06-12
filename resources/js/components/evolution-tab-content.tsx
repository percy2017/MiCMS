import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Briefcase, Check, ExternalLink, Globe, Loader2, X } from 'lucide-react';
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
        business_config?: Array<{ day_of_week?: string; mode?: string }>;
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
            <CardContent className="space-y-4">
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

                {result?.exists && (
                    <div className="rounded-md border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm">
                        <p className="mb-3 text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                            Previsualización
                        </p>
                        <div className="flex items-start gap-3">
                            {result.profile_picture_url ? (
                                <img
                                    src={result.profile_picture_url}
                                    alt={result.push_name ?? 'WhatsApp'}
                                    className="size-16 shrink-0 rounded-full object-cover"
                                />
                            ) : (
                                <div className="flex size-16 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-700">
                                    <Check className="size-6" />
                                </div>
                            )}
                            <div className="min-w-0 flex-1 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="truncate text-base font-semibold text-emerald-900 dark:text-emerald-100">
                                        {result.push_name ?? 'Sin nombre detectado'}
                                    </p>
                                    {result.is_business && (
                                        <span className="inline-flex items-center gap-1 rounded-full border border-emerald-500/40 bg-emerald-500/20 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:text-emerald-200">
                                            <Briefcase className="size-3" />
                                            Business
                                        </span>
                                    )}
                                </div>
                                <p className="break-all text-xs opacity-80">
                                    {result.number} → {result.jid}
                                </p>
                            </div>
                        </div>

                        {result.is_business && result.business_data && (
                            <div className="mt-4 space-y-1.5 border-t border-emerald-500/20 pt-3 text-xs text-emerald-900/90 dark:text-emerald-100/90">
                                {result.business_data.description && (
                                    <p>{result.business_data.description}</p>
                                )}
                                {result.business_data.website && (
                                    <a
                                        href={result.business_data.website}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1 break-all text-emerald-700 underline-offset-2 hover:underline dark:text-emerald-200"
                                    >
                                        <Globe className="size-3 shrink-0" />
                                        {result.business_data.website}
                                        <ExternalLink className="size-3 shrink-0" />
                                    </a>
                                )}
                                {result.business_data.category && (
                                    <p className="text-xs opacity-80">
                                        <span className="font-medium">Categoría:</span> {result.business_data.category}
                                    </p>
                                )}
                                {result.business_data.business_hours?.timezone && (
                                    <p className="text-xs opacity-80">
                                        <span className="font-medium">Zona horaria:</span> {result.business_data.business_hours.timezone}
                                    </p>
                                )}
                            </div>
                        )}

                        {!result.push_name && (
                            <p className="mt-3 text-xs text-amber-700 dark:text-amber-300">
                                No se detectó nombre. Ve a la pestaña Manual para asignarlo.
                            </p>
                        )}
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
            </CardContent>
        </Card>
    );
}
