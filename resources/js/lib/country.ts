/**
 * Helpers de país para UI. CERO hardcoding de países.
 *
 * - countryFlag(): genera emoji de bandera a partir de código ISO-3166-alpha2
 *   usando Regional Indicator Symbols (Unicode nativo, sin librería).
 * - countryName(): nombre localizado via Intl.DisplayNames (API nativa del navegador).
 * - countryDialCode(): prefijo de marcado internacional derivado via Intl.NumberFormat.
 */

const REGIONAL_INDICATOR_A = 0x1F1E6;

export function countryFlag(code: string | null | undefined): string {
    if (!code || code.length !== 2) {
        return '🌐';
    }
    const upper = code.toUpperCase();
    const a = upper.charCodeAt(0);
    const b = upper.charCodeAt(1);
    if (a < 65 || a > 90 || b < 65 || b > 90) {
        return '🌐';
    }
    return String.fromCodePoint(
        REGIONAL_INDICATOR_A + (a - 65),
        REGIONAL_INDICATOR_A + (b - 65),
    );
}

const namesCache = new Map<string, string>();

export function countryName(code: string | null | undefined, locale: string = 'es'): string {
    if (!code || code.length !== 2) {
        return '';
    }
    const upper = code.toUpperCase();
    const cacheKey = `${upper}:${locale}`;
    if (namesCache.has(cacheKey)) {
        return namesCache.get(cacheKey) ?? '';
    }
    try {
        const dn = new Intl.DisplayNames([locale], { type: 'region' });
        const name = dn.of(upper) ?? '';
        if (name) {
            namesCache.set(cacheKey, name);
        }
        return name;
    } catch {
        return '';
    }
}

const dialCache = new Map<string, string>();

export function countryDialCode(code: string | null | undefined): string {
    if (!code || code.length !== 2) {
        return '';
    }
    const upper = code.toUpperCase();
    if (dialCache.has(upper)) {
        return dialCache.get(upper) ?? '';
    }
    try {
        const parts = new Intl.NumberFormat('en', {
            style: 'international',
            numberingSystem: 'latn',
        }).formatToParts(1);
        const dial = parts
            .filter((p) => p.type === 'literal' && p.value !== ' ')
            .map((p) => p.value)
            .join('');
        const result = dial.replace(/[^\d+]/g, '') || '';
        if (result) {
            dialCache.set(upper, result);
        }
        return result;
    } catch {
        return '';
    }
}

export function countryIsValid(code: string | null | undefined): boolean {
    if (!code || code.length !== 2) {
        return false;
    }
    try {
        return Boolean(new Intl.DisplayNames(['en'], { type: 'region' }).of(code.toUpperCase()));
    } catch {
        return false;
    }
}

export type CountryDisplay = {
    code: string;
    flag: string;
    name: string;
    dialCode: string;
};

export function describeCountry(code: string | null | undefined, locale: string = 'es'): CountryDisplay | null {
    if (!code || code.length !== 2) {
        return null;
    }
    return {
        code: code.toUpperCase(),
        flag: countryFlag(code),
        name: countryName(code, locale),
        dialCode: countryDialCode(code),
    };
}
