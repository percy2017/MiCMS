import { Head, Link, useForm } from '@inertiajs/react';
import { BadgeCheck, Building2, Copy, ExternalLink, Globe, Loader2, Mail, MessageCircle, Phone, User } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import AvatarPicker from '@/components/avatar-picker';
import { EvolutionTabContent } from '@/components/evolution-tab-content';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { describeCountry } from '@/lib/country';
import { admin } from '@/routes';
import { index as indexRoute, update } from '@/routes/admin/usuarios';
import { save as saveEvolution } from '@/routes/admin/usuarios/evolution-data';
import { check as checkEvolution } from '@/routes/admin/usuarios/evolution-channel';

type Role = { id: number; name: string };
type User = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    country_code: string | null;
    whatsapp_jid: string | null;
    is_whatsapp_business: boolean;
    avatar_url: string | null;
    avatar_media_id: number | null;
    roles: string[];
    email_verified_at: string | null;
    created_at: string | null;
};
type PageProps = {
    user: User;
    roles: Role[];
};

function CopyableField({ label, value, mono = false }: { label: string; value: string | null; mono?: boolean }): React.ReactElement {
    const display = value && value !== '' ? value : '—';
    const copyable = !!value && value !== '';
    return (
        <div className="grid gap-1">
            <p className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
            <div className="flex items-center gap-1.5">
                <span className={`min-w-0 flex-1 truncate text-sm ${mono ? 'font-mono text-xs' : ''}`}>{display}</span>
                {copyable && (
                    <button
                        type="button"
                        onClick={() => {
                            navigator.clipboard?.writeText(value as string)
                                .then(() => toast.success(`${label} copiado`))
                                .catch(() => toast.error('No se pudo copiar'));
                        }}
                        className="shrink-0 rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        title={`Copiar ${label.toLowerCase()}`}
                    >
                        <Copy className="size-3.5" />
                    </button>
                )}
            </div>
        </div>
    );
}

function CountryBadge({ countryCode }: { countryCode: string | null; phone?: string | null }): React.ReactElement {
    const detected = useMemo(() => describeCountry(countryCode), [countryCode]);
    const [imgFailed, setImgFailed] = useState(false);

    if (!detected) {
        return (
            <p className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                <Globe className="size-3" />
                País no detectado aún (se detecta al guardar)
            </p>
        );
    }

    return (
        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
            {imgFailed ? (
                <span className="inline-flex size-4 shrink-0 items-center justify-center rounded-sm bg-primary/10 font-mono text-[9px] font-semibold uppercase text-primary">
                    {detected.code}
                </span>
            ) : (
                <img
                    src={`https://flagcdn.com/${detected.code.toLowerCase()}.svg`}
                    alt={detected.name}
                    width={16}
                    height={12}
                    className="size-4 shrink-0 rounded-sm object-cover"
                    onError={() => setImgFailed(true)}
                />
            )}
            <span className="font-medium text-foreground">{detected.name}</span>
            {detected.dialCode && (
                <span className="font-mono text-[10px]">{detected.dialCode}</span>
            )}
        </p>
    );
}

