import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ChevronLeft, ChevronRight, Download, FileText, RefreshCw, Search } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { admin } from '@/routes';

type LogFile = {
    identifier: string;
    name: string;
    path: string;
    size: string;
    size_bytes: number;
    mtime: string;
    mtime_human: string;
};

type LogEntry = {
    datetime: string | null;
    datetime_human: string | null;
    level: string;
    message: string;
    context: Record<string, unknown>;
    extra: Record<string, unknown>;
    text: string;
};

type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type PageProps = {
    file: LogFile;
    entries: LogEntry[];
    filters: { q: string; level: string };
    availableLevels: string[];
    pagination: Pagination;
};

const LEVEL_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    debug: 'outline',
    info: 'secondary',
    notice: 'secondary',
    warning: 'default',
    error: 'destructive',
    critical: 'destructive',
    alert: 'destructive',
    emergency: 'destructive',
};

const LEVEL_CLASSES: Record<string, string> = {
    debug: 'bg-gray-500/10 text-gray-700 dark:text-gray-300',
    info: 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
    notice: 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300',
    warning: 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-300',
    error: 'bg-red-500/10 text-red-700 dark:text-red-300',
    critical: 'bg-red-500/20 text-red-700 dark:text-red-300',
    alert: 'bg-red-600/20 text-red-700 dark:text-red-300',
    emergency: 'bg-red-700/30 text-red-900 dark:text-red-200',
};

function levelClass(level: string): string {
    return LEVEL_CLASSES[level] ?? 'bg-gray-500/10';
}

function levelVariant(level: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    return LEVEL_VARIANT[level] ?? 'outline';
}

