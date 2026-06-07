import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import {
    MediaMetadataForm,
    MediaPreview,
    MediaSidebar,
} from '@/pages/media/partials/media-preview';
import { edit as mediaEdit } from '@/routes/admin/media';

type MediaItem = {
    id: number;
    name: string;
    title: string | null;
    alt_text: string | null;
    caption: string | null;
    description: string | null;
    mime_type: string;
    human_size: string;
    width: number | null;
    height: number | null;
    url: string;
    is_image: boolean;
    is_video: boolean;
    is_audio: boolean;
    created_at: string;
    created_at_diff: string;
};

type PageProps = {
    media: MediaItem;
};

export default function MediaEdit({ media }: PageProps) {
    return (
        <>
            <Head title={media.title ?? media.name} />

            <div className="space-y-6 p-4">
                {/* <div>
                    <Heading
                        title={media.title ?? media.name}
                        description={media.mime_type}
                    />
                </div> */}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <MediaPreview media={media} />
                        <div className="rounded-lg border p-6">
                            <Heading
                                variant="small"
                                title="Metadatos"
                                description="Información que ayuda a identificar y describir el archivo"
                            />
                            <div className="mt-4">
                                <MediaMetadataForm media={media} />
                            </div>
                        </div>
                    </div>

                    <div className="lg:col-span-1">
                        <MediaSidebar media={media} />
                    </div>
                </div>
            </div>
        </>
    );
}

MediaEdit.layout = {
    breadcrumbs: [
        {
            title: 'Medios',
            href: mediaEdit({ media: 0 }).url.replace('/0/edit', ''),
        },
        {
            title: 'Editar',
        },
    ],
};
