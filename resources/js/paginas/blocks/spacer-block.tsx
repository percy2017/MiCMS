import type { ComponentConfig } from '@puckeditor/core';
import type { SharedBlockProps } from './shared-block-fields';
import { sharedFields, sharedDefaultProps, BlockWrapper } from './shared-block-fields';

type SpacerBlockProps = {
    height: number;
};

export const SpacerBlock: ComponentConfig<SpacerBlockProps & SharedBlockProps> = {
    label: 'Espaciador',
    fields: {
        height: {
            type: 'number',
            min: 8,
            max: 256,
        },
        ...sharedFields,
    },
    defaultProps: {
        height: 48,
        ...sharedDefaultProps,
    },
    render: ({ height, ...shared }) => (
        <BlockWrapper {...shared}>
            <div style={{ height: `${height}px` }} aria-hidden="true" />
        </BlockWrapper>
    ),
};
