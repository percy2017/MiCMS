import type { ComponentConfig } from '@puckeditor/core';
import { Check, X } from 'lucide-react';
import type { SharedBlockProps } from './shared-block-fields';
import { sharedFields, sharedDefaultProps, BlockWrapper } from './shared-block-fields';

type PricingBlockProps = {
    plan_name: string;
    price: string;
    currency: string;
    period: string;
    description: string;
    features: string;
    button_text: string;
    button_url: string;
    button_variant: 'primary' | 'secondary' | 'outline';
    highlighted: boolean;
    popular_badge: string;
};

const variantClass: Record<string, string> = {
    primary:
        'bg-primary text-primary-foreground hover:bg-primary/90',
    secondary:
        'bg-secondary text-secondary-foreground hover:bg-secondary/80',
    outline:
        'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
};

export const PricingBlock: ComponentConfig<
    PricingBlockProps & SharedBlockProps
> = {
    label: 'Plan de precio',
    fields: {
        plan_name: { type: 'text' },
        price: { type: 'text' },
        currency: { type: 'text' },
        period: { type: 'text' },
        description: { type: 'text' },
        features: { type: 'textarea' },
        button_text: { type: 'text' },
        button_url: { type: 'text' },
        button_variant: {
            type: 'radio',
            options: [
                { label: 'Primario', value: 'primary' },
                { label: 'Secundario', value: 'secondary' },
                { label: 'Contorno', value: 'outline' },
            ],
        },
        highlighted: {
            type: 'radio',
            options: [
                { label: 'No', value: false },
                { label: 'Sí', value: true },
            ],
        },
        popular_badge: { type: 'text' },
        ...sharedFields,
    },
    defaultProps: {
        plan_name: 'Plan',
        price: '9.99',
        currency: '$',
        period: '/mes',
        description: '',
        features: '+ 1 sitio web\n+ 10 GB SSD\n- SSL dedicado',
        button_text: 'Contratar',
        button_url: '#',
        button_variant: 'primary',
        highlighted: false,
        popular_badge: 'Más popular',
        ...sharedDefaultProps,
    },
    render: ({
        plan_name,
        price,
        currency,
        period,
        description,
        features,
        button_text,
        button_url,
        button_variant,
        highlighted,
        popular_badge,
        ...shared
    }) => {
        const featureLines = (features ?? '')
            .split('\n')
            .filter((line) => line.trim().length > 0);
        const variant = button_variant ?? 'primary';

        return (
            <BlockWrapper {...shared}>
                <div
                    className={`relative flex flex-col rounded-xl border p-6 ${
                        highlighted
                            ? 'border-primary shadow-lg'
                            : 'border-border shadow-sm'
                    }`}
                >
                    {highlighted && popular_badge ? (
                        <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-primary px-4 py-1 text-xs font-semibold text-primary-foreground">
                            {popular_badge}
                        </span>
                    ) : null}

                    <div className="mb-4 text-center">
                        <h3 className="text-lg font-semibold">
                            {plan_name}
                        </h3>
                        {description ? (
                            <p className="mt-1 text-sm text-muted-foreground">
                                {description}
                            </p>
                        ) : null}
                    </div>

                    <div className="mb-6 text-center">
                        <span className="text-3xl font-bold">
                            {currency}
                            {price}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {period}
                        </span>
                    </div>

                    <ul className="mb-6 flex flex-col gap-2">
                        {featureLines.map((line, i) => {
                            const isCheck = line.startsWith('+');
                            const isCross = line.startsWith('-');
                            const text = line.replace(/^[+\-]\s*/, '');

                            return (
                                <li
                                    key={i}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    {isCheck ? (
                                        <Check className="size-4 shrink-0 text-green-500" />
                                    ) : isCross ? (
                                        <X className="size-4 shrink-0 text-muted-foreground" />
                                    ) : (
                                        <span className="size-4 shrink-0" />
                                    )}
                                    {text}
                                </li>
                            );
                        })}
                    </ul>

                    <div className="mt-auto">
                        <a
                            href={button_url}
                            className={
                                'inline-flex h-10 w-full items-center justify-center rounded-md px-4 text-sm font-medium transition-colors ' +
                                variantClass[variant]
                            }
                        >
                            {button_text}
                        </a>
                    </div>
                </div>
            </BlockWrapper>
        );
    },
};

export default PricingBlock;
