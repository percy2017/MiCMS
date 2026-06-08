import { Head } from '@inertiajs/react';
import { Render } from '@puckeditor/core';
import type { Data } from '@puckeditor/core';
import '@puckeditor/core/puck.css';
import Footer from '@/paginas/components/footer';
import Header from '@/paginas/components/header';
import { MenuProvider, type MenusByLocation } from '@/paginas/menu-context';
import { puckConfig } from '@/paginas/puck-config';

type PageProps = {
    page: {
        id: number;
        title: string;
        slug: string;
        puck_data: Data;
    };
    menus: MenusByLocation;
};

export default function PaginaShow({ page, menus }: PageProps) {
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
        </MenuProvider>
    );
}
