import { Head, usePage } from '@inertiajs/react';
import { Render } from '@puckeditor/core';
import type { Data } from '@puckeditor/core';
import '@puckeditor/core/puck.css';
import { ChatWidget } from '@/components/chat/chat-widget';
import Footer from '@/paginas/components/footer';
import Header from '@/paginas/components/header';
import { MenuProvider, type MenusByLocation } from '@/paginas/menu-context';
import { puckConfig } from '@/paginas/puck-config';

type EnabledPackage = {
    id: number;
    slug: string;
    label: string;
    icon?: string | null;
};

type PageProps = {
    page: {
        id: number;
        title: string;
        slug: string;
        puck_data: Data;
    };
    menus: MenusByLocation;
    enabledPackages: EnabledPackage[];
};

export default function PaginaShow({ page, menus }: PageProps) {
    const { props } = usePage<PageProps>();
    const hasChatWidget = (props.enabledPackages ?? []).some(
        (pkg) => pkg.slug === 'chat-widget',
    );

    return (
        <MenuProvider menus={menus}>
            <Head title={page.title}>
                <meta
                    name="description"
                    content={`Página ${page.title}`}
                />
            </Head>

            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <Header />
                <main className="flex-1">
                    <div className="mx-auto w-full max-w-4xl px-4 py-8 sm:py-12">
                        <Render config={puckConfig} data={page.puck_data} />
                    </div>
                </main>
                <Footer />
            </div>

            {hasChatWidget ? <ChatWidget /> : null}
        </MenuProvider>
    );
}
