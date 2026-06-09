import type { ComponentConfig } from '@puckeditor/core';
import DOMPurify from 'dompurify';

type HtmlBlockProps = {
    content: string;
};

export const HtmlBlock: ComponentConfig<HtmlBlockProps> = {
    label: 'HTML',
    fields: {
        content: { type: 'textarea' },
    },
    defaultProps: {
        content: '<p>HTML personalizado</p>',
    },
    render: ({ content }) => {
        const safe = DOMPurify.sanitize(content, {
            ALLOWED_TAGS: [
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
                'a', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre',
                'img', 'figure', 'figcaption', 'hr',
                'div', 'span', 'table', 'thead', 'tbody', 'tr', 'td', 'th',
            ],
            ALLOWED_ATTR: ['href', 'title', 'target', 'rel', 'src', 'alt', 'class', 'style', 'width', 'height'],
            ALLOW_DATA_ATTR: false,
        });

        return (
            <div
                className="prose dark:prose-invert max-w-none"
                dangerouslySetInnerHTML={{ __html: safe }}
            />
        );
    },
};
