import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type Spacing4 = {
    top: number;
    right: number;
    bottom: number;
    left: number;
};

export type Spacing2 = {
    top: number;
    bottom: number;
};

export type SharedBlockProps = {
    padding: Spacing4;
    margin: Spacing2;
    backgroundColor: string;
    borderRadius: string;
    boxShadow: string;
    maxWidth: string;
    hideOnMobile: boolean;
    hideOnDesktop: boolean;
    animation: string;
    animationDelay: number;
};

const bgColorMap: Record<string, string> = {
    transparent: 'bg-transparent',
    background: 'bg-background',
    card: 'bg-card',
    muted: 'bg-muted',
    primary: 'bg-primary',
    secondary: 'bg-secondary',
    accent: 'bg-accent',
};

const borderRadiusMap: Record<string, string> = {
    none: 'rounded-none',
    sm: 'rounded-sm',
    md: 'rounded-md',
    lg: 'rounded-lg',
    xl: 'rounded-xl',
    full: 'rounded-full',
};

const shadowMap: Record<string, string> = {
    none: '',
    sm: 'shadow-sm',
    md: 'shadow-md',
    lg: 'shadow-lg',
    xl: 'shadow-xl',
};

const animationMap: Record<string, string> = {
    none: '',
    fadeIn: 'animate-fade-in',
    slideUp: 'animate-slide-up',
    slideDown: 'animate-slide-down',
    zoomIn: 'animate-zoom-in',
};

function SpacingInput({ value, onChange, label }: {
    value: number;
    onChange: (v: number) => void;
    label: string;
}) {
    return (
        <div className="flex items-center gap-1.5">
            <span className="w-6 text-xs text-muted-foreground">{label}</span>
            <input
                type="number"
                min={0}
                max={96}
                value={value}
                onChange={(e) => onChange(Number(e.target.value))}
                className="h-7 w-full rounded border border-input bg-background px-2 text-xs"
            />
        </div>
    );
}

function PaddingField({ value, onChange }: {
    value?: Spacing4;
    onChange: (v: Spacing4) => void;
}) {
    const val = value ?? { top: 0, right: 0, bottom: 0, left: 0 };

    return (
        <div className="grid grid-cols-2 gap-x-3 gap-y-1">
            <SpacingInput
                label="Sup"
                value={val.top}
                onChange={(v) => onChange({ ...val, top: v })}
            />
            <SpacingInput
                label="Der"
                value={val.right}
                onChange={(v) => onChange({ ...val, right: v })}
            />
            <SpacingInput
                label="Inf"
                value={val.bottom}
                onChange={(v) => onChange({ ...val, bottom: v })}
            />
            <SpacingInput
                label="Izq"
                value={val.left}
                onChange={(v) => onChange({ ...val, left: v })}
            />
        </div>
    );
}

function MarginField({ value, onChange }: {
    value?: Spacing2;
    onChange: (v: Spacing2) => void;
}) {
    const val = value ?? { top: 0, bottom: 0 };

    return (
        <div className="grid grid-cols-2 gap-x-3 gap-y-1">
            <SpacingInput
                label="Sup"
                value={val.top}
                onChange={(v) => onChange({ ...val, top: v })}
            />
            <SpacingInput
                label="Inf"
                value={val.bottom}
                onChange={(v) => onChange({ ...val, bottom: v })}
            />
        </div>
    );
}

