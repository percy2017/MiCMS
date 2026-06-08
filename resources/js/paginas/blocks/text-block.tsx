import type { ComponentConfig } from '@puckeditor/core';

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

export const TextBlock: ComponentConfig<TextBlockProps> = {
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
    },
    defaultProps: {
        content: '<p>Escribe tu contenido aquí. Puedes usar formato HTML básico.</p>',
        align: 'left',
    },
    render: ({ content, align }) => (
        <div
            className={`prose dark:prose-invert max-w-none ${alignClass[align] ?? alignClass.left}`}
            dangerouslySetInnerHTML={{ __html: content }}
        />
    ),
};