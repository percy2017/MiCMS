import type { ComponentConfig } from '@puckeditor/core';
import type { SharedBlockProps } from './shared-block-fields';
import { sharedFields, sharedDefaultProps, BlockWrapper } from './shared-block-fields';
import { MediaPickerField } from '@/paginas/blocks/media-picker-field';
import { cn } from '@/lib/utils';

type ImageBlockProps = {
    src: string;
    alt: string;
    caption: string;
    width: 'full' | 'large' | 'medium' | 'small';
    align: 'left' | 'center' | 'right';
    rounded: 'none' | 'sm' | 'md' | 'lg' | 'xl' | 'full';
    shadow: 'none' | 'sm' | 'md' | 'lg' | 'xl';
    object_fit: 'cover' | 'contain' | 'fill' | 'none' | 'scale-down';
    aspect_ratio: 'auto' | 'square' | 'video' | 'standard' | 'portrait' | 'story';
    max_width: string;
    link_url: string;
    link_target: '_self' | '_blank';
};

const widthClass: Record<string, string> = {
    full: 'w-full',
    large: 'w-full max-w-3xl',
    medium: 'w-full max-w-xl',
    small: 'w-full max-w-sm',
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

const shadowClass: Record<string, string> = {
    none: '',
    sm: 'shadow-sm',
    md: 'shadow-md',
    lg: 'shadow-lg',
    xl: 'shadow-xl',
};

const objectFitClass: Record<string, string> = {
    cover: 'object-cover',
    contain: 'object-contain',
    fill: 'object-fill',
    none: 'object-none',
    'scale-down': 'object-scale-down',
};

const aspectClass: Record<string, string> = {
    auto: '',
    square: 'aspect-square',
    video: 'aspect-video',
    standard: 'aspect-[4/3]',
    portrait: 'aspect-[3/4]',
    story: 'aspect-[9/16]',
};

const isAspectFixed = (ar: string): boolean => ar !== 'auto';

export const ImageBlock: ComponentConfig<ImageBlockProps & SharedBlockProps> = {
    label: 'Imagen',
    fields: {
        src: {
            type: 'custom',
            label: 'Imagen',
            render: MediaPickerField,
        },
        alt: {
            type: 'text',
            label: 'Texto alternativo',
        },
        caption: {
            type: 'text',
            label: 'Pie de foto',
        },
        link_url: {
            type: 'text',
            label: 'Enlace (URL)',
        },
        link_target: {
            type: 'radio',
            label: 'Abrir enlace en',
            options: [
                { label: 'Misma pestaña', value: '_self' },
                { label: 'Nueva pestaña', value: '_blank' },
            ],
        },
        width: {
            type: 'radio',
            label: 'Ancho',
            options: [
                { label: 'Completa', value: 'full' },
                { label: 'Grande', value: 'large' },
                { label: 'Mediana', value: 'medium' },
                { label: 'Pequeña', value: 'small' },
            ],
        },
        align: {
            type: 'radio',
            label: 'Alineación',
            options: [
                { label: 'Izquierda', value: 'left' },
                { label: 'Centro', value: 'center' },
                { label: 'Derecha', value: 'right' },
            ],
        },
        max_width: {
            type: 'text',
            label: 'Ancho máximo (px, %, etc.)',
        },
        aspect_ratio: {
            type: 'radio',
            label: 'Relación de aspecto',
            options: [
                { label: 'Auto (natural)', value: 'auto' },
                { label: 'Cuadrada (1:1)', value: 'square' },
                { label: 'Video (16:9)', value: 'video' },
                { label: 'Estándar (4:3)', value: 'standard' },
                { label: 'Retrato (3:4)', value: 'portrait' },
                { label: 'Historia (9:16)', value: 'story' },
            ],
        },
        object_fit: {
            type: 'radio',
            label: 'Ajuste de imagen',
            options: [
                { label: 'Cubrir (recortar)', value: 'cover' },
                { label: 'Contener (ajustar)', value: 'contain' },
                { label: 'Rellenar', value: 'fill' },
                { label: 'Ninguno', value: 'none' },
                { label: 'Reducir si necesario', value: 'scale-down' },
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
        shadow: {
            type: 'radio',
            label: 'Sombra',
            options: [
                { label: 'Ninguna', value: 'none' },
                { label: 'Pequeña', value: 'sm' },
                { label: 'Mediana', value: 'md' },
                { label: 'Grande', value: 'lg' },
                { label: 'Extra grande', value: 'xl' },
            ],
        },
        ...sharedFields,
    },
    defaultProps: {
        src: '',
        alt: '',
        caption: '',
        width: 'full',
        align: 'center',
        rounded: 'lg',
        shadow: 'none',
        object_fit: 'cover',
        aspect_ratio: 'auto',
        max_width: '',
        link_url: '',
        link_target: '_self',
        ...sharedDefaultProps,
    },
    render: ({
        src,
        alt,
        caption,
        width,
        align,
        rounded,
        shadow,
        object_fit,
        aspect_ratio,
        max_width,
        link_url,
        link_target,
        ...shared
    }) => {
        if (!src) {
            return (
                <div className="flex h-32 items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/30 bg-muted/30 text-sm text-muted-foreground">
                    Selecciona una imagen desde Medios
                </div>
            );
        }

        const fixedAspect = isAspectFixed(aspect_ratio);
        const maxWidthStyle = max_width ? { maxWidth: max_width } : undefined;

        const imgClass = cn(
            fixedAspect ? 'h-full w-full' : 'h-auto w-full',
            roundedClass[rounded] ?? 'rounded-none',
            objectFitClass[object_fit] ?? 'object-cover',
        );

        const innerClass = cn(
            widthClass[width] ?? widthClass.full,
            fixedAspect ? aspectClass[aspect_ratio] : '',
            shadowClass[shadow] ?? '',
        );

        const figureClass = cn('flex', alignClass[align] ?? 'justify-center');

        const imgElement = (
            <img src={src} alt={alt} className={imgClass} loading="lazy" />
        );

        const linkedElement = link_url ? (
            <a
                href={link_url}
                target={link_target}
                rel={
                    link_target === '_blank'
                        ? 'noopener noreferrer'
                        : undefined
                }
                className="block"
            >
                {imgElement}
            </a>
        ) : (
            imgElement
        );

        return (
            <BlockWrapper {...shared}>
                <figure className={figureClass}>
                    <div className={innerClass} style={maxWidthStyle}>
                        {linkedElement}
                        {caption ? (
                            <figcaption className="mt-2 text-center text-sm text-muted-foreground">
                                {caption}
                            </figcaption>
                        ) : null}
                    </div>
                </figure>
            </BlockWrapper>
        );
    },
};
