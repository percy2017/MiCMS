import { Head, router } from '@inertiajs/react';
import { Puck, type Data } from '@puckeditor/core';
import '@puckeditor/core/puck.css';
import { Eye } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { emptyPuckData, puckConfig } from '@/paginas/puck-config';
import { MenuProvider, type MenusByLocation } from '@/paginas/menu-context';
import { update } from '@/routes/admin/paginas';


type PageData = {
    id: number;
    title: string;
    slug: string;
    status: 'draft' | 'published';
    is_home: boolean;
    puck_data: Data;
};

type PageProps = {
    page: PageData;
    menus: MenusByLocation;
};

export default function PaginaEdit({ page, menus }: PageProps) {
    const [data, setData] = useState<Data>(
        page.puck_data &&
        Array.isArray((page.puck_data as Data).content) &&
        (page.puck_data as Data).content.length > 0
            ? (page.puck_data as Data)
            : emptyPuckData,
    );
    const [status, setStatus] = useState<'draft' | 'published'>(page.status);

    function save(nextStatus: 'draft' | 'published'): void {
        setStatus(nextStatus);

        router.patch(
            update.url({ page: page.id }),
            {
                puck_data: data,
                status: nextStatus,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    }

    const publicUrl = page.is_home ? '/' : `/${page.slug}`;

    return (
        <>
            <Head title={`Editar: ${page.title}`} />

            <div className="h-[calc(100vh-4rem)] overflow-hidden">
                <MenuProvider menus={menus}>
                    <Puck
                        config={puckConfig}
                        data={data}
                        headerTitle={page.title}
                        onChange={(next) => setData(next)}
                        onPublish={(next) => {
                            setData(next);
                            save('published');
                        }}
                        overrides={{
                            headerActions: ({ children }) => (
                                <>
                                    {status === 'published' ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={publicUrl}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <Eye className="mr-1 size-4" />
                                                Ver página
                                            </a>
                                        </Button>
                                    ) : null}
                                    {children}
                                </>
                            ),
                        }}
                    />
                </MenuProvider>
            </div>
        </>
    );
}

PaginaEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Pagina', href: '/admin/paginas' },
        { title: 'Editar' },
    ],
};

