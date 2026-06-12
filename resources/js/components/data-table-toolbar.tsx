import { Link } from '@inertiajs/react';
import { Loader2, Plus, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type FilterOption = {
    value: string;
    label: string;
};

export type ToolbarFilter = {
    key: string;
    label: string;
    options: FilterOption[];
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
};

type Props = {
    search: string;
    onSearchChange: (value: string) => void;
    searchPlaceholder?: string;
    loading?: boolean;
    total?: number;
    totalLabel?: string;
    filters?: ToolbarFilter[];
    createHref?: string;
    createLabel?: string;
    actions?: ReactNode;
};

export function DataTableToolbar({
    search,
    onSearchChange,
    searchPlaceholder = 'Buscar...',
    loading = false,
    total,
    totalLabel,
    filters = [],
    createHref,
    createLabel = 'Nuevo',
    actions,
}: Props) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            <div className="relative flex-1 min-w-[200px] sm:max-w-sm">
                <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={(e) => onSearchChange(e.target.value)}
                    placeholder={searchPlaceholder}
                    className="pl-9"
                />
            </div>
            {filters.map((f) => (
                <select
                    key={f.key}
                    value={f.value}
                    onChange={(e) => f.onChange(e.target.value)}
                    className={`h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring ${f.className ?? ''}`}
                    aria-label={f.label}
                >
                    {f.placeholder !== undefined && (
                        <option value="">{f.placeholder}</option>
                    )}
                    {f.options.map((opt) => (
                        <option key={opt.value} value={opt.value}>
                            {opt.label}
                        </option>
                    ))}
                </select>
            ))}
            {createHref && (
                <Button asChild>
                    <Link href={createHref}>
                        <Plus className="mr-2 size-4" />
                        {createLabel}
                    </Link>
                </Button>
            )}
            {actions}
            {typeof total === 'number' && total > 0 && totalLabel && (
                <p className="text-sm text-muted-foreground whitespace-nowrap">{total} {totalLabel}</p>
            )}
            {loading && <Loader2 className="size-4 animate-spin text-muted-foreground shrink-0" />}
        </div>
    );
}