export default function LogViewerShow({
    file,
    entries,
    filters,
    availableLevels,
    pagination,
}: PageProps) {
    const [search, setSearch] = useState(filters.q);
    const [level, setLevel] = useState(filters.level);
    const [expandedId, setExpandedId] = useState<number | null>(null);

    function applyFilters(overrides: { q?: string; level?: string } = {}): void {
        const params: Record<string, string> = {};
        const q = overrides.q !== undefined ? overrides.q : search;
        const lv = overrides.level !== undefined ? overrides.level : level;
        if (q.trim() !== '') params.q = q;
        if (lv !== '') params.level = lv;
        router.get(`/admin/logs/${encodeURIComponent(file.identifier)}`, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function handleSearchSubmit(e: React.FormEvent): void {
        e.preventDefault();
        applyFilters({ q: search });
    }

    function handleLevelChange(value: string): void {
        const next = value === 'all' ? '' : value;
        setLevel(next);
        applyFilters({ level: next });
    }

    function handlePage(page: number): void {
        const params: Record<string, string> = { page: String(page) };
        if (filters.q) params.q = filters.q;
        if (filters.level) params.level = filters.level;
        router.get(`/admin/logs/${encodeURIComponent(file.identifier)}`, params, {
            preserveState: true,
            preserveScroll: false,
        });
    }

    function handleRefresh(): void {
        router.reload({ only: ['entries', 'pagination'] });
    }

    return (
        <>
            <Head title={`Logs: ${file.name}`} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <Button asChild variant="ghost" size="sm" className="mb-2 -ml-2">
                            <Link href="/admin/logs">
                                <ArrowLeft className="mr-1 size-4" />
                                Volver a la lista
                            </Link>
                        </Button>
                        <Heading
                            title={file.name}
                            description={`${file.path} · ${file.size} · modificado ${file.mtime_human}`}
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={handleRefresh}>
                            <RefreshCw className="mr-1 size-4" />
                            Refrescar
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <a href={`/admin/logs/${encodeURIComponent(file.identifier)}/download`} download>
                                <Download className="mr-1 size-4" />
                                Descargar
                            </a>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-medium">Filtros</CardTitle>
                        <CardDescription>
                            {pagination.total} entrada{pagination.total === 1 ? '' : 's'}
                            {filters.q && ` · búsqueda: "${filters.q}"`}
                            {filters.level && ` · nivel: ${filters.level}`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <form onSubmit={handleSearchSubmit} className="flex flex-1 items-end gap-2">
                                <div className="grid flex-1 gap-1.5">
                                    <label htmlFor="log-search" className="text-xs font-medium text-muted-foreground">
                                        Buscar en mensajes
                                    </label>
                                    <div className="relative">
                                        <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            id="log-search"
                                            value={search}
                                            onChange={(e) => setSearch(e.target.value)}
                                            placeholder="ej. ClassNotFoundException"
                                            className="pl-8"
                                        />
                                    </div>
                                </div>
                                <Button type="submit" variant="secondary">
                                    Buscar
                                </Button>
                            </form>
                            <div className="grid gap-1.5 sm:w-48">
                                <label htmlFor="log-level" className="text-xs font-medium text-muted-foreground">
                                    Nivel
                                </label>
                                <Select value={level === '' ? 'all' : level} onValueChange={handleLevelChange}>
                                    <SelectTrigger id="log-level">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Todos</SelectItem>
                                        {availableLevels.map((lv) => (
                                            <SelectItem key={lv} value={lv}>
                                                {lv.charAt(0).toUpperCase() + lv.slice(1)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <FileText className="size-4" />
                            Entradas
                            <span className="text-xs font-normal text-muted-foreground">
                                (página {pagination.current_page} de {pagination.last_page})
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {entries.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 py-12 text-center">
                                <FileText className="size-10 text-muted-foreground/50" />
                                <p className="text-sm text-muted-foreground">
                                    No hay entradas que coincidan con los filtros.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {entries.map((entry, idx) => {
                                    const id = idx;
                                    const isExpanded = expandedId === id;
                                    const hasContext = Object.keys(entry.context).length > 0
                                        || Object.keys(entry.extra).length > 0;
                                    return (
                                        <div
                                            key={id}
                                            className="rounded-md border bg-card text-sm"
                                        >
                                            <button
                                                type="button"
                                                onClick={() => setExpandedId(isExpanded ? null : id)}
                                                className="flex w-full items-start gap-3 p-3 text-left"
                                            >
                                                <Badge
                                                    variant={levelVariant(entry.level)}
                                                    className={`shrink-0 font-mono text-[10px] uppercase ${levelClass(entry.level)}`}
                                                >
                                                    {entry.level || 'none'}
                                                </Badge>
                                                <div className="min-w-0 flex-1">
                                                    <p className="break-all text-xs leading-relaxed">
                                                        {entry.message || <span className="text-muted-foreground">(sin mensaje)</span>}
                                                    </p>
                                                    <p className="mt-1 font-mono text-[10px] text-muted-foreground">
                                                        {entry.datetime_human ?? '—'}
                                                    </p>
                                                </div>
                                            </button>
                                            {isExpanded && (
                                                <div className="border-t bg-muted/30 p-3">
                                                    <pre className="overflow-x-auto rounded bg-background p-2 font-mono text-[10px] leading-relaxed">
                                                        {entry.text}
                                                    </pre>
                                                    {hasContext && (
                                                        <div className="mt-2 space-y-2">
                                                            {Object.keys(entry.context).length > 0 && (
                                                                <details>
                                                                    <summary className="cursor-pointer text-xs font-medium text-muted-foreground">
                                                                        Context
                                                                    </summary>
                                                                    <pre className="mt-1 overflow-x-auto rounded bg-background p-2 font-mono text-[10px]">
                                                                        {JSON.stringify(entry.context, null, 2)}
                                                                    </pre>
                                                                </details>
                                                            )}
                                                            {Object.keys(entry.extra).length > 0 && (
                                                                <details>
                                                                    <summary className="cursor-pointer text-xs font-medium text-muted-foreground">
                                                                        Extra
                                                                    </summary>
                                                                    <pre className="mt-1 overflow-x-auto rounded bg-background p-2 font-mono text-[10px]">
                                                                        {JSON.stringify(entry.extra, null, 2)}
                                                                    </pre>
                                                                </details>
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        {pagination.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between border-t pt-3 text-sm">
                                <p className="text-xs text-muted-foreground">
                                    Mostrando {pagination.from ?? 0}–{pagination.to ?? 0} de {pagination.total}
                                </p>
                                <div className="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={pagination.current_page <= 1}
                                        onClick={() => handlePage(pagination.current_page - 1)}
                                    >
                                        <ChevronLeft className="size-4" />
                                        Anterior
                                    </Button>
                                    <span className="px-2 text-xs text-muted-foreground">
                                        {pagination.current_page} / {pagination.last_page}
                                    </span>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={pagination.current_page >= pagination.last_page}
                                        onClick={() => handlePage(pagination.current_page + 1)}
                                    >
                                        Siguiente
                                        <ChevronRight className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

LogViewerShow.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Visor de logs', href: '/admin/logs' },
        { title: 'Detalle', href: '' },
    ],
};
