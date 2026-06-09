import type { ComponentConfig } from '@puckeditor/core';
import { MediaPickerField } from '@/paginas/blocks/media-picker-field';
import { isSafeUrl } from '@/lib/safe-url';

type VideoBlockProps = {
    src: string;
    autoplay: boolean;
    loop: boolean;
};

export const VideoBlock: ComponentConfig<VideoBlockProps> = {
    label: 'Video',
    fields: {
        src: {
            type: 'custom',
            label: 'Video',
            render: MediaPickerField,
        },
        autoplay: {
            type: 'radio',
            options: [
                { label: 'No', value: false },
                { label: 'Sí', value: true },
            ],
        },
        loop: {
            type: 'radio',
            options: [
                { label: 'No', value: false },
                { label: 'Sí', value: true },
            ],
        },
    },
    defaultProps: {
        src: '',
        autoplay: false,
        loop: false,
    },
    render: ({ src, autoplay, loop }) => {
        if (!src) {
            return (
                <div className="flex h-32 items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/30 bg-muted/30 text-sm text-muted-foreground">
                    Selecciona un video desde Medios
                </div>
            );
        }

        const safe = isSafeUrl(src);

        if (!safe) {
            return (
                <div className="flex h-32 items-center justify-center rounded-lg border-2 border-dashed border-destructive/40 bg-destructive/10 text-sm text-destructive">
                    URL de video no permitida.
                </div>
            );
        }

        return (
            <video
                src={safe}
                controls
                autoPlay={autoplay}
                loop={loop}
                muted={autoplay}
                playsInline
                className="w-full rounded-lg"
            >
                Tu navegador no soporta el elemento video.
            </video>
        );
    },
};
