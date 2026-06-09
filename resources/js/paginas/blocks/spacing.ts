const SPACING_OPTIONS = [
    { label: 'Ninguno', value: 'none' },
    { label: 'Pequeño', value: 'sm' },
    { label: 'Mediano', value: 'md' },
    { label: 'Grande', value: 'lg' },
    { label: 'Extra grande', value: 'xl' },
];

const PADDING_CLASS: Record<string, string> = {
    none: 'p-0',
    sm: 'p-2',
    md: 'p-4',
    lg: 'p-6',
    xl: 'p-10',
};

const MARGIN_BOTTOM_CLASS: Record<string, string> = {
    none: 'mb-0',
    sm: 'mb-2',
    md: 'mb-4',
    lg: 'mb-6',
    xl: 'mb-10',
};

const BG_CLASS: Record<string, string> = {
    transparent: '',
    muted: 'bg-muted',
    card: 'bg-card',
    primary: 'bg-primary text-primary-foreground',
    accent: 'bg-accent text-accent-foreground',
};

const RADIUS_CLASS: Record<string, string> = {
    none: 'rounded-none',
    sm: 'rounded-sm',
    md: 'rounded-md',
    lg: 'rounded-lg',
    xl: 'rounded-xl',
    full: 'rounded-full',
};

export type SpacingProps = {
    padding?: keyof typeof PADDING_CLASS;
    marginBottom?: keyof typeof MARGIN_BOTTOM_CLASS;
    backgroundColor?: keyof typeof BG_CLASS;
    borderRadius?: keyof typeof RADIUS_CLASS;
};

export const SPACING_FIELDS = {
    padding: {
        type: 'select',
        label: 'Padding',
        options: SPACING_OPTIONS,
    },
    marginBottom: {
        type: 'select',
        label: 'Margen inferior',
        options: SPACING_OPTIONS,
    },
    backgroundColor: {
        type: 'select',
        label: 'Fondo',
        options: [
            { label: 'Transparente', value: 'transparent' },
            { label: 'Muted', value: 'muted' },
            { label: 'Tarjeta', value: 'card' },
            { label: 'Primario', value: 'primary' },
            { label: 'Acento', value: 'accent' },
        ],
    },
    borderRadius: {
        type: 'select',
        label: 'Esquinas',
        options: SPACING_OPTIONS,
    },
} as const;

export function spacingClassName(props: SpacingProps): string {
    return [
        PADDING_CLASS[props.padding ?? 'md'] ?? PADDING_CLASS.md,
        MARGIN_BOTTOM_CLASS[props.marginBottom ?? 'md'] ?? MARGIN_BOTTOM_CLASS.md,
        BG_CLASS[props.backgroundColor ?? 'transparent'] ?? '',
        RADIUS_CLASS[props.borderRadius ?? 'none'] ?? RADIUS_CLASS.none,
    ].join(' ');
}
