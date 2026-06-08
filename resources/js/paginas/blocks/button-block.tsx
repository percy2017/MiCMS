import type { ComponentConfig } from '@puckeditor/core';

type ButtonBlockProps = {
    text: string;
    url: string;
    variant: 'primary' | 'secondary' | 'outline';
    align: 'left' | 'center' | 'right';
};

const variantClass: Record<string, string> = {
    primary: 'bg-primary text-primary-foreground hover:bg-primary/90',
    secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
    outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
};

const alignClass: Record<string, string> = {
    left: 'flex justify-start',
    center: 'flex justify-center',
    right: 'flex justify-end',
};

export const ButtonBlock: ComponentConfig<ButtonBlockProps> = {
    label: 'Botón',
    fields: {
        text: { type: 'text' },
        url: { type: 'text' },
        variant: {
            type: 'radio',
            options: [
                { label: 'Primario', value: 'primary' },
                { label: 'Secundario', value: 'secondary' },
                { label: 'Contorno', value: 'outline' },
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
    },
    defaultProps: {
        text: 'Haz clic aquí',
        url: '#',
        variant: 'primary',
        align: 'left',
    },
    render: ({ text, url, variant, align }) => (
        <div className={alignClass[align] ?? alignClass.left}>
            <a
                href={url}
                className={
                    'inline-flex h-9 items-center justify-center rounded-md px-4 text-sm font-medium transition-colors ' +
                    (variantClass[variant] ?? variantClass.primary)
                }
            >
                {text}
            </a>
        </div>
    ),
};