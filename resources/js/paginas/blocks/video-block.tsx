import type { ComponentConfig } from '@puckeditor/core';
import { MediaPickerField } from '@/paginas/blocks/media-picker-field';

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

        return (
            <video
                src={src}
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