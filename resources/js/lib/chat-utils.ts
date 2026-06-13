export function formatBytes(bytes: number | null | undefined): string {
    if (!bytes || bytes < 1) {
        return '0 B';
    }
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    if (bytes < 1024 * 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }
    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
}

export function formatChatDate(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return '';
    }
    try {
        return d.toLocaleString('es-BO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'America/La_Paz',
        });
    } catch {
        return d.toLocaleString();
    }
}

export function isPlaceholder(content: string | null | undefined): boolean {
    if (!content) {
        return false;
    }
    return /^\[(Imagen|Video|Audio|Archivo|Sticker|Documento|Contacto|Ubicación)\]$/i.test(content);
}

export function csrfHeaders(): Record<string, string> {
    const match = document.cookie.match(new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]*)'));
    const token = match ? decodeURIComponent(match[2]) : null;
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-XSRF-TOKEN': token } : {}),
    };
}
