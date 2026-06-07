import type { ComponentConfig } from '@puckeditor/core';
import type { SharedBlockProps } from './shared-block-fields';
import { sharedFields, sharedDefaultProps, BlockWrapper } from './shared-block-fields';

type HtmlBlockProps = {
    content: string;
};

export const HtmlBlock: ComponentConfig<HtmlBlockProps & SharedBlockProps> = {
    label: 'HTML',
    fields: {
        content: { type: 'textarea' },
        ...sharedFields,
    },
    defaultProps: {
        content: '<p>HTML personalizado</p>',
        ...sharedDefaultProps,
    },
    render: ({ content, ...shared }) => (
        <BlockWrapper {...shared}>
            <div
                className="prose dark:prose-invert max-w-none"
                dangerouslySetInnerHTML={{ __html: content }}
            />
        </BlockWrapper>
    ),
};
