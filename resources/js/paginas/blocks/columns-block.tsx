import type { ComponentConfig } from '@puckeditor/core';

type ColumnsBlockProps = {
    columns: number;
    gap: 'sm' | 'md' | 'lg';
};

const gridClass: Record<number, string> = {
    2: 'grid-cols-1 md:grid-cols-2',
    3: 'grid-cols-1 md:grid-cols-3',
    4: 'grid-cols-2 md:grid-cols-4',
};

const gapClass: Record<string, string> = {
    sm: 'gap-2',
    md: 'gap-4',
    lg: 'gap-8',
};

export const ColumnsBlock: ComponentConfig<ColumnsBlockProps> = {
    label: 'Columnas',
    fields: {
        columns: {
            type: 'radio',
            options: [
                { label: '2 columnas', value: 2 },
                { label: '3 columnas', value: 3 },
                { label: '4 columnas', value: 4 },
            ],
        },
        gap: {
            type: 'radio',
            options: [
                { label: 'Pequeño', value: 'sm' },
                { label: 'Mediano', value: 'md' },
                { label: 'Grande', value: 'lg' },
            ],
        },
    },
    defaultProps: {
        columns: 2,
        gap: 'md',
    },
    render: ({ columns, gap }) => {
        const cols = Number(columns);

        return (
            <div
                className={`grid ${gridClass[cols] ?? gridClass[2]} ${gapClass[gap] ?? gapClass.md}`}
            >
                {Array.from({ length: cols }).map((_, i) => (
                    <div
                        key={i}
                        className="min-h-[80px] rounded-md border-2 border-dashed border-muted-foreground/30 p-4 text-center text-sm text-muted-foreground"
                    >
                        Columna {i + 1}
                    </div>
                ))}
            </div>
        );
    },
};
