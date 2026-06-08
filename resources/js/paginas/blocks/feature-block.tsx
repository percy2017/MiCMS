import type { ComponentConfig } from '@puckeditor/core';
import { MediaPickerField } from './media-picker-field';

type FeatureBlockProps = {
    icon_src: string;
    icon_alt: string;
    title: string;
    description: string;
    align: 'left' | 'center';
};

const alignClass: Record<string, string> = {
    left: 'text-left items-start',
    center: 'text-center items-center',
};

export const FeatureBlock: ComponentConfig<
    FeatureBlockProps
> = {
    label: 'Característica',
    fields: {
        icon_src: {
            type: 'custom',
            render: MediaPickerField,
        },
        icon_alt: { type: 'text' },
        title: { type: 'text' },
        description: { type: 'textarea' },
        align: {
            type: 'radio',
            options: [
                { label: 'Izquierda', value: 'left' },
                { label: 'Centro', value: 'center' },
            ],
        },
    },
    defaultProps: {
        icon_src: '',
        icon_alt: '',
        title: 'Característica',
        description: 'Descripción de la característica',
        align: 'center',
    },
    render: ({
        icon_src,
        icon_alt,
        title,
        description,
        align,
    }) => {
        const alignVal = align ?? 'center';

        return (
            <div
                className={`flex flex-col gap-3 ${alignClass[alignVal]}`}
            >
                {icon_src ? (
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <img
                            src={icon_src}
                            alt={icon_alt || title}
                            className="h-6 w-6 object-contain"
                        />
                    </div>
                ) : null}

                <h3 className="text-lg font-semibold">{title}</h3>

                {description ? (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
        );
    },
};

export default FeatureBlock;