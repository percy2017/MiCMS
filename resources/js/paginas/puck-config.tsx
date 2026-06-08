import type { Config, Data, Slot } from '@puckeditor/core';
import { ButtonBlock } from '@/paginas/blocks/button-block';
import { ColumnsBlock } from '@/paginas/blocks/columns-block';
import { GridBlock } from '@/paginas/blocks/grid-block';
import { DividerBlock } from '@/paginas/blocks/divider-block';
import { FeatureBlock } from '@/paginas/blocks/feature-block';
import { HeadingBlock } from '@/paginas/blocks/heading-block';
import { HtmlBlock } from '@/paginas/blocks/html-block';
import { ImageBlock } from '@/paginas/blocks/image-block';
import { PricingBlock } from '@/paginas/blocks/pricing-block';
import { SpacerBlock } from '@/paginas/blocks/spacer-block';
import { TestimonialsBlock } from '@/paginas/blocks/testimonials-block';
import { TextBlock } from '@/paginas/blocks/text-block';
import { VideoBlock } from '@/paginas/blocks/video-block';

export type PuckComponents = {
    ButtonBlock: { text: string; url: string; variant: 'primary' | 'secondary' | 'outline'; align: 'left' | 'center' | 'right' };
    ColumnsBlock: { columns: number; gap: 'sm' | 'md' | 'lg'; column1: Slot; column2: Slot; column3: Slot; column4: Slot };
    DividerBlock: { style: 'solid' | 'dashed' | 'dotted' };
    FeatureBlock: { title: string; description: string; align: 'left' | 'center' };
    GridBlock: { columns: number; gap: string; items: Slot };
    HeadingBlock: { children: string; level: 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6'; align: 'left' | 'center' | 'right' };
    HtmlBlock: { content: string };
    ImageBlock: { src: string; alt: string; caption: string; align: 'left' | 'center' | 'right'; rounded: 'none' | 'sm' | 'md' | 'lg' | 'xl' | 'full'; link_url: string };
    PricingBlock: { plan_name: string; price: string; features: string; button_text: string; button_url: string; highlighted: boolean };
    SpacerBlock: { height: number };
    TestimonialsBlock: { name: string; quote: string };
    TextBlock: { content: string; align: 'left' | 'center' | 'right' | 'justify' };
    VideoBlock: { src: string };
};

export type PuckRootProps = {
    title: string;
};

export const puckConfig: Config<PuckComponents, PuckRootProps> = {
    categories: {
        layout: {
            title: 'Layout',
            components: ['ColumnsBlock', 'GridBlock', 'SpacerBlock', 'DividerBlock'],
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
        blocks: {
            title: 'Bloques',
            components: ['PricingBlock', 'FeatureBlock', 'TestimonialsBlock'],
        },
    },
    components: {
        ButtonBlock,
        ColumnsBlock,
        FeatureBlock,
        GridBlock,
        HeadingBlock,
        HtmlBlock,
        ImageBlock,
        PricingBlock,
        SpacerBlock,
        TestimonialsBlock,
        TextBlock,
        VideoBlock,
        DividerBlock,
    },
    root: {
        fields: {
            title: { type: 'text' },
        },
        defaultProps: {
            title: 'Sin título',
        },
        render: ({ children }) => (
            <div className="mx-auto w-full max-w-4xl px-4 py-8">
                {children}
            </div>
        ),
    },
};

export const emptyPuckData: Data = {
    content: [],
    root: { props: { title: 'Sin título' } },
};
