import * as React from 'react';
import { cn } from '@/lib/utils';

type TabsContextValue = {
    value: string;
    setValue: (value: string) => void;
};

const TabsContext = React.createContext<TabsContextValue | null>(null);

function useTabsContext(): TabsContextValue {
    const ctx = React.useContext(TabsContext);
    if (!ctx) {
        throw new Error('Tabs components must be used within <Tabs>');
    }
    return ctx;
}

type TabsProps = {
    value: string;
    onValueChange: (value: string) => void;
    children: React.ReactNode;
    className?: string;
};

export function Tabs({ value, onValueChange, children, className }: TabsProps) {
    return (
        <TabsContext value={{ value, setValue: onValueChange }}>
            <div className={className}>{children}</div>
        </TabsContext>
    );
}

export function TabsList({
    children,
    className,
}: {
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div
            role="tablist"
            className={cn(
                'inline-flex h-9 items-center justify-center rounded-lg bg-muted p-1 text-muted-foreground',
                className,
            )}
        >
            {children}
        </div>
    );
}

type TabsTriggerProps = {
    value: string;
    children: React.ReactNode;
    className?: string;
    disabled?: boolean;
};

export function TabsTrigger({ value, children, className, disabled }: TabsTriggerProps) {
    const { value: current, setValue } = useTabsContext();
    const active = current === value;
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            disabled={disabled}
            onClick={() => !disabled && setValue(value)}
            className={cn(
                'inline-flex items-center justify-center whitespace-nowrap rounded-md px-3 py-1 text-sm font-medium transition-all',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                'disabled:pointer-events-none disabled:opacity-50',
                active
                    ? 'bg-background text-foreground shadow'
                    : 'text-muted-foreground hover:text-foreground',
                className,
            )}
        >
            {children}
        </button>
    );
}

type TabsContentProps = {
    value: string;
    children: React.ReactNode;
    className?: string;
};

export function TabsContent({ value, children, className }: TabsContentProps) {
    const { value: current } = useTabsContext();
    if (current !== value) {
        return null;
    }
    return (
        <div role="tabpanel" className={cn('mt-2', className)}>
            {children}
        </div>
    );
}
