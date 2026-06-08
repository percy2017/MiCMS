import type { ComponentConfig } from '@puckeditor/core';

type SpacerBlockProps = {
    height: number;
};

export const SpacerBlock: ComponentConfig<SpacerBlockProps> = {
    label: 'Espaciador',
    fields: {
        height: {
            type: 'number',
            min: 8,
            max: 256,
        },
    },
    defaultProps: {
        height: 48,
    },
    render: ({ height }) => (
        <div style={{ height: `${height}px` }} aria-hidden="true" />
    ),
};