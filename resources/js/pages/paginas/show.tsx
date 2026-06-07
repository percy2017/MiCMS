import { Head } from '@inertiajs/react';
import { Render } from '@puckeditor/core';
import type { Data } from '@puckeditor/core';
import '@puckeditor/core/puck.css';
import { puckConfig } from '@/paginas/puck-config';
import { MenuProvider, type MenusByLocation } from '@/paginas/menu-context';

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
        <>
            <Head title={page.title}>
                <meta
                    name="description"
                    content={`Página ${page.title}`}
                />
            </Head>

            <MenuProvider menus={menus}>
                <Render config={puckConfig} data={page.puck_data} />
            </MenuProvider>
        </>
    );
}
