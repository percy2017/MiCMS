import { Head, Link, router } from '@inertiajs/react';
import { SearchIcon } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { MediaCard } from '@/pages/media/partials/media-card';
import { MediaUploader } from '@/pages/media/partials/media-uploader';
import { index as mediaIndex } from '@/routes/admin/media';

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

type Paginator<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
};

type PageProps = {
    media: Paginator<MediaItem>;
    filters: {
        search: string;
        type: string | null;
    };
    max_size: number;
};

const TYPE_OPTIONS = [
    { value: 'all', label: 'Todos los tipos' },
    { value: 'image', label: 'Imágenes' },
    { value: 'video', label: 'Videos' },
    { value: 'audio', label: 'Audio' },
    { value: 'application', label: 'Documentos' },
];

export default function MediaIndex({ media, filters, max_size }: PageProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (overrides: Record<string, string | null> = {}) => {
        router.get(
            mediaIndex.url(),
            {
                search: overrides.search ?? search,
                type:
                    overrides.type !== undefined
                        ? overrides.type
                        : (filters.type ?? null),
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const onTypeChange = (value: string) => {
        const next = value === 'all' ? null : value;
        apply({ type: next });
    };

    const onSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        apply();
    };

    return (
        <>
            <Head title="Medios" />

            <div className="space-y-6 p-4">
                {/* <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Medios"
                        description="Biblioteca de archivos subidos"
                    />
                </div> */}

                <MediaUploader maxSize={max_size} />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <form onSubmit={onSearchSubmit} className="flex-1">
                        <div className="relative">
                            <SearchIcon className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar por nombre, título o texto alternativo…"
                                className="pl-9"
                            />
                        </div>
                    </form>
                    <Select
                        value={filters.type ?? 'all'}
                        onValueChange={onTypeChange}
                    >
                        <SelectTrigger className="sm:w-48">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {TYPE_OPTIONS.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {media.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
                        No hay archivos. Arrastra algo arriba para empezar.
                    </div>
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                            {media.data.map((item) => (
                                <MediaCard key={item.id} item={item} />
                            ))}
                        </div>

                        {media.last_page > 1 && (
                            <div className="flex items-center justify-between text-sm text-muted-foreground">
                                <span>
                                    Mostrando {media.from}–{media.to} de{' '}
                                    {media.total}
                                </span>
                                <div className="flex gap-1">
                                    {media.links
                                        .filter(
                                            (l) =>
                                                l.label === 'Anterior' ||
                                                l.label === 'Siguiente' ||
                                                !l.label.includes('...'),
                                        )
                                        .map((link, idx) => (
                                            <Button
                                                key={`${link.label}-${idx}`}
                                                asChild
                                                size="sm"
                                                variant={
                                                    link.active
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                disabled={!link.url}
                                            >
                                                <Link
                                                    href={link.url ?? '#'}
                                                    preserveState
                                                >
                                                    <span
                                                        dangerouslySetInnerHTML={{
                                                            __html: link.label,
                                                        }}
                                                    />
                                                </Link>
                                            </Button>
                                        ))}
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

MediaIndex.layout = {
    breadcrumbs: [
        {
            title: 'Medios',
            href: mediaIndex().url,
        },
    ],
};
