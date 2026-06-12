import { Head, useForm } from '@inertiajs/react';
import { Loader2, Mail, MessageCircle, Phone, User } from 'lucide-react';
import { useState } from 'react';
import AvatarPicker from '@/components/avatar-picker';
import { EvolutionTabContent } from '@/components/evolution-tab-content';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { admin } from '@/routes';
import { index as indexRoute, store } from '@/routes/admin/usuarios';
import { store as storeWithEvolution, check as checkWithEvolution } from '@/routes/admin/usuarios/with-evolution';

type Role = { id: number; name: string };

type PageProps = { roles: Role[] };

export default function UsuariosCreate({ roles }: PageProps) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        avatar_media_id: null as number | null,
        password: '',
        password_confirmation: '',
        roles: [] as string[],
    });

    const [activeTab, setActiveTab] = useState<'manual' | 'evolution'>('manual');

    function toggleRole(name: string): void {
        setData('roles', data.roles.includes(name)
            ? data.roles.filter((r) => r !== name)
            : [...data.roles, name],
        );
    }

    function handleSubmit(e: React.FormEvent): void {
        e.preventDefault();
        post(store.url());
    }

    return (
        <>
            <Head title="Nuevo Usuario" />

            <div className="space-y-6 p-4">
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
                                                    placeholder="Ej: María Pérez"
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
                                                    placeholder="usuario@ejemplo.com"
                                                    required
                                                />
                                                {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                                            </div>

                                            <div className="grid gap-2 md:col-span-2">
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
                                        </div>

                                        <div className="flex flex-col items-center gap-2 border-t pt-6 md:border-l md:border-t-0 md:pl-6 md:pt-0">
                                            <p className="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                                <User className="size-3" />
                                                Avatar
                                            </p>
                                            <AvatarPicker
                                                value={data.avatar_media_id}
                                                previewUrl={null}
                                                name={data.name}
                                                onChange={(id) => setData('avatar_media_id', id)}
                                                error={errors.avatar_media_id}
                                            />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Credenciales</CardTitle>
                                        <CardDescription>Contraseña para que el usuario pueda iniciar sesión.</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="password">Contraseña</Label>
                                            <Input
                                                id="password"
                                                type="password"
                                                value={data.password}
                                                onChange={(e) => setData('password', e.target.value)}
                                                required
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
                                                required
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>Roles</CardTitle>
                                        <CardDescription>Selecciona los roles que tendrá este usuario.</CardDescription>
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
                                    Crear usuario
                                </Button>
                            </div>
                        </form>
                    </TabsContent>

                    <TabsContent value="evolution" className="mt-6">
                        <EvolutionTabContent
                            userId={null}
                            fetchEndpoint={checkWithEvolution().url}
                            saveEndpoint={storeWithEvolution().url}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

UsuariosCreate.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Usuarios', href: indexRoute() },
        { title: 'Nuevo', href: '/admin/usuarios/crear' },
    ],
};
