import type { ComponentConfig } from '@puckeditor/core';
import type { SharedBlockProps } from './shared-block-fields';
import { sharedFields, sharedDefaultProps, BlockWrapper } from './shared-block-fields';

type TextBlockProps = {
    content: string;
    align: 'left' | 'center' | 'right' | 'justify';
};

const alignClass: Record<string, string> = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
    justify: 'text-justify',
};

export const TextBlock: ComponentConfig<TextBlockProps & SharedBlockProps> = {
    fields: {
        content: { type: 'textarea' },
        align: {
            type: 'radio',
            options: [
                { label: 'Izquierda', value: 'left' },
                { label: 'Centro', value: 'center' },
                { label: 'Derecha', value: 'right' },
                { label: 'Justificado', value: 'justify' },
            ],
        },
        ...sharedFields,
    },
    defaultProps: {
        content: '<p>Escribe tu contenido aquí. Puedes usar formato HTML básico.</p>',
        align: 'left',
        ...sharedDefaultProps,
    },
    render: ({ content, align, ...shared }) => (
        <BlockWrapper {...shared}>
            <div
                className={`prose dark:prose-invert max-w-none ${alignClass[align] ?? alignClass.left}`}
                dangerouslySetInnerHTML={{ __html: content }}
            />
        </BlockWrapper>
    ),
};