export const sharedFields = {
    padding: {
        type: 'custom' as const,
        label: 'Padding',
        render: PaddingField,
    },
    margin: {
        type: 'custom' as const,
        label: 'Margen',
        render: MarginField,
    },
    backgroundColor: {
        type: 'select' as const,
        label: 'Color de fondo',
        options: [
            { label: 'Transparente', value: 'transparent' },
            { label: 'Fondo', value: 'background' },
            { label: 'Tarjeta', value: 'card' },
            { label: 'Apagado', value: 'muted' },
            { label: 'Primario', value: 'primary' },
            { label: 'Secundario', value: 'secondary' },
            { label: 'Acento', value: 'accent' },
        ],
    },
    borderRadius: {
        type: 'radio' as const,
        label: 'Borde redondeado',
        options: [
            { label: 'Ninguno', value: 'none' },
            { label: 'Pequeño', value: 'sm' },
            { label: 'Mediano', value: 'md' },
            { label: 'Grande', value: 'lg' },
            { label: 'Extra grande', value: 'xl' },
            { label: 'Completo', value: 'full' },
        ],
    },
    boxShadow: {
        type: 'radio' as const,
        label: 'Sombra',
        options: [
            { label: 'Ninguna', value: 'none' },
            { label: 'Pequeña', value: 'sm' },
            { label: 'Mediana', value: 'md' },
            { label: 'Grande', value: 'lg' },
            { label: 'Extra grande', value: 'xl' },
        ],
    },
    maxWidth: {
        type: 'text' as const,
        label: 'Ancho máximo (ej. 640px, 50%)',
    },
    hideOnMobile: {
        type: 'radio' as const,
        label: 'Ocultar en móvil',
        options: [
            { label: 'No', value: false },
            { label: 'Sí', value: true },
        ],
    },
    hideOnDesktop: {
        type: 'radio' as const,
        label: 'Ocultar en desktop',
        options: [
            { label: 'No', value: false },
            { label: 'Sí', value: true },
        ],
    },
    animation: {
        type: 'select' as const,
        label: 'Animación al aparecer',
        options: [
            { label: 'Ninguna', value: 'none' },
            { label: 'Desvanecer', value: 'fadeIn' },
            { label: 'Deslizar arriba', value: 'slideUp' },
            { label: 'Deslizar abajo', value: 'slideDown' },
            { label: 'Zoom', value: 'zoomIn' },
        ],
    },
    animationDelay: {
        type: 'number' as const,
        label: 'Retardo animación (ms)',
        min: 0,
        max: 3000,
    },
};

export const sharedDefaultProps = {
    padding: { top: 32, right: 0, bottom: 32, left: 0 },
    margin: { top: 0, bottom: 0 },
    backgroundColor: 'transparent',
    borderRadius: 'none',
    boxShadow: 'none',
    maxWidth: '',
    hideOnMobile: false,
    hideOnDesktop: false,
    animation: 'none',
    animationDelay: 0,
};

export function BlockWrapper({
    children,
    padding,
    margin,
    backgroundColor,
    borderRadius,
    boxShadow,
    maxWidth,
    hideOnMobile,
    hideOnDesktop,
    animation,
    animationDelay,
}: SharedBlockProps & { children: ReactNode }) {
    const p = padding ?? sharedDefaultProps.padding;
    const m = margin ?? sharedDefaultProps.margin;

    const classes = cn(
        bgColorMap[backgroundColor] ?? 'bg-transparent',
        borderRadiusMap[borderRadius] ?? 'rounded-none',
        shadowMap[boxShadow] ?? '',
        animationMap[animation] ?? '',
        hideOnMobile && hideOnDesktop
            ? 'hidden'
            : hideOnMobile
              ? 'hidden md:block'
              : hideOnDesktop
                ? 'block md:hidden'
                : '',
    );

    const style: Record<string, string> = {};

    if (p.top) style.paddingTop = `${p.top}px`;
    if (p.right) style.paddingRight = `${p.right}px`;
    if (p.bottom) style.paddingBottom = `${p.bottom}px`;
    if (p.left) style.paddingLeft = `${p.left}px`;
    if (m.top) style.marginTop = `${m.top}px`;
    if (m.bottom) style.marginBottom = `${m.bottom}px`;
    if (maxWidth) style.maxWidth = maxWidth;
    if (animationDelay) style.animationDelay = `${animationDelay}ms`;

    return (
        <div className={classes} style={Object.keys(style).length > 0 ? style : undefined}>
            {children}
        </div>
    );
}
