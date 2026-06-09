import type { ComponentConfig } from '@puckeditor/core';
import DOMPurify from 'dompurify';
import {
    SPACING_FIELDS,
    spacingClassName,
    type SpacingProps,
} from '@/paginas/blocks/spacing';

type TextBlockProps = {
    content: string;
    align: 'left' | 'center' | 'right' | 'justify';
} & SpacingProps;

const alignClass: Record<string, string> = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
    justify: 'text-justify',
};

export const TextBlock: ComponentConfig<TextBlockProps> = {
    label: 'Texto',
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
        ...SPACING_FIELDS,
    },
    defaultProps: {
        content: '<p>Escribe tu contenido aquí.</p>',
        align: 'left',
        padding: 'md',
        marginBottom: 'md',
        backgroundColor: 'transparent',
        borderRadius: 'none',
    },
    render: ({ content, align, ...spacing }) => {
        const text = typeof content === 'string' ? content : '';
        const safe = DOMPurify.sanitize(text, {
            ALLOWED_TAGS: [
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
                'a', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre',
                'img', 'figure', 'figcaption', 'hr',
                'div', 'span', 'table', 'thead', 'tbody', 'tr', 'td', 'th',
            ],
            ALLOWED_ATTR: ['href', 'title', 'target', 'rel', 'src', 'alt', 'class', 'style'],
        });

        return (
            <div
                className={`prose dark:prose-invert max-w-none ${alignClass[align] ?? alignClass.left} ${spacingClassName(spacing as SpacingProps)}`}
                dangerouslySetInnerHTML={{ __html: safe }}
            />
        );
    },
};
