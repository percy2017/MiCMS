import type { ComponentConfig } from '@puckeditor/core';
import type { SharedBlockProps } from './shared-block-fields';
import { sharedFields, sharedDefaultProps, BlockWrapper } from './shared-block-fields';

type DividerBlockProps = {
    style: 'solid' | 'dashed' | 'dotted';
};

export const DividerBlock: ComponentConfig<DividerBlockProps & SharedBlockProps> = {
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
        ...sharedFields,
    },
    defaultProps: {
        style: 'solid',
        ...sharedDefaultProps,
    },
    render: ({ style, ...shared }) => (
        <BlockWrapper {...shared}>
            <hr
                className="my-4 border-0 border-t border-border"
                style={{ borderTopStyle: style }}
            />
        </BlockWrapper>
    ),
};
