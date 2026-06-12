import type { Currency } from './currency';

export function formatMoney(value: string | number, currency: Currency, code?: string): string {
    const n = typeof value === 'string' ? parseFloat(value) : value;
    const safeN = Number.isNaN(n) ? 0 : n;
    const sym = currency.symbol || currency.code;
    const dec = currency.decimals;
    const numStr = safeN.toLocaleString('es-MX', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    switch (currency.position) {
        case 'right':
        case 'right_space':
            return `${numStr} ${sym}`;
        case 'left_space':
            return `${sym} ${numStr}`;
        case 'left':
        default:
            return `${sym}${numStr}`;
    }
}
