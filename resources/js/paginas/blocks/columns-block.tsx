import type { ComponentConfig, Slot } from '@puckeditor/core';

type ColumnsProps = {
    columns: number;
    gap: 'sm' | 'md' | 'lg';
    column1: Slot;
    column2: Slot;
    column3: Slot;
    column4: Slot;
};

const gapSize: Record<string, number> = {
    sm: 8,
    md: 16,
    lg: 32,
};

const colLabel: Record<number, string> = {
    2: '1fr 1fr',
    3: '1fr 1fr 1fr',
    4: '1fr 1fr 1fr 1fr',
};

export const ColumnsBlock: ComponentConfig<ColumnsProps> = {
    label: 'Columnas',
    fields: {
        columns: {
            type: 'radio',
            options: [
                { label: '2', value: 2 },
                { label: '3', value: 3 },
                { label: '4', value: 4 },
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
        column1: { type: 'slot' },
        column2: { type: 'slot' },
        column3: { type: 'slot' },
        column4: { type: 'slot' },
    },
    defaultProps: {
        columns: 3,
        gap: 'md',
        column1: [],
        column2: [],
        column3: [],
        column4: [],
    },
    render: ({ columns, gap, column1: Col1, column2: Col2, column3: Col3, column4: Col4 }) => {
        const cols = Number(columns);
        const gapPx = gapSize[gap] ?? 16;
        const colsArray = [Col1, Col2, Col3, Col4].slice(0, cols);

        return (
            <div style={{ display: 'grid', gridTemplateColumns: colLabel[cols] ?? '1fr 1fr 1fr', gap: gapPx }}>
                {colsArray.map((Col, i) => (
                    <div key={i} style={{ minHeight: 80, padding: 8, border: '1px dashed hsl(var(--muted-foreground) / 0.3)', borderRadius: 6 }}>
                        <Col />
                    </div>
                ))}
            </div>
        );
    },
};