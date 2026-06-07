import { Link } from '@inertiajs/react';
import { FileIcon, FileTextIcon, MusicIcon, VideoIcon } from 'lucide-react';
import { edit as mediaEdit } from '@/routes/admin/media';

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
    created_at_diff: string;
};

type MediaCardProps = {
    item: MediaItem;
};

export function MediaCard({ item }: MediaCardProps) {
    return (
        <Link
            href={mediaEdit(item.id)}
            className="group flex flex-col overflow-hidden rounded-lg border border-sidebar-border/70 bg-card transition hover:border-primary/50 hover:shadow-sm"
        >
            <div className="flex aspect-square items-center justify-center overflow-hidden bg-muted">
                {item.is_image ? (
                    <img
                        src={item.url}
                        alt={item.title ?? item.name}
                        className="h-full w-full object-cover transition group-hover:scale-105"
                        loading="lazy"
                    />
                ) : item.is_video ? (
                    <VideoIcon className="size-12 text-muted-foreground" />
                ) : item.is_audio ? (
                    <MusicIcon className="size-12 text-muted-foreground" />
                ) : item.mime_type === 'application/pdf' ||
                  item.mime_type.startsWith('text/') ? (
                    <FileTextIcon className="size-12 text-muted-foreground" />
                ) : (
                    <FileIcon className="size-12 text-muted-foreground" />
                )}
            </div>
            <div className="flex flex-col gap-1 p-3 text-sm">
                <p
                    className="truncate font-medium"
                    title={item.title ?? item.name}
                >
                    {item.title ?? item.name}
                </p>
                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span className="truncate">{item.mime_type}</span>
                    <span>{item.human_size}</span>
                </div>
                <p className="text-xs text-muted-foreground">
                    {item.created_at_diff}
                </p>
            </div>
        </Link>
    );
}
