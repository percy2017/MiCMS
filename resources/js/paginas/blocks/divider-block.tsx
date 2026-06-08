import type { ComponentConfig } from '@puckeditor/core';

type DividerBlockProps = {
    style: 'solid' | 'dashed' | 'dotted';
};

export const DividerBlock: ComponentConfig<DividerBlockProps> = {
    label: 'Divisor',
    fields: {
        style: {
            type: 'radio',
            options: [
                { label: 'Sólido', value: 'solid' },
                { label: 'Punteado', value: 'dashed' },
                { label: 'Puntos', value: 'dotted' },
            ],
        },
    },
    defaultProps: {
        style: 'solid',
    },
    render: ({ style }) => (
        <hr
            className="my-4 border-0 border-t border-border"
            style={{ borderTopStyle: style }}
        />
    ),
};