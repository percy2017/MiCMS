import { Head } from '@inertiajs/react';
import { ChevronDown, UploadIcon } from 'lucide-react';
import { useState } from 'react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DataTableToolbar, type ToolbarFilter } from '@/components/data-table-toolbar';
import { TablePagination } from '@/components/table-pagination';
import { MediaCard } from '@/pages/media/partials/media-card';
import { MediaUploader } from '@/pages/media/partials/media-uploader';
import { useTableSearch } from '@/hooks/use-table-search';
import { index as mediaIndex } from '@/routes/admin/media';
import { cn } from '@/lib/utils';

type MediaItem = {
    id: number;
    name: string;
    title: string | null;
    mime_type: string;
    human_size: string;
    url: string;
    is_image: boolean;
    is_video: boolean;
    is_audio: boolean;
    created_at: string;
    created_at_diff: string;
};

type PageProps = {
    media: {
        data: MediaItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search: string;
        type: string | null;
    };
    max_size: number;
};

const TYPE_OPTIONS = [
    { value: 'image', label: 'Imágenes' },
    { value: 'video', label: 'Videos' },
    { value: 'audio', label: 'Audio' },
    { value: 'application', label: 'Documentos' },
];

export default function MediaIndex({ media, filters, max_size }: PageProps) {
    const [uploadOpen, setUploadOpen] = useState(false);

    const table = useTableSearch<MediaItem>({
        endpoint: '/admin/media/search',
        initialData: media,
        perPage: 24,
        initialFilters: {
            search: filters.search ?? '',
            type: filters.type ?? '',
        },
    });

    const typeFilters: ToolbarFilter[] = [
        {
            key: 'type',
            label: 'Tipo',
            value: table.filters.type ?? '',
            onChange: (v) => table.setFilter('type', v),
            placeholder: 'Todos los tipos',
            options: TYPE_OPTIONS,
        },
    ];

    return (
        <>
            <Head title="Medios" />

            <div className="h-full space-y-4 overflow-y-auto p-4">
                <Collapsible
                    open={uploadOpen}
                    onOpenChange={setUploadOpen}
                    className="rounded-lg border bg-card"
                >
                    <CollapsibleTrigger
                        type="button"
                        className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition-colors hover:bg-muted/50"
                        aria-label={uploadOpen ? 'Colapsar cargador' : 'Expandir cargador'}
                    >
                        <span className="flex items-center gap-2 text-sm font-medium">
                            <UploadIcon className="size-4 text-muted-foreground" />
                            Subir archivo
                        </span>
                        <ChevronDown
                            className={cn(
                                'size-4 text-muted-foreground transition-transform duration-200',
                                uploadOpen && 'rotate-180',
                            )}
                        />
                    </CollapsibleTrigger>
                    <CollapsibleContent className="overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down">
                        <div className="border-t p-4">
                            <MediaUploader maxSize={max_size} onUploaded={() => table.refresh()} />
                        </div>
                    </CollapsibleContent>
                </Collapsible>

                <DataTableToolbar
                    search={table.search}
                    onSearchChange={table.setSearch}
                    searchPlaceholder="Buscar por nombre, título o texto alternativo..."
                    loading={table.loading}
                    total={table.total}
                    totalLabel={`archivo${table.total !== 1 ? 's' : ''}`}
                    filters={typeFilters}
                />

                {table.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
                        {table.search || table.filters.type
                            ? 'Sin resultados para la búsqueda'
                            : 'No hay archivos. Arrastra algo arriba para empezar.'}
                    </div>
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                            {table.data.map((item) => (
                                <MediaCard key={item.id} item={item} />
                            ))}
                        </div>

                        <TablePagination
                            currentPage={table.currentPage}
                            lastPage={table.lastPage}
                            onPageChange={table.goPage}
                            total={table.total}
                            perPage={24}
                            itemLabel={`archivo${table.total !== 1 ? 's' : ''}`}
                        />
                    </>
                )}
            </div>
        </>
    );
}

MediaIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Medios', href: mediaIndex().url },
    ],
};

