import { Button } from '@/components/ui/button';

type Props = {
    currentPage: number;
    lastPage: number;
    onPageChange: (page: number) => void;
    total?: number;
    perPage?: number;
    itemLabel?: string;
};

export function TablePagination({ currentPage, lastPage, onPageChange, total, perPage, itemLabel = 'registros' }: Props) {
    if (lastPage <= 1 && total === undefined) return null;

    const pages: (number | 'ellipsis')[] = [];
    for (let i = 1; i <= lastPage; i++) {
        if (i === 1 || i === lastPage || (i >= currentPage - 1 && i <= currentPage + 1)) {
            pages.push(i);
        } else if (pages[pages.length - 1] !== 'ellipsis') {
            pages.push('ellipsis');
        }
    }

    const from = total !== undefined && total > 0 && perPage ? (currentPage - 1) * perPage + 1 : 0;
    const to = total !== undefined && perPage ? Math.min(currentPage * perPage, total) : 0;

    return (
        <div className="flex flex-col items-center justify-between gap-2 sm:flex-row">
            {total !== undefined && total > 0 && (
                <p className="text-sm text-muted-foreground">
                    {from}-{to} de {total} {itemLabel}
                </p>
            )}
            {lastPage > 1 && (
                <div className="flex items-center gap-1 text-sm">
                    <Button type="button" variant="ghost" size="sm" disabled={currentPage <= 1} onClick={() => onPageChange(currentPage - 1)}>
                        Anterior
                    </Button>
                    {pages.map((p, i) =>
                        p === 'ellipsis' ? (
                            <span key={`e-${i}`} className="px-1 text-muted-foreground">...</span>
                        ) : (
                            <Button
                                key={p}
                                type="button"
                                variant={p === currentPage ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => onPageChange(p)}
                                className="min-w-[32px] px-2"
                            >
                                {p}
                            </Button>
                        ),
                    )}
                    <Button type="button" variant="ghost" size="sm" disabled={currentPage >= lastPage} onClick={() => onPageChange(currentPage + 1)}>
                        Siguiente
                    </Button>
                </div>
            )}
        </div>
    );
}
