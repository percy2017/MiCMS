import { Head, Link, router } from '@inertiajs/react';
import { Download, FileText, Loader2, ScrollText, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import { admin } from '@/routes';

type LogFileRow = {
    identifier: string;
    name: string;
    path: string;
    size: string;
    size_bytes: number;
    mtime: string;
    mtime_human: string;
    errors_total: number;
    errors_today: number;
};

type PageProps = {
    files: LogFileRow[];
    levels: string[];
};

export default function LogViewerIndex({ files }: PageProps) {
    const canDelete = useCan('delete logs');
    const [pendingId, setPendingId] = useState<string | null>(null);

    function handleDelete(file: LogFileRow): void {
        if (! confirm(`¿Eliminar el archivo "${file.name}"? Esta acción no se puede deshacer.`)) {
            return;
        }
        setPendingId(file.identifier);
        router.delete(`/admin/logs/${encodeURIComponent(file.identifier)}`, {
            preserveScroll: true,
            onFinish: () => setPendingId(null),
        });
    }

    const totalErrorsToday = files.reduce((acc, f) => acc + f.errors_today, 0);
    const totalSize = files.reduce((acc, f) => acc + f.size_bytes, 0);

    function formatBytes(bytes: number): string {
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
    }

    return (
        <>
            <Head title="Visor de logs" />

            <div className="space-y-6 p-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Archivos</CardDescription>
                            <CardTitle className="text-2xl">{files.length}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Espacio total</CardDescription>
                            <CardTitle className="text-2xl">{formatBytes(totalSize)}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Errores hoy</CardDescription>
                            <CardTitle className="text-2xl">
                                <span className={totalErrorsToday > 0 ? 'text-destructive' : ''}>
                                    {totalErrorsToday}
                                </span>
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-medium">Archivos de log</CardTitle>
                        <CardDescription>
                            {files.length === 0
                                ? 'No se encontraron archivos de log.'
                                : `${files.length} archivo${files.length === 1 ? '' : 's'} disponible${files.length === 1 ? '' : 's'}.`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {files.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 py-12 text-center">
                                <ScrollText className="size-10 text-muted-foreground/50" />
                                <p className="text-sm text-muted-foreground">
                                    No hay archivos en <code className="rounded bg-muted px-1">storage/logs</code>.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/50 text-left">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">Archivo</th>
                                            <th className="px-3 py-2 font-medium">Tamaño</th>
                                            <th className="px-3 py-2 font-medium">Modificado</th>
                                            <th className="px-3 py-2 text-center font-medium">Errores hoy</th>
                                            <th className="px-3 py-2 text-right font-medium">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {files.map((file) => {
                                            const isPending = pendingId === file.identifier;
                                            return (
                                                <tr key={file.identifier} className="hover:bg-muted/30">
                                                    <td className="px-3 py-2">
                                                        <div className="flex items-center gap-2">
                                                            <FileText className="size-4 shrink-0 text-muted-foreground" />
                                                            <Link
                                                                href={`/admin/logs/${encodeURIComponent(file.identifier)}`}
                                                                className="font-mono text-xs hover:underline"
                                                            >
                                                                {file.name}
                                                            </Link>
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-2 text-muted-foreground">{file.size}</td>
                                                    <td className="px-3 py-2 text-muted-foreground">{file.mtime_human}</td>
                                                    <td className="px-3 py-2 text-center">
                                                        {file.errors_today > 0 ? (
                                                            <Badge variant="destructive">{file.errors_today}</Badge>
                                                        ) : (
                                                            <span className="text-muted-foreground">0</span>
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-right">
                                                        <div className="flex items-center justify-end gap-1">
                                                            <Button asChild size="sm" variant="ghost">
                                                                <Link href={`/admin/logs/${encodeURIComponent(file.identifier)}`}>
                                                                    Ver
                                                                </Link>
                                                            </Button>
                                                            <Button asChild size="sm" variant="ghost">
                                                                <a
                                                                    href={`/admin/logs/${encodeURIComponent(file.identifier)}/download`}
                                                                    download
                                                                >
                                                                    <Download className="size-4" />
                                                                </a>
                                                            </Button>
                                                            {canDelete && (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    disabled={isPending}
                                                                    onClick={() => handleDelete(file)}
                                                                >
                                                                    {isPending ? (
                                                                        <Loader2 className="size-4 animate-spin" />
                                                                    ) : (
                                                                        <Trash2 className="size-4 text-destructive" />
                                                                    )}
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

LogViewerIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin() },
        { title: 'Visor de logs', href: '/admin/logs' },
    ],
};
