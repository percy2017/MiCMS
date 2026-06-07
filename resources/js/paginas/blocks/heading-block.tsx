import type { ComponentConfig } from '@puckeditor/core';
import type { SharedBlockProps } from './shared-block-fields';
import { sharedFields, sharedDefaultProps, BlockWrapper } from './shared-block-fields';

type HeadingBlockProps = {
    children: string;
    level: 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';
    align: 'left' | 'center' | 'right';
};

const sizeClass: Record<string, string> = {
    h1: 'text-4xl font-bold md:text-5xl',
    h2: 'text-3xl font-bold md:text-4xl',
    h3: 'text-2xl font-semibold md:text-3xl',
    h4: 'text-xl font-semibold md:text-2xl',
    h5: 'text-lg font-semibold',
    h6: 'text-base font-semibold',
};

const alignClass: Record<string, string> = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
};

export const HeadingBlock: ComponentConfig<HeadingBlockProps & SharedBlockProps> = {
    fields: {
        children: { type: 'text' },
        level: {
            type: 'select',
            options: [
                { label: 'H1', value: 'h1' },
                { label: 'H2', value: 'h2' },
                { label: 'H3', value: 'h3' },
                { label: 'H4', value: 'h4' },
                { label: 'H5', value: 'h5' },
                { label: 'H6', value: 'h6' },
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
        ...sharedFields,
    },
    defaultProps: {
        children: 'Título',
        level: 'h2',
        align: 'left',
        ...sharedDefaultProps,
    },
    render: ({ children, level, align, ...shared }) => {
        const Tag = level;

        return (
            <BlockWrapper {...shared}>
                <Tag className={`${sizeClass[level] ?? sizeClass.h2} ${alignClass[align] ?? alignClass.left}`}>
                    {children}
                </Tag>
            </BlockWrapper>
        );
    },
};
