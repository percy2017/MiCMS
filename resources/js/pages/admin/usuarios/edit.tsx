import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Loader2, Mail, MessageCircle, Phone, User } from 'lucide-react';
import { useEffect, useState } from 'react';
import AvatarPicker from '@/components/avatar-picker';
import { EvolutionTabContent } from '@/components/evolution-tab-content';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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
    avatar_url: string | null;
    avatar_media_id: number | null;
    roles: string[];
};
type PageProps = { user: User; roles: Role[] };

export default function UsuariosEdit({ user, roles }: PageProps) {
    const { data, setData, patch, processing, errors } = useForm({
        name: user.name,
        email: user.email,
        phone: user.phone ?? '',
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
            avatar_media_id: user.avatar_media_id ?? null,
            password: '',
            password_confirmation: '',
            roles: user.roles,
        });
    }, [user.id, user.name, user.email, user.phone, user.avatar_media_id, user.avatar_url, JSON.stringify(user.roles)]);

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
                <Button asChild variant="ghost" size="sm">
                    <Link href={indexRoute()}>
                        <ArrowLeft className="mr-1 size-4" /> Volver
                    </Link>
                </Button>

                <Heading title={`Editar ${user.name}`} description="Modifica los datos y roles del usuario." />

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
                                    <CardTitle className="flex items-center gap-2">
                                        <User className="size-4" />
                                        Avatar
                                    </CardTitle>
                                    <CardDescription>
                                        Imagen de perfil del usuario.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <AvatarPicker
                                        value={data.avatar_media_id}
                                        previewUrl={user.avatar_url}
                                        name={data.name}
                                        onChange={(id) => setData('avatar_media_id', id)}
                                        error={errors.avatar_media_id}
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Datos personales</CardTitle>
                                    <CardDescription>Información básica de contacto.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
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
                                        {errors.phone && <p className="text-sm text-destructive">{errors.phone}</p>}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Credenciales</CardTitle>
                                    <CardDescription>Deja los campos vacíos para mantener la contraseña actual.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
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
