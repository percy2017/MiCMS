import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { LogoPicker } from '@/components/logo-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GeneralSettingsController from '@/actions/App/Http/Controllers/Settings/GeneralSettingsController';
import { edit } from '@/routes/settings/general';

type GeneralProps = {
    settings: {
        site_name: string;
        site_tagline: string;
        site_logo_id: number | null;
        site_logo_url: string | null;
    };
};

export default function General({ settings }: GeneralProps) {
    const [logoId, setLogoId] = useState<number | null>(settings.site_logo_id);
    const [logoUrl, setLogoUrl] = useState<string | null>(
        settings.site_logo_url,
    );
    return (
        <>
            <Head title="General settings" />

            <h1 className="sr-only">General settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="General"
                    description="Update your site name, tagline and logo"
                />

                <Form
                    {...GeneralSettingsController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="site_name">Site name</Label>

                                <Input
                                    id="site_name"
                                    className="mt-1 block w-full"
                                    defaultValue={settings.site_name}
                                    name="site_name"
                                    required
                                    placeholder="My Site"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.site_name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="site_tagline">Tagline</Label>

                                <Input
                                    id="site_tagline"
                                    className="mt-1 block w-full"
                                    defaultValue={settings.site_tagline}
                                    name="site_tagline"
                                    placeholder="Brief description of your site"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.site_tagline}
                                />
                            </div>

                            <input
                                type="hidden"
                                name="site_logo_id"
                                value={logoId ?? ''}
                            />

                            <LogoPicker
                                logoId={logoId}
                                logoUrl={logoUrl}
                                onChange={(id, url) => {
                                    setLogoId(id);
                                    setLogoUrl(url);
                                }}
                            />

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

General.layout = {
    breadcrumbs: [
        {
            title: 'General settings',
            href: edit(),
        },
    ],
};
