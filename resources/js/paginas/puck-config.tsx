import type { Config, Data } from '@puckeditor/core';
import { ColumnsBlock } from '@/paginas/blocks/columns-block';
import { DividerBlock } from '@/paginas/blocks/divider-block';
import { HeadingBlock } from '@/paginas/blocks/heading-block';
import { HtmlBlock } from '@/paginas/blocks/html-block';
import { ImageBlock } from '@/paginas/blocks/image-block';
import { SpacerBlock } from '@/paginas/blocks/spacer-block';
import { TextBlock } from '@/paginas/blocks/text-block';
import { VideoBlock } from '@/paginas/blocks/video-block';
import Footer from '@/paginas/components/footer';
import Header from '@/paginas/components/header';

export type PuckComponents = {
    HeadingBlock: {
        children: string;
        level: 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';
        align: 'left' | 'center' | 'right';
    };
    TextBlock: {
        content: string;
        align: 'left' | 'center' | 'right' | 'justify';
    };
    ImageBlock: {
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
    VideoBlock: {
        src: string;
        autoplay: boolean;
        loop: boolean;
    };
    ButtonBlock: {
        text: string;
        url: string;
        variant: 'primary' | 'secondary' | 'outline';
        align: 'left' | 'center' | 'right';
    };
    ColumnsBlock: {
        columns: number;
        gap: 'sm' | 'md' | 'lg';
    };
    SpacerBlock: {
        height: number;
    };
    DividerBlock: {
        style: 'solid' | 'dashed' | 'dotted';
    };
    HtmlBlock: {
        content: string;
    };
};

export type PuckRootProps = {
    title: string;
};

export const puckConfig: Config<PuckComponents, PuckRootProps> = {
    categories: {
        layout: {
            title: 'Layout',
            components: ['ColumnsBlock', 'SpacerBlock', 'DividerBlock'],
        },
        typography: {
            title: 'Tipografía',
            components: ['HeadingBlock', 'TextBlock'],
        },
        media: {
            title: 'Medios',
            components: ['ImageBlock', 'VideoBlock'],
        },
        interactive: {
            title: 'Interactivo',
            components: ['ButtonBlock', 'HtmlBlock'],
        },
    },
    components: {
        HeadingBlock,
        TextBlock,
        ImageBlock,
        VideoBlock,
        ButtonBlock: {
            label: 'Botón',
            fields: {
                text: { type: 'text' },
                url: { type: 'text' },
                variant: {
                    type: 'radio',
                    options: [
                        { label: 'Primario', value: 'primary' },
                        { label: 'Secundario', value: 'secondary' },
                        { label: 'Contorno', value: 'outline' },
                    ],
                },
                align: {
                    type: 'radio',
                    options: [
                        { label: 'Izquierda', value: 'left' },
                        { label: 'Centro', value: 'center' },
                        { label: 'Derecha', value: 'right' },
                    ],
                },
            },
            defaultProps: {
                text: 'Haz clic aquí',
                url: '#',
                variant: 'primary',
                align: 'left',
            },
            render: ({ text, url, variant, align }) => {
                const variantClass =
                    variant === 'primary'
                        ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                        : variant === 'secondary'
                          ? 'bg-secondary text-secondary-foreground hover:bg-secondary/80'
                          : 'border border-input bg-background hover:bg-accent hover:text-accent-foreground';
                const alignClass =
                    align === 'center'
                        ? 'flex justify-center'
                        : align === 'right'
                          ? 'flex justify-end'
                          : 'flex justify-start';

                return (
                    <div className={alignClass}>
                        <a
                            href={url}
                            className={
                                'inline-flex h-9 items-center justify-center rounded-md px-4 text-sm font-medium transition-colors ' +
                                variantClass
                            }
                        >
                            {text}
                        </a>
                    </div>
                );
            },
        },
        ColumnsBlock,
        SpacerBlock,
        DividerBlock,
        HtmlBlock,
    },
    root: {
        fields: {
            title: { type: 'text' },
        },
        defaultProps: {
            title: 'Sin título',
        },
        render: ({ children }) => (
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <Header />
                <main className="flex-1">{children}</main>
                <Footer />
            </div>
        ),
    },
};

export const emptyPuckData: Data = {
    content: [],
    root: { props: { title: 'Sin título' } },
};
