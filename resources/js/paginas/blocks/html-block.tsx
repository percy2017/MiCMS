import type { ComponentConfig } from '@puckeditor/core';

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
    render: ({ content }) => (
        <div
            className="prose dark:prose-invert max-w-none"
            dangerouslySetInnerHTML={{ __html: content }}
        />
    ),
};
