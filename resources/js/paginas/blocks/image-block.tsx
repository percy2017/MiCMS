import type { ComponentConfig } from '@puckeditor/core';
import { MediaPickerField } from '@/paginas/blocks/media-picker-field';
import { isSafeUrl } from '@/lib/safe-url';

type ImageBlockProps = {
    src: string;
    alt: string;
    caption: string;
    align: 'left' | 'center' | 'right';
    rounded: 'none' | 'sm' | 'md' | 'lg' | 'xl' | 'full';
    link_url: string;
};

const alignClass: Record<string, string> = {
    left: 'justify-start',
    center: 'justify-center',
    right: 'justify-end',
};

const roundedClass: Record<string, string> = {
    none: 'rounded-none',
    sm: 'rounded-sm',
    md: 'rounded-md',
    lg: 'rounded-lg',
    xl: 'rounded-xl',
    full: 'rounded-full',
};

export const ImageBlock: ComponentConfig<ImageBlockProps> = {
    label: 'Imagen',
    fields: {
        src: {
            type: 'custom',
            label: 'Imagen',
            render: MediaPickerField,
        },
        alt: { type: 'text', label: 'Texto alternativo' },
        caption: { type: 'text', label: 'Pie de foto' },
        align: {
            type: 'radio',
            label: 'Alineación',
            options: [
                { label: 'Izquierda', value: 'left' },
                { label: 'Centro', value: 'center' },
                { label: 'Derecha', value: 'right' },
            ],
        },
        rounded: {
            type: 'radio',
            label: 'Esquinas',
            options: [
                { label: 'Ninguna', value: 'none' },
                { label: 'Pequeña', value: 'sm' },
                { label: 'Mediana', value: 'md' },
                { label: 'Grande', value: 'lg' },
                { label: 'Extra grande', value: 'xl' },
                { label: 'Circular', value: 'full' },
            ],
        },
        link_url: { type: 'text', label: 'Enlace (URL)' },
    },
    defaultProps: {
        src: '',
        alt: '',
        caption: '',
        align: 'center',
        rounded: 'lg',
        link_url: '',
    },
    render: ({ src, alt, caption, align, rounded, link_url }) => {
        if (!src) {
            return (
                <div className="flex h-32 items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/30 bg-muted/30 text-sm text-muted-foreground">
                    Selecciona una imagen desde Medios
                </div>
            );
        }

        const safeLink = isSafeUrl(link_url);
        const isExternal = safeLink.startsWith('http://') || safeLink.startsWith('https://');

        const imgElement = (
            <img
                src={src}
                alt={alt}
                className={`w-full ${roundedClass[rounded] ?? 'rounded-none'}`}
                loading="lazy"
            />
        );

        const linkedElement = safeLink ? (
            <a
                href={safeLink}
                target={isExternal ? '_blank' : undefined}
                rel={isExternal ? 'noopener noreferrer' : undefined}
                className="block"
            >
                {imgElement}
            </a>
        ) : (
            imgElement
        );

        return (
            <figure className={`flex flex-col ${alignClass[align] ?? 'justify-center'}`}>
                <div className={align === 'center' ? 'mx-auto' : ''}>
                    {linkedElement}
                </div>
                {caption ? (
                    <figcaption className="mt-2 text-center text-sm text-muted-foreground">
                        {caption}
                    </figcaption>
                ) : null}
            </figure>
        );
    },
};
