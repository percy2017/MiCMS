import type { ComponentConfig, Slot } from '@puckeditor/core';

type GridBlockProps = {
    columns: number;
    gap: string;
    items: Slot;
};

const gridMap: Record<number, string> = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 md:grid-cols-2',
    3: 'grid-cols-1 md:grid-cols-3',
    4: 'grid-cols-2 md:grid-cols-4',
    6: 'grid-cols-2 sm:grid-cols-3 md:grid-cols-6',
};

const gapMap: Record<string, string> = {
    sm: 'gap-2',
    md: 'gap-4',
    lg: 'gap-8',
};

export const GridBlock: ComponentConfig<GridBlockProps> = {
    label: 'Cuadrícula',
    fields: {
        columns: {
            type: 'radio',
            options: [
                { label: '1 columna', value: 1 },
                { label: '2 columnas', value: 2 },
                { label: '3 columnas', value: 3 },
                { label: '4 columnas', value: 4 },
                { label: '6 columnas', value: 6 },
            ],
        },
        gap: {
            type: 'radio',
            options: [
                { label: 'Pequeño', value: 'sm' },
                { label: 'Mediano', value: 'md' },
                { label: 'Grande', value: 'lg' },
            ],
        },
        items: {
            type: 'slot',
            allow: [
                'HeadingBlock', 'TextBlock', 'ImageBlock', 'VideoBlock',
                'ButtonBlock', 'ColumnsBlock', 'SpacerBlock', 'DividerBlock',
                'HtmlBlock', 'GridBlock', 'PricingBlock', 'FeatureBlock',
                'TestimonialsBlock',
            ],
        },
    },
    defaultProps: {
        columns: 3,
        gap: 'md',
        items: [],
    },
    render: ({ columns, gap, items }) => {
        const cols = Number(columns);
        return items({
            className: `grid ${gridMap[cols] ?? gridMap[3]} ${gapMap[gap] ?? gapMap.md}`,
            minEmptyHeight: 120,
        });
    },
};