export default function UsuariosEdit({ user, roles }: PageProps) {
    const { data, setData, patch, processing, errors } = useForm({
        name: user.name,
        email: user.email,
        phone: user.phone ?? '',
        whatsapp_jid: user.whatsapp_jid ?? '',
        is_whatsapp_business: user.is_whatsapp_business ?? false,
        email_verified: !!user.email_verified_at,
        avatar_media_id: user.avatar_media_id ?? null,
        password: '',
        password_confirmation: '',
        roles: user.roles,
    });

    const [activeTab, setActiveTab] = useState<'manual' | 'evolution'>('manual');

    useEffect(() => {
        setData({
            name: user.name,
            email: user.email,
            phone: user.phone ?? '',
            whatsapp_jid: user.whatsapp_jid ?? '',
            is_whatsapp_business: user.is_whatsapp_business ?? false,
            email_verified: !!user.email_verified_at,
            avatar_media_id: user.avatar_media_id ?? null,
            password: '',
            password_confirmation: '',
            roles: user.roles,
        });
    }, [user.id, user.name, user.email, user.phone, user.whatsapp_jid, user.is_whatsapp_business, user.email_verified_at, user.avatar_media_id, user.avatar_url, JSON.stringify(user.roles)]);

    function toggleRole(name: string): void {
        setData('roles', data.roles.includes(name)
            ? data.roles.filter((r) => r !== name)
            : [...data.roles, name],
        );
    }

    function handleSubmit(e: React.FormEvent): void {
        e.preventDefault();
        patch(update({ user: user.id }).url);
    }

    return (
        <>
            <Head title={`Editar ${user.name}`} />

            <div className="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto p-4">
                <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as 'manual' | 'evolution')}>
                    <TabsList>
                        <TabsTrigger value="manual">
                            <User className="mr-1.5 size-4" /> Manual
                        </TabsTrigger>
                        <TabsTrigger value="evolution">
                            <MessageCircle className="mr-1.5 size-4" /> Evolution
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="manual" className="mt-6">
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Datos personales</CardTitle>
                                    <CardDescription>Información básica de contacto.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-1 gap-6 md:grid-cols-[1fr_220px]">
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="name">Nombre completo</Label>
                                                <Input
                                                    id="name"
                                                    value={data.name}
                                                    onChange={(e) => setData('name', e.target.value)}
                                                    required
                                                />
                                                {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="email" className="flex items-center gap-1.5">
                                                    <Mail className="size-3.5" />
                                                    Email
                                                </Label>
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    value={data.email}
                                                    onChange={(e) => setData('email', e.target.value)}
                                                    required
                                                />
                                                {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="phone" className="flex items-center gap-1.5">
                                                    <Phone className="size-3.5" />
                                                    Teléfono
                                                </Label>
                                                <Input
                                                    id="phone"
                                                    type="tel"
                                                    value={data.phone}
                                                    onChange={(e) => setData('phone', e.target.value)}
                                                    placeholder="+591 7XXXXXXX"
                                                />
                                                <CountryBadge countryCode={user.country_code} phone={data.phone} />
                                                {errors.phone && <p className="text-sm text-destructive">{errors.phone}</p>}
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="whatsapp_jid" className="flex items-center gap-1.5">
                                                    <MessageCircle className="size-3.5" />
                                                    WhatsApp JID
                                                </Label>
                                                <Input
                                                    id="whatsapp_jid"
                                                    value={data.whatsapp_jid}
                                                    onChange={(e) => setData('whatsapp_jid', e.target.value)}
                                                    placeholder="59172811368@s.whatsapp.net"
                                                />
                                                {errors.whatsapp_jid && <p className="text-sm text-destructive">{errors.whatsapp_jid}</p>}
                                            </div>
                                        </div>

                                        <div className="flex flex-col items-center gap-2 border-t pt-6 md:border-l md:border-t-0 md:pl-6 md:pt-0">
                                            <p className="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                                <User className="size-3" />
                                                Avatar
                                            </p>
                                            <AvatarPicker
                                                value={data.avatar_media_id}
                                                previewUrl={user.avatar_url}
                                                name={data.name}
                                                onChange={(id) => setData('avatar_media_id', id)}
                                                error={errors.avatar_media_id}
                                            />

                                            <div className="mt-4 grid w-full grid-cols-2 gap-2 border-t pt-4">
                                                <label className="flex cursor-pointer flex-col items-center gap-1 rounded-md border bg-muted/30 px-2 py-2 text-center text-xs transition hover:bg-muted/50">
                                                    <BadgeCheck className={data.email_verified ? 'size-4 text-emerald-500' : 'size-4 text-muted-foreground/50'} />
                                                    <span className="font-medium">¿Verificado?</span>
                                                    <input
                                                        type="checkbox"
                                                        checked={data.email_verified}
                                                        onChange={(e) => setData('email_verified', e.target.checked)}
                                                        className="size-4 accent-primary"
                                                    />
                                                </label>
                                                <label className="flex cursor-pointer flex-col items-center gap-1 rounded-md border bg-muted/30 px-2 py-2 text-center text-xs transition hover:bg-muted/50">
                                                    <Building2 className={data.is_whatsapp_business ? 'size-4 text-emerald-500' : 'size-4 text-muted-foreground/50'} />
                                                    <span className="font-medium">¿Business?</span>
                                                    <input
                                                        type="checkbox"
                                                        checked={data.is_whatsapp_business}
                                                        onChange={(e) => setData('is_whatsapp_business', e.target.checked)}
                                                        className="size-4 accent-primary"
                                                    />
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Credenciales</CardTitle>
                                        <CardDescription>Deja los campos vacíos para mantener la contraseña actual.</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="password">Nueva contraseña</Label>
                                            <Input
                                                id="password"
                                                type="password"
                                                value={data.password}
                                                onChange={(e) => setData('password', e.target.value)}
                                                placeholder="(sin cambios)"
                                            />
                                            {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="password_confirmation">Confirmar contraseña</Label>
                                            <Input
                                                id="password_confirmation"
                                                type="password"
                                                value={data.password_confirmation}
                                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>Roles</CardTitle>
                                        <CardDescription>Roles asignados al usuario.</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {roles.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">No hay roles disponibles.</p>
                                        ) : (
                                            <div className="flex flex-wrap gap-2">
                                                {roles.map((role) => {
                                                    const checked = data.roles.includes(role.name);
                                                    return (
                                                        <label
                                                            key={role.id}
                                                            className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition ${checked ? 'border-primary bg-primary/10' : 'hover:bg-muted/50'}`}
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                checked={checked}
                                                                onChange={() => toggleRole(role.name)}
                                                            />
                                                            {role.name}
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        )}
                                        {errors.roles && <p className="text-sm text-destructive">{errors.roles}</p>}
                                    </CardContent>
                                </Card>
                            </div>

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2 className="mr-2 size-4 animate-spin" />}
                                    Guardar cambios
                                </Button>
                                <Button type="button" variant="outline" asChild>
                                    <Link href={indexRoute()}>Cancelar</Link>
                                </Button>
                            </div>
                        </form>
                    </TabsContent>

                    <TabsContent value="evolution" className="mt-6">
                        <EvolutionTabContent
                            userId={user.id}
                            initialPhone={user.phone ?? ''}
                            initialName={user.name}
                            fetchEndpoint={checkEvolution({ user: user.id }).url}
                            saveEndpoint={saveEvolution({ user: user.id }).url}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

UsuariosEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Usuarios', href: indexRoute() },
        { title: 'Editar', href: '' },
    ],
};
