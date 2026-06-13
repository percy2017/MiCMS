/**
 * WhatsApp-flavored markdown renderer.
 *
 * Supports the WhatsApp formatting syntax:
 *   *bold*        → <strong>bold</strong>
 *   _italic_      → <em>italic</em>
 *   ~strike~      → <del>strike</del>
 *   ```code```    → <code>code</code>
 *   [text](url)   → <a href="url">text</a>
 *   line breaks   → <br>
 *
 * Used by WhatsAppEditor for live preview only. The raw markdown is
 * stored in the DB and sent to WhatsApp, which renders it natively.
 */

const ESCAPE_MAP: Record<string, string> = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
};

function escapeHtml(s: string): string {
    return s.replace(/[&<>"']/g, (c) => ESCAPE_MAP[c]);
}

function isSafeUrl(url: string): boolean {
    const trimmed = url.trim().toLowerCase();
    return (
        trimmed.startsWith('http://') ||
        trimmed.startsWith('https://') ||
        trimmed.startsWith('mailto:') ||
        trimmed.startsWith('tel:') ||
        trimmed.startsWith('/')
    );
}

type Token = { type: 'text' | 'code'; content: string };

/**
 * Tokenize the source into "code spans" and "text spans" so that
 * markdown inside ```code``` is rendered literally and not parsed.
 */
function tokenize(src: string): Token[] {
    const tokens: Token[] = [];
    const re = /```([^`\n]+)```/g;
    let last = 0;
    let m: RegExpExecArray | null;
    while ((m = re.exec(src)) !== null) {
        if (m.index > last) {
            tokens.push({ type: 'text', content: src.slice(last, m.index) });
        }
        tokens.push({ type: 'code', content: m[1] });
        last = re.lastIndex;
    }
    if (last < src.length) {
        tokens.push({ type: 'text', content: src.slice(last) });
    }
    return tokens;
}

function renderText(text: string): string {
    let out = escapeHtml(text);

    // Links [text](url) — must run before other inline formatters.
    out = out.replace(
        /\[([^\]\n]+)\]\(([^\s)]+)(?:\s+&quot;([^&]*)&quot;)?\)/g,
        (_full, label: string, url: string) => {
            const safe = isSafeUrl(url) ? url : '#';
            return `<a href="${escapeHtml(safe)}" target="_blank" rel="noopener noreferrer">${label}</a>`;
        },
    );

    // Bold *text* — non-greedy, must not span newlines.
    out = out.replace(/(^|[^*])\*([^*\n]+)\*/g, (_full, pre: string, inner: string) => `${pre}<strong>${inner}</strong>`);

    // Italic _text_
    out = out.replace(/(^|[^_])_([^_\n]+)_/g, (_full, pre: string, inner: string) => `${pre}<em>${inner}</em>`);

    // Strikethrough ~text~
    out = out.replace(/(^|[^~])~([^~\n]+)~/g, (_full, pre: string, inner: string) => `${pre}<del>${inner}</del>`);

    return out;
}

export function renderWhatsAppMarkdown(src: string): string {
    if (!src) return '';
    const tokens = tokenize(src);
    const html = tokens
        .map((t) => (t.type === 'code' ? `<code>${escapeHtml(t.content)}</code>` : renderText(t.content)))
        .join('');
    // Convert newlines to <br> (WhatsApp shows single Enter as new line)
    return html.replace(/\n/g, '<br>');
}

/**
 * Slugify a title into a valid shortcut.
 *
 * Rules:
 *  1. lowercase
 *  2. NFD unicode normalization + remove combining marks (accents)
 *  3. replace any non [a-z0-9] with -
 *  4. collapse multiple - into single -
 *  5. trim - from start/end
 *  6. truncate to 50 chars
 */
export function slugifyShortcut(input: string, maxLength = 50): string {
    return input
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, maxLength);
}
