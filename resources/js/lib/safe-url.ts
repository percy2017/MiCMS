/**
 * Returns the URL if it is safe to use in href / src.
 * Blocks javascript:, data:, vbscript:, file: schemes and embedded payloads.
 */
export function isSafeUrl(url: string | null | undefined): string {
    if (!url) {
        return '';
    }

    const trimmed = url.trim();

    if (trimmed === '') {
        return '';
    }

    const lower = trimmed.toLowerCase();

    for (const blocked of ['javascript:', 'data:', 'vbscript:', 'file:']) {
        if (lower.startsWith(blocked)) {
            return '';
        }
    }

    const cleaned = trimmed.replace(/[\u0000-\u001F\u007F]/g, '');

    return cleaned;
}
