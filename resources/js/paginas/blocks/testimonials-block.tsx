import type { ComponentConfig } from '@puckeditor/core';
import { Star } from 'lucide-react';
import { MediaPickerField } from './media-picker-field';

type TestimonialsBlockProps = {
    avatar_src: string;
    avatar_alt: string;
    name: string;
    role: string;
    quote: string;
    stars: number;
};

export const TestimonialsBlock: ComponentConfig<
    TestimonialsBlockProps
> = {
    label: 'Testimonio',
    fields: {
        avatar_src: {
            type: 'custom',
            render: MediaPickerField,
        },
        avatar_alt: { type: 'text' },
        name: { type: 'text' },
        role: { type: 'text' },
        quote: { type: 'textarea' },
        stars: {
            type: 'select',
            options: [
                { label: '0 estrellas', value: 0 },
                { label: '1 estrella', value: 1 },
                { label: '2 estrellas', value: 2 },
                { label: '3 estrellas', value: 3 },
                { label: '4 estrellas', value: 4 },
                { label: '5 estrellas', value: 5 },
            ],
        },
    },
    defaultProps: {
        avatar_src: '',
        avatar_alt: '',
        name: 'Nombre',
        role: 'Cargo, Empresa',
        quote: 'Excelente servicio, completamente recomendado.',
        stars: 5,
    },
    render: ({
        avatar_src,
        avatar_alt,
        name,
        role,
        quote,
        stars,
    }) => {
        const starCount = Number(stars) || 0;

        return (
            <div className="flex flex-col gap-4 rounded-xl border border-border bg-background p-6 shadow-sm">
                {quote ? (
                    <blockquote className="text-sm leading-relaxed text-foreground/90">
                        &ldquo;{quote}&rdquo;
                    </blockquote>
                ) : null}

                {starCount > 0 ? (
                    <div className="flex gap-0.5">
                        {Array.from({ length: starCount }).map(
                            (_, i) => (
                                <Star
                                    key={i}
                                    className="size-4 fill-yellow-400 text-yellow-400"
                                />
                            ),
                        )}
                    </div>
                ) : null}

                <div className="flex items-center gap-3">
                    {avatar_src ? (
                        <div className="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-muted">
                            <img
                                src={avatar_src}
                                alt={avatar_alt || name}
                                className="h-full w-full object-cover"
                            />
                        </div>
                    ) : (
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-medium text-muted-foreground">
                            {name?.charAt(0)?.toUpperCase() || '?'}
                        </div>
                    )}
                    <div>
                        <p className="text-sm font-medium">{name}</p>
                        {role ? (
                            <p className="text-xs text-muted-foreground">
                                {role}
                            </p>
                        ) : null}
                    </div>
                </div>
            </div>
        );
    },
};

export default TestimonialsBlock;